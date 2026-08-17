<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

/**
 * De dónde salió el peso.
 *
 * Importa más de lo que parece: la balanza de la veterinaria y la de casa no
 * coinciden, así que en la curva de evolución los dos orígenes se dibujan
 * distinto para no leer como variación real lo que es diferencia de balanza.
 */
enum OrigenPeso: string
{
    use TieneOpciones;

    case Casa = 'casa';
    case Veterinaria = 'veterinaria';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Casa => 'En casa',
            self::Veterinaria => 'En la veterinaria',
        };
    }
}
