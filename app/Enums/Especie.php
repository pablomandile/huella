<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum Especie: string
{
    use TieneOpciones;

    case Perro = 'perro';
    case Gato = 'gato';
    case Ave = 'ave';
    case Roedor = 'roedor';
    case Reptil = 'reptil';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Perro => 'Perro',
            self::Gato => 'Gato',
            self::Ave => 'Ave',
            self::Roedor => 'Roedor',
            self::Reptil => 'Reptil',
            self::Otro => 'Otro',
        };
    }
}
