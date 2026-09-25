<?php

namespace App\Services\Analisis;

use Illuminate\Support\Str;

/**
 * Normalized fingerprint of a piece of content, used to match confirmed cases
 * regardless of casing, spacing, scheme or "www." differences.
 */
final class Huella
{
    public static function de(string $contenido): string
    {
        $contenido = trim($contenido);

        $normalizado = ExtractorDeUrls::esUrlUnica($contenido)
            ? 'url:'.self::normalizarUrl($contenido)
            : 'texto:'.Str::squish(Str::lower($contenido));

        return hash('sha256', $normalizado);
    }

    public static function normalizarUrl(string $url): string
    {
        $url = Str::lower(trim($url));
        $url = (string) preg_replace('~^[a-z]+://~', '', $url);
        $url = (string) preg_replace('~^www\.~', '', $url);

        return rtrim($url, '/');
    }
}
