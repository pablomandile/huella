<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

/**
 * Dónde lo bañaron.
 *
 * Se anota porque no es lo mismo: en el salón o en la veterinaria suele venir
 * con corte, limpieza de oídos o un champú medicado, y en casa no.
 */
enum LugarBano: string
{
    use TieneOpciones;

    case Casa = 'casa';
    case Salon = 'salon';
    case Veterinaria = 'veterinaria';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Casa => 'En casa',
            self::Salon => 'En el salón',
            self::Veterinaria => 'En la veterinaria',
        };
    }
}
