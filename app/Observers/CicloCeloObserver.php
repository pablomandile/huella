<?php

namespace App\Observers;

use App\Enums\TipoRecordatorio;
use App\Models\CicloCelo;
use App\Services\EstimadorCeloService;
use App\Services\GeneradorRecordatoriosService;

/**
 * Al guardar un ciclo se recalcula la estimación del próximo y se sincroniza su
 * recordatorio.
 *
 * Se recalcula sobre **el último ciclo de la mascota**, no sobre el que se
 * acaba de guardar: cargar un ciclo viejo que faltaba mejora el promedio, y la
 * estimación tiene que salir siempre del más reciente.
 */
class CicloCeloObserver
{
    public function __construct(
        private readonly EstimadorCeloService $estimador,
        private readonly GeneradorRecordatoriosService $recordatorios,
    ) {}

    public function saved(CicloCelo $ciclo): void
    {
        $this->recalcular($ciclo);
    }

    public function deleted(CicloCelo $ciclo): void
    {
        $this->recordatorios->descartarDe($ciclo, TipoRecordatorio::Celo);
        $this->recalcular($ciclo);
    }

    public function restored(CicloCelo $ciclo): void
    {
        $this->recalcular($ciclo);
    }

    private function recalcular(CicloCelo $ciclo): void
    {
        $mascota = $ciclo->mascota;

        // Regla de negocio 2: si está castrada no hay celo que estimar. El
        // módulo ya se oculta por `celo_visible`; acá se corta la generación.
        if (! $mascota->celo_visible) {
            $this->recordatorios->descartarDeLaMascota($mascota, TipoRecordatorio::Celo);

            return;
        }

        $this->guardarDuracion($ciclo);

        $ultimo = $mascota->ciclosCelo()->orderByDesc('fecha_inicio')->first();

        if ($ultimo === null) {
            return;
        }

        $estimacion = $this->estimador->para($mascota);

        // La estimación es una por mascota, no una por ciclo: los avisos que
        // colgaban de un ciclo anterior ya no valen.
        $this->recordatorios->dejarSoloEste($mascota, TipoRecordatorio::Celo, $ultimo);

        // La estimación se guarda en el ciclo más reciente, que es el que la
        // explica: se calcula desde su fecha de inicio.
        $ultimo->forceFill(['proxima_estimada' => $estimacion['fecha']?->toDateString()])
            ->saveQuietly();

        $this->recordatorios->sincronizar(
            origen: $ultimo,
            mascota: $mascota,
            tipo: TipoRecordatorio::Celo,
            fecha: $estimacion['fecha'],
            titulo: "Se estima el próximo celo de {$mascota->nombre}",
            // El nivel de confianza viaja con la estimación: una fecha sin
            // contexto se lee como un dato, y esto es un promedio.
            descripcion: $estimacion['detalle'],
        );
    }

    /**
     * `duracion_dias` la calcula el sistema al cerrar el ciclo, así que no es
     * fillable y se escribe sin volver a disparar el observer.
     */
    private function guardarDuracion(CicloCelo $ciclo): void
    {
        $dias = $ciclo->diasDeDuracion();

        if ($ciclo->duracion_dias !== $dias) {
            $ciclo->forceFill(['duracion_dias' => $dias])->saveQuietly();
        }
    }
}
