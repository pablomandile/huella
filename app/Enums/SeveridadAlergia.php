<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum SeveridadAlergia: string
{
    use TieneOpciones;

    case Leve = 'leve';
    case Moderada = 'moderada';
    case Severa = 'severa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Leve => 'Leve',
            self::Moderada => 'Moderada',
            self::Severa => 'Severa',
        };
    }
}
