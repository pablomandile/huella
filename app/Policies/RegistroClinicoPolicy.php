<?php

namespace App\Policies;

use App\Contracts\PerteneceAMascota;
use App\Models\User;

/**
 * Autorización de visitas, tratamientos, tomas y adjuntos.
 *
 * Ninguno decide por su cuenta: **todos preguntan por su mascota**, y la
 * mascota responde por el pivote `mascota_usuario`. Eso concentra la regla en
 * un solo lugar y hace que el multi-cuidador de v2 alcance a todo el historial
 * clínico sin tocar nada más.
 *
 * En los adjuntos la cadena es más larga —adjunto → visita → mascota— y esa es
 * justamente la razón de que la resuelva el modelo y no la Policy.
 */
class RegistroClinicoPolicy
{
    public function view(User $usuario, PerteneceAMascota $registro): bool
    {
        $mascota = $registro->mascotaAsociada();

        return $mascota !== null && $usuario->can('view', $mascota);
    }

    /**
     * Editar y borrar exigen lo mismo que cargar: una mascota fallecida pasa a
     * modo lectura y su historial no se retoca.
     */
    public function update(User $usuario, PerteneceAMascota $registro): bool
    {
        $mascota = $registro->mascotaAsociada();

        return $mascota !== null && $usuario->can('registrarEventos', $mascota);
    }

    public function delete(User $usuario, PerteneceAMascota $registro): bool
    {
        return $this->update($usuario, $registro);
    }
}
