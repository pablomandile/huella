<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoAlimento: string
{
    use TieneOpciones;

    case BalanceadoSeco = 'balanceado_seco';
    case Humedo = 'humedo';
    case Casero = 'casero';
    case Barf = 'barf';
    case Snack = 'snack';
    case Suplemento = 'suplemento';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BalanceadoSeco => 'Balanceado seco',
            self::Humedo => 'Húmedo (lata o sachet)',
            self::Casero => 'Casero',
            self::Barf => 'BARF',
            self::Snack => 'Snack o premio',
            self::Suplemento => 'Suplemento',
            self::Otro => 'Otro',
        };
    }
}
