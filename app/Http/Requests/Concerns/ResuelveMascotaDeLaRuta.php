<?php

namespace App\Http\Requests\Concerns;

use App\Contracts\PerteneceAMascota;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Model;

/**
 * El id de la mascota de la ruta, para las reglas que necesitan acotar por ella.
 *
 * Existe porque `$this->route('mascota')` **no siempre devuelve el modelo**:
 * según cuándo se evalúe, puede llegar todavía como el id crudo de la URL. Con
 * un `instanceof` a secas y un fallback a cero, la regla queda comparando contra
 * `mascota_id = 0`, no encuentra nada y **pasa siempre**. Eso convertía una
 * validación de propiedad en un colador silencioso: se podía referenciar la
 * visita de otra mascota.
 */
trait ResuelveMascotaDeLaRuta
{
    /**
     * @param  string  $parametroDelRegistro  nombre del parámetro que en la
     *                                        edición trae el registro (por
     *                                        ejemplo `peso` o `aplicacion`)
     */
    protected function mascotaIdDeLaRuta(string $parametroDelRegistro): int
    {
        $mascota = $this->route('mascota');

        if ($mascota instanceof Mascota) {
            return $mascota->id;
        }

        // Al editar, la ruta puede no traer la mascota: sale del registro.
        $registro = $this->route($parametroDelRegistro);

        if ($registro instanceof Model && $registro instanceof PerteneceAMascota) {
            return (int) $registro->getAttribute('mascota_id');
        }

        // Y si el binding todavía no corrió, el parámetro es el id de la URL.
        return is_scalar($mascota) ? (int) $mascota : 0;
    }
}
