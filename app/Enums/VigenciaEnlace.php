<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

/**
 * Cuánto dura un enlace compartido.
 *
 * **No hay opción "no vence", y es a propósito.** El modo de falla dominante de
 * un enlace público no es que alguien lo adivine: es que se reenvíe por WhatsApp
 * y quede vivo en un grupo. El vencimiento es la única defensa que funciona sin
 * que el dueño haga nada, y un enlace sin vencimiento es, en los hechos, un
 * perfil público permanente —justo lo que la especificación deja fuera de
 * alcance—. Si hace falta más tiempo, se crea otro.
 *
 * Es un enum de PHP y **no** una columna ENUM de MySQL: lo que se persiste es la
 * fecha ya calculada. Sumar un caso acá no obliga a ensanchar ninguna columna.
 */
enum VigenciaEnlace: string
{
    use TieneOpciones;

    case UnaSemana = '7';
    case UnMes = '30';
    case TresMeses = '90';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UnaSemana => 'Una semana',
            self::UnMes => 'Un mes',
            self::TresMeses => 'Tres meses',
        };
    }

    public function dias(): int
    {
        return (int) $this->value;
    }

    /** Un mes: alcanza para una consulta y no tanto como para olvidarse. */
    public static function porDefecto(): self
    {
        return self::UnMes;
    }
}
