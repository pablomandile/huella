<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum EstadoToma: string
{
    use TieneOpciones;

    case Pendiente = 'pendiente';
    case Administrada = 'administrada';
    case Omitida = 'omitida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Administrada => 'Dada',
            self::Omitida => 'Salteada',
        };
    }
}
