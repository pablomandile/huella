<?php

namespace App\Http\Resources;

use App\Models\Recordatorio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recordatorio
 */
class RecordatorioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Fecha contra fecha: fecha_objetivo es una columna `date`.
        $hoy = $request->user()->hoyCalendario();
        $objetivo = $this->fecha_objetivo->startOfDay();
        $dias = (int) $hoy->diffInDays($objetivo, absolute: false);

        return [
            'id' => $this->id,
            'mascota_id' => $this->mascota_id,
            'mascota_nombre' => $this->whenLoaded('mascota', fn () => $this->mascota->nombre),
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'es_automatico' => $this->tipo->esAutomatico(),
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'fecha_objetivo' => $objetivo->toDateString(),
            'fecha_legible' => $objetivo->translatedFormat('j \d\e F \d\e Y'),
            'dias_restantes' => $dias,
            'cuando' => $this->cuando($dias),
            'vencido' => $dias < 0,
            'dias_anticipacion' => $this->dias_anticipacion,
            'hora_notificacion' => substr($this->hora_notificacion, 0, 5),
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'recurrente' => $this->recurrente,
            'intervalo_dias' => $this->intervalo_dias,
        ];
    }

    /** En cuánto es, dicho como lo diría una persona. */
    private function cuando(int $dias): string
    {
        return match (true) {
            $dias < -1 => 'hace '.abs($dias).' días',
            $dias === -1 => 'era ayer',
            $dias === 0 => 'es hoy',
            $dias === 1 => 'es mañana',
            $dias < 7 => "en {$dias} días",
            $dias < 30 => 'en '.(int) round($dias / 7).' semanas',
            default => 'en '.(int) round($dias / 30).' meses',
        };
    }
}
