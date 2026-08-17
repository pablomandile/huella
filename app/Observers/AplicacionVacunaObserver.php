<?php

namespace App\Observers;

use App\Enums\TipoRecordatorio;
use App\Models\AplicacionVacuna;
use App\Services\GeneradorRecordatoriosService;

/**
 * La próxima dosis de una vacuna genera su recordatorio.
 *
 * Vive en un observer y no en el controlador para que valga siempre: se cargue
 * desde el formulario de la visita, desde la ficha o desde un seeder.
 */
class AplicacionVacunaObserver
{
    public function __construct(private readonly GeneradorRecordatoriosService $recordatorios) {}

    public function saved(AplicacionVacuna $aplicacion): void
    {
        $this->sincronizar($aplicacion);
    }

    public function deleted(AplicacionVacuna $aplicacion): void
    {
        // Si la aplicación se dio de baja, su próxima dosis dejó de existir.
        $this->recordatorios->descartarDe($aplicacion, TipoRecordatorio::Vacuna);
    }

    public function restored(AplicacionVacuna $aplicacion): void
    {
        $this->sincronizar($aplicacion);
    }

    private function sincronizar(AplicacionVacuna $aplicacion): void
    {
        // `mascota_id` es NOT NULL con FK, y al borrar la mascota las
        // aplicaciones se van en cascada sin pasar por acá: siempre está.
        $mascota = $aplicacion->mascota;
        $nombre = $aplicacion->nombre_vacuna;

        $this->recordatorios->sincronizar(
            origen: $aplicacion,
            mascota: $mascota,
            tipo: TipoRecordatorio::Vacuna,
            fecha: $aplicacion->proxima_dosis,
            titulo: "Refuerzo de {$nombre} para {$mascota->nombre}",
            descripcion: "La última dosis fue el {$aplicacion->fecha->format('d/m/Y')}.",
        );
    }
}
