<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum ViaAdministracion: string
{
    use TieneOpciones;

    case Oral = 'oral';
    case Topica = 'topica';
    case Inyectable = 'inyectable';
    case Oftalmica = 'oftalmica';
    case Otica = 'otica';
    case Rectal = 'rectal';
    case Otra = 'otra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Oral => 'Oral',
            self::Topica => 'Tópica (sobre la piel)',
            self::Inyectable => 'Inyectable',
            self::Oftalmica => 'Oftálmica (en el ojo)',
            self::Otica => 'Ótica (en el oído)',
            self::Rectal => 'Rectal',
            self::Otra => 'Otra',
        };
    }
}
