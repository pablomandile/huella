<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoDesparasitacion: string
{
    use TieneOpciones;

    case Interna = 'interna';
    case Externa = 'externa';
    case Mixta = 'mixta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Interna => 'Interna (lombrices)',
            self::Externa => 'Externa (pulgas y garrapatas)',
            self::Mixta => 'Mixta',
        };
    }
}
