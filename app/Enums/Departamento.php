<?php

namespace App\Enums;

/**
 * Department of Formosa where a reported message was received. Optional context of the
 * message, never data about the visitor.
 */
enum Departamento: string
{
    case FormosaCapital = 'formosa_capital';
    case Pilcomayo = 'pilcomayo';
    case Laishi = 'laishi';
    case Pirane = 'pirane';
    case Patino = 'patino';
    case Pilagas = 'pilagas';
    case Bermejo = 'bermejo';
    case Matacos = 'matacos';
    case RamonLista = 'ramon_lista';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FormosaCapital => 'Formosa Capital',
            self::Pilcomayo => 'Pilcomayo',
            self::Laishi => 'Laishí',
            self::Pirane => 'Pirané',
            self::Patino => 'Patiño',
            self::Pilagas => 'Pilagás',
            self::Bermejo => 'Bermejo',
            self::Matacos => 'Matacos',
            self::RamonLista => 'Ramón Lista',
        };
    }
}
