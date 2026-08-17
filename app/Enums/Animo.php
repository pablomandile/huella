<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

/**
 * Cómo estaba de ánimo ese día. Es una observación del dueño, no una medición:
 * sirve para leer una racha en la línea de tiempo.
 */
enum Animo: string
{
    use TieneOpciones;

    case MuyBajo = 'muy_bajo';
    case Bajo = 'bajo';
    case Normal = 'normal';
    case Bueno = 'bueno';
    case Excelente = 'excelente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuyBajo => 'Muy decaída',
            self::Bajo => 'Decaída',
            self::Normal => 'Normal',
            self::Bueno => 'Bien',
            self::Excelente => 'Muy bien',
        };
    }
}
