<?php

namespace App\Observers;

use App\Enums\RolCuidador;
use App\Enums\TipoRecordatorio;
use App\Models\Mascota;
use App\Services\GeneradorRecordatoriosService;

class MascotaObserver
{
    public function __construct(private readonly GeneradorRecordatoriosService $recordatorios) {}

    /**
     * Al crear una mascota, su dueño entra al pivote como propietario.
     * Es lo que hace que MascotaPolicy funcione sin comparar usuario_id,
     * y lo que deja el multi-cuidador de v2 a una UI de distancia.
     */
    public function created(Mascota $mascota): void
    {
        $mascota->cuidadores()->attach($mascota->usuario_id, [
            'rol' => RolCuidador::Propietario->value,
        ]);

        $this->sincronizarSeguro($mascota);
        $this->sincronizarRabia($mascota);
    }

    /**
     * Dos reglas de negocio viven acá, y las dos son de descarte:
     *
     * - **Regla 2:** al marcar la castración se van los recordatorios de celo.
     *   El módulo ya se oculta por `celo_visible`, pero los recordatorios
     *   pendientes seguirían llegando por mail.
     * - **Regla 3:** una mascota fallecida pasa a modo lectura. Conserva todo su
     *   historial, pero no puede seguir avisando que le toca la antirrábica.
     */
    public function updated(Mascota $mascota): void
    {
        if ($mascota->wasChanged('fecha_fallecimiento') && $mascota->fallecida) {
            $this->recordatorios->descartarDeLaMascota($mascota);

            return; // ya no hay nada que sincronizar
        }

        if ($mascota->wasChanged('castrado') && $mascota->castrado) {
            $this->recordatorios->descartarDeLaMascota($mascota, TipoRecordatorio::Celo);
        }

        if ($mascota->wasChanged('seguro_vencimiento')) {
            $this->sincronizarSeguro($mascota);
        }

        if ($mascota->wasChanged('rabia_vencimiento')) {
            $this->sincronizarRabia($mascota);
        }
    }

    private function sincronizarSeguro(Mascota $mascota): void
    {
        $this->recordatorios->sincronizar(
            origen: $mascota,
            mascota: $mascota,
            tipo: TipoRecordatorio::Seguro,
            fecha: $mascota->seguro_vencimiento,
            titulo: "Vence el seguro de {$mascota->nombre}",
            descripcion: $mascota->seguro_compania,
        );
    }

    /**
     * El vencimiento del certificado de rabia.
     *
     * Convive con el del seguro sobre el mismo origen —la mascota— porque la
     * idempotencia es por `origen_type` + `origen_id` + **`tipo`**: son dos
     * recordatorios distintos de la misma fila, y ninguno pisa al otro.
     *
     * Es un aviso aparte del de la antirrábica: esa la genera
     * `aplicaciones_vacuna.proxima_dosis` y habla de la dosis. Este habla del
     * papel, que es lo que piden en un viaje o en una guardería y puede vencer en
     * otra fecha si el veterinario lo emitió más tarde.
     */
    private function sincronizarRabia(Mascota $mascota): void
    {
        $this->recordatorios->sincronizar(
            origen: $mascota,
            mascota: $mascota,
            tipo: TipoRecordatorio::CertificadoRabia,
            fecha: $mascota->rabia_vencimiento,
            titulo: "Vence el certificado de rabia de {$mascota->nombre}",
            descripcion: 'Hay que renovarlo para viajar o dejarla en una guardería.',
        );
    }
}
