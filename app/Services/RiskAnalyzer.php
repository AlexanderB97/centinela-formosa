<?php

namespace App\Services;

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Services\Analisis\ExtractorDeUrls;
use App\Services\Analisis\Huella;
use App\Services\Analisis\ReglasHeuristicas;
use App\Services\Analisis\ResultadoAnalisis;
use App\Services\Analisis\Senal;
use InvalidArgumentException;
use Throwable;

/**
 * Risk analysis of a text, link or decoded QR. The level is 100% deterministic:
 * confirmed cases + own rules + VirusTotal. Gemini only words the explanation.
 *
 * Usable directly from PHP (e.g. a Livewire component) or through POST /analizar.
 */
class RiskAnalyzer
{
    /**
     * Minimum total weight of the signals for each level.
     */
    private const PESO_RIESGO = 3;

    private const PESO_DUDOSO = 1;

    private const RAZON_SIN_SENALES = 'No se detectaron patrones típicos de estafa.';

    public function __construct(
        private readonly ReglasHeuristicas $reglas,
        private readonly VirusTotalClient $virusTotal,
        private readonly GeminiClient $gemini,
    ) {}

    /**
     * @param  TipoContenido|string  $tipo  'texto', 'link' or 'qr'
     *
     * @throws InvalidArgumentException when the type is unknown or the content is empty.
     */
    public function analizar(TipoContenido|string $tipo, string $contenido): ResultadoAnalisis
    {
        $tipo = $tipo instanceof TipoContenido ? $tipo : TipoContenido::tryFrom($tipo)
            ?? throw new InvalidArgumentException("Tipo de contenido inválido: {$tipo}");

        $contenido = trim($contenido);

        if ($contenido === '') {
            throw new InvalidArgumentException('El contenido a analizar está vacío.');
        }

        $urls = ExtractorDeUrls::extraer($contenido);
        $senales = $this->reglas->evaluar($contenido);

        if ($this->esCasoConfirmado($contenido, $urls)) {
            // A confirmed case is conclusive: VirusTotal is not consulted.
            array_unshift($senales, new Senal('Coincide con un caso de estafa ya confirmado por nuestro equipo.', self::PESO_RIESGO));
            $nivel = NivelRiesgo::Riesgo;
        } else {
            array_push($senales, ...$this->consultarVirusTotal($urls));
            $nivel = $this->calcularNivel($senales);
        }

        $razones = $this->razones($senales);
        $explicacionIa = $this->gemini->redactarExplicacion($nivel, $razones);

        $resultado = new ResultadoAnalisis(
            nivel: $nivel,
            razones: $razones,
            explicacion: $explicacionIa ?? $nivel->explicacionDeRespaldo(),
            explicacionGeneradaPorIa: $explicacionIa !== null,
        );

        $this->guardar($tipo, $contenido, $resultado);

        return $resultado;
    }

    /**
     * @param  list<string>  $urls
     */
    private function esCasoConfirmado(string $contenido, array $urls): bool
    {
        $huellas = [Huella::de($contenido), ...array_map(Huella::de(...), $urls)];

        return CasoConfirmado::query()->whereIn('huella', array_unique($huellas))->exists();
    }

    /**
     * @param  list<string>  $urls
     * @return list<Senal>
     */
    private function consultarVirusTotal(array $urls): array
    {
        if ($urls === [] || ! $this->virusTotal->estaConfigurado()) {
            return [];
        }

        $senales = [];
        $fallo = false;

        foreach (array_slice($urls, 0, max(1, (int) config('services.virustotal.max_urls'))) as $url) {
            $veredicto = $this->virusTotal->consultarUrl($url);

            if ($veredicto === null) {
                $fallo = true;
            } elseif ($veredicto->maliciosos >= 2) {
                $senales[] = new Senal("{$veredicto->maliciosos} servicios de seguridad de VirusTotal marcan el enlace como malicioso.", self::PESO_RIESGO);
            } elseif ($veredicto->maliciosos === 1 || $veredicto->sospechosos > 0) {
                $senales[] = new Senal('Algún servicio de seguridad de VirusTotal marca el enlace como sospechoso.', 1);
            } elseif ($veredicto->encontrado) {
                $senales[] = new Senal('VirusTotal no reporta el enlace como peligroso.', 0);
            }
        }

        if ($fallo) {
            $senales[] = new Senal('No pudimos consultar VirusTotal: el resultado se basa en nuestras reglas.', 0);
        }

        return array_values(array_unique($senales, SORT_REGULAR));
    }

    /**
     * @param  list<Senal>  $senales
     */
    private function calcularNivel(array $senales): NivelRiesgo
    {
        $peso = array_sum(array_map(fn (Senal $senal) => $senal->peso, $senales));

        return match (true) {
            $peso >= self::PESO_RIESGO => NivelRiesgo::Riesgo,
            $peso >= self::PESO_DUDOSO => NivelRiesgo::Dudoso,
            default => NivelRiesgo::Seguro,
        };
    }

    /**
     * Reasons ordered from the heaviest signal to the lightest.
     *
     * @param  list<Senal>  $senales
     * @return list<string>
     */
    private function razones(array $senales): array
    {
        usort($senales, fn (Senal $a, Senal $b) => $b->peso <=> $a->peso);

        $razones = array_map(fn (Senal $senal) => $senal->razon, $senales);

        if (! array_filter($senales, fn (Senal $senal) => $senal->peso > 0)) {
            array_unshift($razones, self::RAZON_SIN_SENALES);
        }

        return array_values(array_unique($razones));
    }

    private function guardar(TipoContenido $tipo, string $contenido, ResultadoAnalisis $resultado): void
    {
        try {
            Analisis::create([
                'tipo' => $tipo,
                'contenido' => $contenido,
                'nivel' => $resultado->nivel,
                'razones' => $resultado->razones,
                'explicacion' => $resultado->explicacion,
                'explicacion_generada_por_ia' => $resultado->explicacionGeneradaPorIa,
            ]);
        } catch (Throwable $e) {
            // Failing to log the analysis must not deny the citizen their result.
            report($e);
        }
    }
}
