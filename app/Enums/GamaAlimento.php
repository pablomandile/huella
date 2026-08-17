<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum GamaAlimento: string
{
    use TieneOpciones;

    case Estandar = 'estandar';
    case Premium = 'premium';
    case SuperPremium = 'super_premium';
    case Medicado = 'medicado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Estandar => 'Estándar',
            self::Premium => 'Premium',
            self::SuperPremium => 'Súper premium',
            self::Medicado => 'Medicado',
        };
    }
}
