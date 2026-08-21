<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

/**
 * Cuánto dura un enlace compartido.
 *
 * **`Siempre` existe y hay que entender qué se paga.** El modo de falla
 * dominante de un enlace público no es que alguien lo adivine: es que se reenvíe
 * por WhatsApp y quede vivo en un grupo. El vencimiento es la única defensa que
 * funciona sin que el dueño haga nada, y uno sin vencimiento es, en los hechos,
 * un perfil permanente de la mascota para cualquiera que tenga la URL.
 *
 * Lo que lo hace aceptable —y no es poco— es que este enlace **se revoca**: para
 * eso tiene tabla propia en vez de ser una URL firmada. La defensa deja de ser
 * el reloj y pasa a ser el dueño, que lo puede matar desde la ficha en cualquier
 * momento y ve cuántas veces se abrió. Por eso `porDefecto()` sigue siendo un
 * mes: el que no vence se elige, no se cae en él por descuido.
 *
 * Es un enum de PHP y **no** una columna ENUM de MySQL: lo que se persiste es la
 * fecha ya calculada, o NULL. Sumar un caso acá no obliga a ensanchar nada.
 */
enum VigenciaEnlace: string
{
    use TieneOpciones;

    case UnaSemana = '7';
    case UnMes = '30';
    case TresMeses = '90';

    /*
     * El valor no es un número de días como los otros y no puede serlo: '0'
     * pasaría por `(int)` sin ruido y un enlace que vence hoy se leería como uno
     * que no vence nunca. Con una palabra, `dias()` está obligado a decidir.
     */
    case Siempre = 'siempre';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UnaSemana => 'Una semana',
            self::UnMes => 'Un mes',
            self::TresMeses => 'Tres meses',
            self::Siempre => 'Sin vencimiento',
        };
    }

    /** Null es "no vence": lo que se persiste en `expira_en` es NULL. */
    public function dias(): ?int
    {
        return $this === self::Siempre ? null : (int) $this->value;
    }

    /** Un mes: alcanza para una consulta y no tanto como para olvidarse. */
    public static function porDefecto(): self
    {
        return self::UnMes;
    }
}
