<?php

namespace App\Enums;

enum TipoContenido: string
{
    case Texto = 'texto';
    case Link = 'link';
    case Qr = 'qr';

    // File scanner: contenido is the SHA-256 hash computed in the browser, never the file.
    case Archivo = 'archivo';

    /**
     * Types analyzed by RiskAnalyzer from their text, and the ones shown in the public rankings.
     *
     * @return list<self>
     */
    public static function textuales(): array
    {
        return [self::Texto, self::Link, self::Qr];
    }
}
