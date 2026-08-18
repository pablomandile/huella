<?php

namespace App\Services;

use App\Enums\EstadoTratamiento;
use App\Models\Mascota;
use Carbon\CarbonImmutable;

/**
 * Junta todo el historial de una mascota para el PDF.
 *
 * El PDF está pensado para una cosa concreta: llevarlo a un veterinario nuevo o
 * a un viaje. Por eso **las alergias van primero**, antes de la ficha: es el
 * dato que puede cambiar una decisión en una urgencia, y quien lo lee no va a
 * hojear tres páginas para encontrarlo.
 *
 * La tabla de alergias faltaba en el esquema original y se agregó justamente
 * porque la especificación §4.14 la exige en la exportación.
 */
class HistoriaClinicaService
{
    /**
     * @param  array{desde?: string|null, hasta?: string|null}  $rango
     * @return array<string, mixed>
     */
    public function para(Mascota $mascota, array $rango = []): array
    {
        $desde = $rango['desde'] ?? null;
        $hasta = $rango['hasta'] ?? null;

        $mascota->load(['propietario', 'alergias']);

        return [
            'mascota' => $mascota,
            'alergias' => $mascota->alergias,
            // Para poder marcar "(VENCIDO)" en la ficha impresa.
            'estadoRabia' => $mascota->estado_rabia,
            'rango' => $this->rangoLegible($desde, $hasta),
            'generado' => $mascota->propietario->ahora(),

            'visitas' => $this->acotar(
                $mascota->visitas()->with(['veterinaria', 'veterinario', 'tratamientos.medicamento']),
                'fecha_hora',
                $desde,
                $hasta,
            )->get(),

            'vacunas' => $this->acotar(
                $mascota->vacunasAplicadas()->with('vacuna'),
                'fecha',
                $desde,
                $hasta,
            )->get(),

            'desparasitaciones' => $this->acotar(
                $mascota->desparasitaciones()->with('medicamento'),
                'fecha',
                $desde,
                $hasta,
            )->get(),

            // En curso primero: es lo que el veterinario necesita saber ya.
            'tratamientos' => $this->acotar(
                $mascota->tratamientos()->with('medicamento'),
                'fecha_inicio',
                $desde,
                $hasta,
            )->get()->sortByDesc(
                fn ($tratamiento) => $tratamiento->estado === EstadoTratamiento::Activo ? 1 : 0,
            )->values(),

            'pesos' => $this->acotar($mascota->pesos(), 'fecha', $desde, $hasta)->get(),
            'dietas' => $this->acotar(
                $mascota->dietas()->with('alimento'),
                'fecha_inicio',
                $desde,
                $hasta,
            )->get(),
            'ciclos' => $mascota->celo_visible
                ? $this->acotar($mascota->ciclosCelo(), 'fecha_inicio', $desde, $hasta)->get()
                : collect(),
            'entradas' => $this->acotar(
                $mascota->entradasDiario(),
                'fecha',
                $desde,
                $hasta,
            )->get(),
        ];
    }

    /**
     * Nombre de archivo previsible: se descargan varios y hay que distinguirlos.
     */
    public function nombreDeArchivo(Mascota $mascota): string
    {
        $nombre = preg_replace('/[^a-z0-9]+/i', '-', $mascota->nombre) ?? 'mascota';

        return sprintf(
            'historia-clinica-%s-%s.pdf',
            mb_strtolower(trim($nombre, '-')),
            CarbonImmutable::now()->toDateString(),
        );
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Relations\Relation
     *
     * @param  T  $consulta
     * @return T
     */
    private function acotar(
        mixed $consulta,
        string $columna,
        ?string $desde,
        ?string $hasta,
    ): mixed {
        if ($desde !== null && $desde !== '') {
            $consulta->whereDate($columna, '>=', $desde);
        }

        if ($hasta !== null && $hasta !== '') {
            $consulta->whereDate($columna, '<=', $hasta);
        }

        return $consulta;
    }

    private function rangoLegible(?string $desde, ?string $hasta): string
    {
        if (! $desde && ! $hasta) {
            return 'Historial completo';
        }

        $formato = fn (?string $fecha) => $fecha
            ? CarbonImmutable::parse($fecha)->translatedFormat('j \d\e F \d\e Y')
            : null;

        return match (true) {
            $desde && $hasta => sprintf('Del %s al %s', $formato($desde), $formato($hasta)),
            (bool) $desde => 'Desde el '.$formato($desde),
            default => 'Hasta el '.$formato($hasta),
        };
    }
}
