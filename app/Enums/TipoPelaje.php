<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoPelaje: string
{
    use TieneOpciones;

    case Corto = 'corto';
    case Medio = 'medio';
    case Largo = 'largo';
    case Rizado = 'rizado';
    case Duro = 'duro';
    case SinPelo = 'sin_pelo';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Corto => 'Corto',
            self::Medio => 'Medio',
            self::Largo => 'Largo',
            self::Rizado => 'Rizado',
            self::Duro => 'Duro',
            self::SinPelo => 'Sin pelo',
            self::Otro => 'Otro',
        };
    }
}
