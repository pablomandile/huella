<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum EtapaVida: string
{
    use TieneOpciones;

    case Cachorro = 'cachorro';
    case Adulto = 'adulto';
    case Senior = 'senior';
    case Todas = 'todas';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cachorro => 'Cachorro',
            self::Adulto => 'Adulto',
            self::Senior => 'Senior',
            self::Todas => 'Todas las etapas',
        };
    }
}
