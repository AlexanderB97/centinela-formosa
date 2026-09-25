<?php

namespace App\Enums;

enum EstadoReporte: string
{
    case Pendiente = 'pendiente';
    case Confirmado = 'confirmado';
    case Descartado = 'descartado';
}
