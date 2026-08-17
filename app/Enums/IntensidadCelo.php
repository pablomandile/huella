<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum IntensidadCelo: string
{
    use TieneOpciones;

    case Leve = 'leve';
    case Normal = 'normal';
    case Intensa = 'intensa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Leve => 'Leve',
            self::Normal => 'Normal',
            self::Intensa => 'Intensa',
        };
    }
}
