<?php

namespace App\Enums;

/**
 * Channel through which a reported message arrived. Optional context of the message,
 * never data about the visitor.
 */
enum MedioRecepcion: string
{
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Sms = 'sms';
    case RedesSociales = 'redes_sociales';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::RedesSociales => 'Redes sociales',
        };
    }
}
