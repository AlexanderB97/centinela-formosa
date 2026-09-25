<?php

namespace App\Enums;

enum TipoContenido: string
{
    case Texto = 'texto';
    case Link = 'link';
    case Qr = 'qr';
}
