<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum Sexo: string
{
    use TieneOpciones;

    case Macho = 'macho';
    case Hembra = 'hembra';
    case Desconocido = 'desconocido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Macho => 'Macho',
            self::Hembra => 'Hembra',
            self::Desconocido => 'No sé',
        };
    }
}
