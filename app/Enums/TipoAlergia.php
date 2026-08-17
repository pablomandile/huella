<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoAlergia: string
{
    use TieneOpciones;

    case Alimentaria = 'alimentaria';
    case Medicamentosa = 'medicamentosa';
    case Ambiental = 'ambiental';
    case Picadura = 'picadura';
    case Otra = 'otra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Alimentaria => 'Alimentaria',
            self::Medicamentosa => 'A un medicamento',
            self::Ambiental => 'Ambiental',
            self::Picadura => 'A picaduras',
            self::Otra => 'Otra',
        };
    }
}
