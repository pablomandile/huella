<?php

namespace App\Policies;

use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\User;

/**
 * Toda la autorización pasa por el pivote `mascota_usuario`, nunca por
 * comparar `mascotas.usuario_id`. Así el multi-cuidador de v2 no reescribe
 * ninguna Policy: solo agrega filas con otros roles.
 */
class MascotaPolicy
{
    public function view(User $usuario, Mascota $mascota): bool
    {
        return $this->rol($usuario, $mascota) !== null;
    }

    public function update(User $usuario, Mascota $mascota): bool
    {
        return $this->rol($usuario, $mascota)?->puedeEditar() ?? false;
    }

    public function delete(User $usuario, Mascota $mascota): bool
    {
        // Dar de baja la mascota es solo del propietario.
        return $this->rol($usuario, $mascota) === RolCuidador::Propietario;
    }

    /**
     * Cargar eventos nuevos (fotos, alergias y, en fases siguientes, visitas,
     * pesos, etc.). Una mascota fallecida pasa a modo lectura: conserva todo
     * su historial pero no recibe registros nuevos.
     */
    public function registrarEventos(User $usuario, Mascota $mascota): bool
    {
        if ($mascota->fallecida) {
            return false;
        }

        return $this->rol($usuario, $mascota)?->puedeEditar() ?? false;
    }

    /**
     * Compartir la ficha: invitar a alguien, crear un enlace público, y revocar
     * cualquiera de los dos.
     *
     * Va por `Propietario` y **no** por `registrarEventos`, al revés que la
     * documentación de la mascota, por dos razones:
     *
     * - `registrarEventos` devuelve `false` para una mascota fallecida (regla 3),
     *   y la ficha de una mascota que se murió es justo una de las que uno quiere
     *   poder mandar. Compartir no escribe nada en el historial.
     * - Con `puedeEditar()`, el Cuidador de v2 podría re-compartir una mascota que
     *   no es suya. Dar acceso a terceros es del dueño y de nadie más.
     */
    public function compartir(User $usuario, Mascota $mascota): bool
    {
        return $this->rol($usuario, $mascota) === RolCuidador::Propietario;
    }

    /**
     * Quitarle el acceso a alguien: lo hace el propietario, o el propio invitado
     * que se quiere ir. Al propietario no lo saca nadie, ni él mismo: dejaría la
     * mascota sin dueño y sin nadie que pueda volver a entrar.
     */
    public function revocarAcceso(User $actor, Mascota $mascota, User $objetivo): bool
    {
        if ($mascota->rolDe($objetivo) === RolCuidador::Propietario) {
            return false;
        }

        return $this->rol($actor, $mascota) === RolCuidador::Propietario
            || $actor->is($objetivo);
    }

    private function rol(User $usuario, Mascota $mascota): ?RolCuidador
    {
        return $mascota->rolDe($usuario);
    }
}
