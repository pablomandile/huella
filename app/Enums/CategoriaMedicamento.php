<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum CategoriaMedicamento: string
{
    use TieneOpciones;

    case Antibiotico = 'antibiotico';
    case AntiparasitarioInterno = 'antiparasitario_interno';
    case AntiparasitarioExterno = 'antiparasitario_externo';
    case Antiinflamatorio = 'antiinflamatorio';
    case Analgesico = 'analgesico';
    case Suplemento = 'suplemento';
    case Dermatologico = 'dermatologico';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Antibiotico => 'Antibiótico',
            self::AntiparasitarioInterno => 'Antiparasitario interno',
            self::AntiparasitarioExterno => 'Antiparasitario externo',
            self::Antiinflamatorio => 'Antiinflamatorio',
            self::Analgesico => 'Analgésico',
            self::Suplemento => 'Suplemento',
            self::Dermatologico => 'Dermatológico',
            self::Otro => 'Otro',
        };
    }
}
