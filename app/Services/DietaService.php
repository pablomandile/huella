<?php

namespace App\Services;

use App\Models\Dieta;
use App\Models\Mascota;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cambios de dieta.
 *
 * Regla de negocio 1: **una sola dieta vigente por mascota**. Al empezar una
 * nueva se cierra la anterior con la fecha del día anterior, y las dos cosas
 * pasan dentro de una transacción: si quedaran dos vigentes, el dashboard no
 * podría decir qué come la mascota, y si no quedara ninguna se perdería el dato.
 *
 * La base no puede garantizarlo sola: MySQL admite múltiples NULL en un índice
 * único, así que la única defensa es esta.
 */
class DietaService
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function iniciar(Mascota $mascota, array $datos): Dieta
    {
        return DB::transaction(function () use ($mascota, $datos) {
            // Bloqueo pesimista: dos envíos simultáneos del mismo formulario
            // —el doble tap del celular— dejarían dos vigentes.
            $vigentes = $mascota->dietas()
                ->vigente()
                ->lockForUpdate()
                ->get();

            $nueva = $mascota->dietas()->create($datos);

            foreach ($vigentes as $anterior) {
                $this->cerrar($anterior, $nueva);
            }

            return $nueva;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Dieta $dieta, array $datos): Dieta
    {
        return DB::transaction(function () use ($dieta, $datos) {
            $dieta->update($datos);

            // Si la edición la volvió a dejar vigente, las otras se cierran:
            // sigue habiendo una sola.
            if ($dieta->fresh()->estaVigente()) {
                $otras = $dieta->mascota->dietas()
                    ->vigente()
                    ->whereKeyNot($dieta->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($otras as $otra) {
                    $this->cerrar($otra, $dieta);
                }
            }

            return $dieta;
        });
    }

    /**
     * Cierra la dieta anterior el día antes de que empiece la nueva.
     *
     * Si la nueva arranca el mismo día que arrancó la anterior, cerrarla "el día
     * anterior" le daría una fecha de fin previa a su inicio. En ese caso se
     * cierra el mismo día: duró un día.
     */
    private function cerrar(Dieta $anterior, Dieta $nueva): void
    {
        if ($anterior->id === $nueva->id) {
            return;
        }

        $cierre = $nueva->fecha_inicio->toImmutable()->subDay();

        if ($cierre->lessThan($anterior->fecha_inicio)) {
            $cierre = $anterior->fecha_inicio->toImmutable();
        }

        $anterior->update(['fecha_fin' => $cierre->toDateString()]);
    }

    /**
     * Red de seguridad para los tests y para cualquier reparación futura: si
     * alguna vez quedaran dos vigentes, esto lo grita en vez de dejarlo pasar.
     */
    public function verificarUnicaVigente(Mascota $mascota): void
    {
        $vigentes = $mascota->dietas()->vigente()->count();

        if ($vigentes > 1) {
            throw new RuntimeException(
                "La mascota {$mascota->id} quedó con {$vigentes} dietas vigentes.",
            );
        }
    }
}
