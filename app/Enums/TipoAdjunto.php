<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoAdjunto: string
{
    use TieneOpciones;

    case Receta = 'receta';
    case Analisis = 'analisis';
    case Radiografia = 'radiografia';
    case Ecografia = 'ecografia';
    case Certificado = 'certificado';
    case Factura = 'factura';
    case Foto = 'foto';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Receta => 'Receta',
            self::Analisis => 'Análisis',
            self::Radiografia => 'Radiografía',
            self::Ecografia => 'Ecografía',
            self::Certificado => 'Certificado',
            self::Factura => 'Factura',
            self::Foto => 'Foto',
            self::Otro => 'Otro',
        };
    }
}
