<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum EstadoTratamiento: string
{
    use TieneOpciones;

    case Activo = 'activo';
    case Finalizado = 'finalizado';
    case Suspendido = 'suspendido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'En curso',
            self::Finalizado => 'Terminado',
            self::Suspendido => 'Suspendido',
        };
    }

    /** Un tratamiento que ya no está en curso no genera ni espera tomas. */
    public function estaEnCurso(): bool
    {
        return $this === self::Activo;
    }
}
