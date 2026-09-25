<?php

namespace App\Enums;

enum NivelRiesgo: string
{
    case Seguro = 'seguro';
    case Dudoso = 'dudoso';
    case Riesgo = 'riesgo';

    /**
     * Fixed explanation used when the AI explanation is unavailable.
     */
    public function explicacionDeRespaldo(): string
    {
        return match ($this) {
            self::Seguro => 'No detectamos señales de riesgo en este contenido. Igual, nunca compartas claves, códigos de verificación ni datos de tus tarjetas, aunque te los pidan por mensaje.',
            self::Dudoso => 'Encontramos algunas señales que piden precaución. Antes de responder o abrir el enlace, confirmá por un canal oficial que el mensaje sea real.',
            self::Riesgo => 'Este contenido tiene señales claras de estafa. No respondas, no abras los enlaces y no compartas datos personales. Si dice venir de una empresa o banco, comunicate por sus canales oficiales.',
        };
    }
}
