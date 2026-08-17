<?php

namespace App\Observers;

use App\Enums\TipoRecordatorio;
use App\Models\Desparasitacion;
use App\Services\GeneradorRecordatoriosService;

class DesparasitacionObserver
{
    public function __construct(private readonly GeneradorRecordatoriosService $recordatorios) {}

    public function saved(Desparasitacion $desparasitacion): void
    {
        $this->sincronizar($desparasitacion);
    }

    public function deleted(Desparasitacion $desparasitacion): void
    {
        $this->recordatorios->descartarDe($desparasitacion, TipoRecordatorio::Desparasitacion);
    }

    public function restored(Desparasitacion $desparasitacion): void
    {
        $this->sincronizar($desparasitacion);
    }

    private function sincronizar(Desparasitacion $desparasitacion): void
    {
        $mascota = $desparasitacion->mascota;

        $this->recordatorios->sincronizar(
            origen: $desparasitacion,
            mascota: $mascota,
            tipo: TipoRecordatorio::Desparasitacion,
            fecha: $desparasitacion->proxima_fecha,
            titulo: "Desparasitar a {$mascota->nombre}",
            descripcion: sprintf(
                'La última fue el %s (%s).',
                $desparasitacion->fecha->format('d/m/Y'),
                $desparasitacion->tipo->etiqueta(),
            ),
        );
    }
}
