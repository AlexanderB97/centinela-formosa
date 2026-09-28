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
 * Looks up existing VirusTotal reports of URLs and file hashes. Never throws: any failure
 * (timeout, HTTP error, quota exceeded, bad payload) returns null so the caller decides.
 *
 * Files are only ever looked up by their SHA-256 hash: nothing is uploaded to VirusTotal.
 */
class VirusTotalClient
{
    private const ENDPOINT_URLS = 'https://www.virustotal.com/api/v3/urls/';

    private const ENDPOINT_ARCHIVOS = 'https://www.virustotal.com/api/v3/files/';

    public function estaConfigurado(): bool
    {
        return filled(config('services.virustotal.key'));
    }

    /**
     * Null means VirusTotal is not configured or could not be reached.
     */
    public function consultarUrl(string $url): ?VeredictoVirusTotal
    {
        $url = ExtractorDeUrls::conEsquema($url);

        // VirusTotal identifies URLs by their unpadded base64url encoding.
        $id = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        return $this->consultar('virustotal:url:'.hash('sha256', $url), self::ENDPOINT_URLS.$id);
    }

    /**
     * Looks up a file by its SHA-256 hash (64 lowercase hex characters). Null means VirusTotal
     * is not configured or could not be reached; a hash it does not know is noEncontrado().
     */
    public function consultarArchivo(string $sha256): ?VeredictoVirusTotal
    {
        return $this->consultar('virustotal:file:'.$sha256, self::ENDPOINT_ARCHIVOS.$sha256);
    }

    private function consultar(string $claveCache, string $endpoint): ?VeredictoVirusTotal
    {
        if (! $this->estaConfigurado()) {
            return null;
        }

        /** @var array{encontrado: bool, maliciosos: int, sospechosos: int}|null $cacheado */
        $cacheado = Cache::get($claveCache);

        if (is_array($cacheado)) {
            return new VeredictoVirusTotal(...$cacheado);
        }

        $veredicto = $this->pedir($endpoint);

        if ($veredicto !== null) {
            Cache::put($claveCache, [
                'encontrado' => $veredicto->encontrado,
                'maliciosos' => $veredicto->maliciosos,
                'sospechosos' => $veredicto->sospechosos,
            ], (int) config('services.virustotal.cache_ttl'));
        }

        return $veredicto;
    }

    private function pedir(string $endpoint): ?VeredictoVirusTotal
    {
        try {
            $response = Http::withHeaders(['x-apikey' => (string) config('services.virustotal.key')])
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout((int) config('services.virustotal.timeout'))
                ->get($endpoint);
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

        // Logged apart so a quota problem is easy to spot: the key is shared by links and files.
        if ($response->tooManyRequests()) {
            Log::warning('VirusTotal rechazó la consulta por cuota (429).');

            return null;
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
