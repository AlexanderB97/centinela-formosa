<?php

namespace App\Services\Analisis;

use Illuminate\Support\Str;

/**
 * Finds links inside free text, including bare domains like "bit.ly/abc".
 */
final class ExtractorDeUrls
{
    private const PATRON = '~(?<![@\w.-])(?:https?://)?(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z][a-z0-9-]{1,23}|\d{1,3}(?:\.\d{1,3}){3})(?::\d{1,5})?(?:[/?#][^\s<>"\']*)?~iu';

    /**
     * TLDs accepted for bare domains without scheme, "www." or path, to avoid
     * treating things like "Sr.Pérez" or "hola.chau" as links.
     */
    private const TLDS_SIN_ESQUEMA = [
        'ar', 'com', 'net', 'org', 'info', 'io', 'co', 'me', 'ly', 'gl', 'gd', 'app', 'xyz', 'top',
        'click', 'site', 'online', 'link', 'live', 'shop', 'store', 'club', 'icu', 'vip', 'buzz', 'tk',
    ];

    /**
     * @return list<string>
     */
    public static function extraer(string $texto): array
    {
        preg_match_all(self::PATRON, $texto, $coincidencias);

        $urls = [];

        foreach ($coincidencias[0] as $coincidencia) {
            $url = rtrim($coincidencia, '.,;:!?)]}\'"');

            if (self::pareceLink($url)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    public static function esUrlUnica(string $contenido): bool
    {
        $contenido = trim($contenido);

        return ! preg_match('~\s~', $contenido) && self::extraer($contenido) === [$contenido];
    }

    /**
     * Adds "http://" when the link has no scheme, so it can be parsed or sent to VirusTotal.
     */
    public static function conEsquema(string $url): string
    {
        return preg_match('~^https?://~i', $url) ? $url : 'http://'.$url;
    }

    public static function host(string $url): string
    {
        return Str::lower((string) parse_url(self::conEsquema($url), PHP_URL_HOST));
    }

    private static function pareceLink(string $url): bool
    {
        if (preg_match('~^(https?://|www\.)~i', $url) || preg_match('~[/?#]~', $url)) {
            return true;
        }

        $host = self::host($url);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return in_array(Str::afterLast($host, '.'), self::TLDS_SIN_ESQUEMA, true);
    }
}
