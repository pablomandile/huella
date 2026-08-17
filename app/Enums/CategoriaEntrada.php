<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum CategoriaEntrada: string
{
    use TieneOpciones;

    case General = 'general';
    case Sintoma = 'sintoma';
    case Comportamiento = 'comportamiento';
    case Higiene = 'higiene';
    case Paseo = 'paseo';
    case Entrenamiento = 'entrenamiento';
    case Hito = 'hito';
    case Viaje = 'viaje';

    public function etiqueta(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Sintoma => 'Síntoma',
            self::Comportamiento => 'Comportamiento',
            self::Higiene => 'Higiene',
            self::Paseo => 'Paseo',
            self::Entrenamiento => 'Entrenamiento',
            self::Hito => 'Hito',
            self::Viaje => 'Viaje',
        };
    }
}
