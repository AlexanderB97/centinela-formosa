<?php

namespace App\Services;

use App\Services\Analisis\ExtractorDeUrls;
use App\Services\Analisis\VeredictoVirusTotal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Looks up the existing VirusTotal report of a URL. Never throws: any failure
 * (timeout, HTTP error, bad payload) returns null so the analysis keeps going.
 */
class VirusTotalClient
{
    private const ENDPOINT = 'https://www.virustotal.com/api/v3/urls/';

    public function estaConfigurado(): bool
    {
        return filled(config('services.virustotal.key'));
    }

    /**
     * Null means VirusTotal is not configured or could not be reached.
     */
    public function consultarUrl(string $url): ?VeredictoVirusTotal
    {
        if (! $this->estaConfigurado()) {
            return null;
        }

        $url = ExtractorDeUrls::conEsquema($url);
        $claveCache = 'virustotal:url:'.hash('sha256', $url);

        /** @var array{encontrado: bool, maliciosos: int, sospechosos: int}|null $cacheado */
        $cacheado = Cache::get($claveCache);

        if (is_array($cacheado)) {
            return new VeredictoVirusTotal(...$cacheado);
        }

        $veredicto = $this->pedir($url);

        if ($veredicto !== null) {
            Cache::put($claveCache, [
                'encontrado' => $veredicto->encontrado,
                'maliciosos' => $veredicto->maliciosos,
                'sospechosos' => $veredicto->sospechosos,
            ], (int) config('services.virustotal.cache_ttl'));
        }

        return $veredicto;
    }

    private function pedir(string $url): ?VeredictoVirusTotal
    {
        // VirusTotal identifies URLs by their unpadded base64url encoding.
        $id = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        try {
            $response = Http::withHeaders(['x-apikey' => (string) config('services.virustotal.key')])
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout((int) config('services.virustotal.timeout'))
                ->get(self::ENDPOINT.$id);
        } catch (ConnectionException $e) {
            Log::warning('VirusTotal no respondió a tiempo.', ['error' => $e->getMessage()]);

            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($response->notFound()) {
            return VeredictoVirusTotal::noEncontrado();
        }

        $estadisticas = $response->json('data.attributes.last_analysis_stats');

        if (! $response->successful() || ! is_array($estadisticas)) {
            Log::warning('VirusTotal devolvió una respuesta inesperada.', ['status' => $response->status()]);

            return null;
        }

        return new VeredictoVirusTotal(
            encontrado: true,
            maliciosos: (int) ($estadisticas['malicious'] ?? 0),
            sospechosos: (int) ($estadisticas['suspicious'] ?? 0),
        );
    }
}
