<?php

namespace App\Observers;

use App\Enums\TipoRecordatorio;
use App\Models\Visita;
use App\Services\GeneradorRecordatoriosService;

/**
 * El próximo control que quedó pactado en la consulta.
 */
class VisitaObserver
{
    public function __construct(private readonly GeneradorRecordatoriosService $recordatorios) {}

    public function saved(Visita $visita): void
    {
        $this->sincronizar($visita);
    }

    public function deleted(Visita $visita): void
    {
        $this->recordatorios->descartarDe($visita, TipoRecordatorio::Control);
    }

    public function restored(Visita $visita): void
    {
        $this->sincronizar($visita);
    }

    private function sincronizar(Visita $visita): void
    {
        $mascota = $visita->mascota;
        $motivo = $visita->motivo !== null ? " por {$visita->motivo}" : '';

        $this->recordatorios->sincronizar(
            origen: $visita,
            mascota: $mascota,
            tipo: TipoRecordatorio::Control,
            fecha: $visita->proximo_control,
            titulo: "Control de {$mascota->nombre}",
            descripcion: sprintf(
                'Quedó pactado en la visita del %s%s.',
                $visita->fecha_hora->format('d/m/Y'),
                $motivo,
            ),
        );
    }
}
