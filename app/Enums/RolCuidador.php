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

    /**
     * Los roles que el dueño puede conceder al compartir.
     *
     * `Propietario` queda afuera y no es negociable: no se regala la propiedad
     * de una ficha por un formulario. Es una **lista blanca** y no un
     * `cases()` menos uno, para que un rol nuevo del enum nazca prohibido y
     * haya que habilitarlo a mano.
     *
     * @return list<self>
     */
    public static function invitables(): array
    {
        return [self::Lector, self::Cuidador];
    }

    /**
     * Las opciones del selector, en orden de menor a mayor permiso: lo que se
     * elige por descuido es lo primero, y acá lo primero es lo que menos puede.
     *
     * @return list<array{value: string, label: string, descripcion: string}>
     */
    public static function opcionesParaInvitar(): array
    {
        return array_map(fn (self $rol) => [
            'value' => $rol->value,
            'label' => $rol->etiqueta(),
            'descripcion' => match ($rol) {
                self::Lector => 'Ve todo el historial. No puede cargar ni modificar nada.',
                self::Cuidador => 'Además puede registrar tomas, pesos, visitas y notas.',
                self::Propietario => '',
            },
        ], self::invitables());
    }
}
