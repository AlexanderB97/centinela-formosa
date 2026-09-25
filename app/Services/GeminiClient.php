<?php

namespace App\Services;

use App\Enums\NivelRiesgo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks Gemini to word the explanation of a result the backend already decided.
 * It only receives the level and the deterministic reasons, never the analyzed
 * content, so it cannot influence the level. Never throws: returns null on failure.
 */
class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const LARGO_MAXIMO = 800;

    public function estaConfigurado(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @param  list<string>  $razones
     */
    public function redactarExplicacion(NivelRiesgo $nivel, array $razones): ?string
    {
        if (! $this->estaConfigurado()) {
            return null;
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout((int) config('services.gemini.timeout'))
                ->post(sprintf(self::ENDPOINT, config('services.gemini.model')), [
                    'system_instruction' => ['parts' => [['text' => $this->instrucciones()]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $this->prompt($nivel, $razones)]]]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 300,
                        'thinkingConfig' => ['thinkingBudget' => 0],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Gemini no respondió a tiempo.', ['error' => $e->getMessage()]);

            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $response->successful() || $response->json('candidates.0.finishReason') !== 'STOP') {
            Log::warning('Gemini devolvió una respuesta inutilizable.', ['status' => $response->status()]);

            return null;
        }

        $partes = $response->json('candidates.0.content.parts', []);
        $texto = trim(implode('', array_map(
            fn ($parte) => is_array($parte) && is_string($parte['text'] ?? null) ? $parte['text'] : '',
            is_array($partes) ? $partes : [],
        )));

        if ($texto === '' || mb_strlen($texto) > self::LARGO_MAXIMO) {
            return null;
        }

        return $texto;
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
            Sos el asistente de Centinela Formosa, un servicio público que ayuda a vecinos a detectar estafas digitales.
            Tu única tarea es redactar una explicación breve (2 o 3 oraciones, máximo 60 palabras) en español rioplatense, con voseo,
            para una persona sin conocimientos técnicos. El nivel de riesgo ya fue decidido y no podés cambiarlo, discutirlo ni
            suavizarlo: explicá ese nivel usando solo las señales recibidas y terminá con un consejo práctico.
            No uses markdown, listas, emojis ni comillas. No inventes señales nuevas.
            TXT;
    }

    /**
     * @param  list<string>  $razones
     */
    private function prompt(NivelRiesgo $nivel, array $razones): string
    {
        $etiqueta = match ($nivel) {
            NivelRiesgo::Seguro => 'seguro (no se detectaron señales de estafa)',
            NivelRiesgo::Dudoso => 'dudoso (hay señales que piden precaución)',
            NivelRiesgo::Riesgo => 'riesgo (señales claras de estafa)',
        };

        $senales = $razones === [] ? '- Ninguna.' : '- '.implode("\n- ", $razones);

        return "Nivel de riesgo: {$etiqueta}\nSeñales detectadas:\n{$senales}";
    }
}
