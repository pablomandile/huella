<?php

namespace App\Policies;

use App\Contracts\Catalogo;
use App\Models\User;

/**
 * Una sola política para los cinco catálogos: cada modelo la declara con
 * `#[UsePolicy(CatalogoPolicy::class)]`.
 *
 * La regla de negocio 4 vive acá y en un solo lugar: **los registros semilla
 * se ven y se duplican, pero no se editan ni se borran**. Si se pudieran
 * editar, un usuario le cambiaría los meses de refuerzo a la antirrábica y se
 * los cambiaría a todos los demás.
 */
class CatalogoPolicy
{
    public function view(User $usuario, Catalogo $registro): bool
    {
        return $registro->esSemilla() || $registro->perteneceA($usuario);
    }

    public function update(User $usuario, Catalogo $registro): bool
    {
        return $registro->perteneceA($usuario);
    }

    public function delete(User $usuario, Catalogo $registro): bool
    {
        return $registro->perteneceA($usuario);
    }

    /**
     * Se puede duplicar todo lo que se pueda ver. Es la salida que tiene el
     * usuario cuando necesita un semilla con otros valores.
     */
    public function duplicar(User $usuario, Catalogo $registro): bool
    {
        return $this->view($usuario, $registro);
    }
}
