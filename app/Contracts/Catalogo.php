<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Lo que `CatalogoPolicy` y `CatalogoBaseController` necesitan saber de
 * cualquier catálogo. La implementa el trait `App\Models\Concerns\EsCatalogo`.
 */
interface Catalogo
{
    /** Registro precargado del sistema (`usuario_id` NULL). */
    public function esSemilla(): bool;

    public function perteneceA(User $usuario): bool;

    /** Pasa el registro a nombre del usuario: alta propia o copia de un semilla. */
    public function asignarPropietario(User $usuario): void;
}
