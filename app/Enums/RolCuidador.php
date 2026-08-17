<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

/**
 * Rol de un usuario sobre una mascota en el pivote `mascota_usuario`.
 *
 * En v1 solo existe Propietario (lo asigna el observer al crear la mascota).
 * Cuidador y Lector quedan definidos para el multi-cuidador de v2: la Policy
 * ya distingue por rol, así que sumar la UI de invitaciones no toca permisos.
 */
enum RolCuidador: string
{
    use TieneOpciones;

    case Propietario = 'propietario';
    case Cuidador = 'cuidador';
    case Lector = 'lector';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propietario => 'Propietario',
            self::Cuidador => 'Cuidador',
            self::Lector => 'Solo lectura',
        };
    }

    public function puedeEditar(): bool
    {
        return match ($this) {
            self::Propietario, self::Cuidador => true,
            self::Lector => false,
        };
    }
}
