<?php

namespace App\Services;

use App\Models\AplicacionVacuna;
use App\Models\Mascota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * El semáforo de vacunación de la ficha: al día, próxima a vencer o vencida.
 *
 * Solo mira las fechas que el usuario cargó. **No decide qué vacunas le
 * corresponden a una mascota** —eso sería una recomendación clínica, y la regla
 * de negocio 7 dice que el sistema registra, no aconseja—. Si nunca se cargó
 * una vacuna, la respuesta es "sin datos", no "está mal vacunada".
 */
class EstadoVacunacionService
{
    /** Dentro de este plazo, "próxima a vencer". */
    private const DIAS_DE_AVISO = 30;

    /**
     * @return array{estado: string, etiqueta: string, detalle: string|null}
     */
    public function para(Mascota $mascota): array
    {
        $aplicaciones = $mascota->vacunasAplicadas()
            ->whereNotNull('proxima_dosis')
            ->with('vacuna')
            ->get();

        if ($aplicaciones->isEmpty()) {
            return $this->resultado(
                'sin_datos',
                'Sin datos',
                $mascota->vacunasAplicadas()->exists()
                    ? 'Ninguna vacuna cargada tiene próxima dosis anotada.'
                    : 'Todavía no cargaste ninguna vacuna.',
            );
        }

        // Fecha contra fecha: proxima_dosis es una columna `date`.
        $hoy = $mascota->propietario->hoyCalendario();

        $vencidas = $aplicaciones->filter(
            fn (AplicacionVacuna $a) => $this->dia($a)->lessThan($hoy),
        );

        if ($vencidas->isNotEmpty()) {
            return $this->resultado(
                'vencida',
                'Vencida',
                $this->listar($vencidas, 'venció el'),
            );
        }

        $proximas = $aplicaciones->filter(
            fn (AplicacionVacuna $a) => $this->dia($a)->lessThanOrEqualTo(
                $hoy->addDays(self::DIAS_DE_AVISO),
            ),
        );

        if ($proximas->isNotEmpty()) {
            return $this->resultado(
                'proxima',
                'Próxima a vencer',
                $this->listar($proximas, 'toca el'),
            );
        }

        $siguiente = $aplicaciones->sortBy(fn (AplicacionVacuna $a) => $this->dia($a))->first();

        return $this->resultado(
            'al_dia',
            'Al día',
            sprintf(
                'La próxima es %s, el %s.',
                $siguiente->nombre_vacuna,
                $this->dia($siguiente)->format('d/m/Y'),
            ),
        );
    }

    private function dia(AplicacionVacuna $aplicacion): CarbonImmutable
    {
        return $aplicacion->proxima_dosis->toImmutable()->startOfDay();
    }

    /**
     * @param  Collection<int, AplicacionVacuna>  $aplicaciones
     */
    private function listar($aplicaciones, string $conector): string
    {
        return $aplicaciones
            ->take(3)
            ->map(fn (AplicacionVacuna $a) => sprintf(
                '%s %s %s',
                $a->nombre_vacuna,
                $conector,
                $this->dia($a)->format('d/m/Y'),
            ))
            ->implode('. ').'.';
    }

    /**
     * @return array{estado: string, etiqueta: string, detalle: string|null}
     */
    private function resultado(string $estado, string $etiqueta, ?string $detalle): array
    {
        return ['estado' => $estado, 'etiqueta' => $etiqueta, 'detalle' => $detalle];
    }
}
