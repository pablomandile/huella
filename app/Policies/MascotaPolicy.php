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

    private function rol(User $usuario, Mascota $mascota): ?RolCuidador
    {
        return $mascota->rolDe($usuario);
    }
}
