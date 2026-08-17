<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoVisita: string
{
    use TieneOpciones;

    case Rutina = 'rutina';
    case Control = 'control';
    case Urgencia = 'urgencia';
    case Cirugia = 'cirugia';
    case Vacunacion = 'vacunacion';
    case Estudios = 'estudios';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Rutina => 'Rutina',
            self::Control => 'Control',
            self::Urgencia => 'Urgencia',
            self::Cirugia => 'Cirugía',
            self::Vacunacion => 'Vacunación',
            self::Estudios => 'Estudios',
            self::Otro => 'Otra',
        };
    }
}
