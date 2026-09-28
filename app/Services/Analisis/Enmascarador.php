<?php

namespace App\Services\Analisis;

use Illuminate\Support\Str;

/**
 * Hides data that could identify someone before showing analyzed content in public
 * (emails, phone numbers, long numbers, URL query strings) and truncates it.
 * Only for display: the stored content is never modified.
 */
final class Enmascarador
{
    public const LARGO_TEXTO = 140;

    public const LARGO_LINK = 80;

    /**
     * Masks a piece of content with the rules of its shape: a single link or free text.
     */
    public static function contenido(string $contenido): string
    {
        return ExtractorDeUrls::esUrlUnica($contenido) ? self::link($contenido) : self::texto($contenido);
    }

    public static function texto(string $texto): string
    {
        // Query strings and fragments of links inside the text often carry tokens.
        $texto = (string) preg_replace('~((?:https?://|www\.)[^\s?#]+)[?#]\S*~iu', '$1', Str::squish($texto));

        return Str::limit(self::datos($texto), self::LARGO_TEXTO, '…');
    }

    public static function link(string $link): string
    {
        $link = (string) preg_replace('~[?#].*$~s', '', trim($link));

        return Str::limit(self::datos($link), self::LARGO_LINK, '…');
    }

    private static function datos(string $texto): string
    {
        $texto = (string) preg_replace('~[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}~iu', '[email]', $texto);

        return (string) preg_replace_callback('~(?:\+|\()?\d[\d\s().-]{5,}\d~u', function (array $coincidencia) {
            $numero = $coincidencia[0];
            $digitos = strlen((string) preg_replace('~\D~', '', $numero));

            // IP addresses (e.g. phishing links like http://192.168.10.5/pago) are not personal data.
            if ($digitos < 7 || preg_match('~^\d{1,3}(?:\.\d{1,3}){3}$~', $numero)) {
                return $numero;
            }

            // Cards (15-19 digits) and CBUs (22) are longer than any phone, even +54 9 mobiles (13).
            // DNIs are usually written with dots only (30.123.456).
            if ($digitos >= 14 || preg_match('~^\d{1,3}(?:\.\d{3})+$~', $numero)) {
                return '[número]';
            }

            return '[teléfono]';
        }, $texto);
    }
}
