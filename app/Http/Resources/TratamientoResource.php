<?php

namespace App\Http\Resources;

use App\Enums\EstadoToma;
use App\Models\Tratamiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tratamiento
 */
class TratamientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visita_id' => $this->visita_id,
            'medicamento_id' => $this->medicamento_id,
            'medicamento_libre' => $this->medicamento_libre,
            'nombre_medicamento' => $this->nombre_medicamento,
            'dosis' => $this->dosis,
            'via' => $this->via->value,
            'via_etiqueta' => $this->via->etiqueta(),
            'frecuencia_horas' => $this->frecuencia_horas,
            'veces_por_dia' => $this->veces_por_dia,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'duracion_dias' => $this->duracion_dias,
            'hora_primera_toma' => $this->hora_primera_toma
                ? substr($this->hora_primera_toma, 0, 5)
                : null,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'notas' => $this->notas,
            'posologia' => $this->posologia(),
            'adherencia' => $this->adherencia(),
        ];
    }

    /** Una línea con toda la indicación: es lo que se lee de un vistazo. */
    private function posologia(): string
    {
        $partes = [$this->dosis];

        if ($this->frecuencia_horas !== null) {
            $partes[] = $this->frecuencia_horas === 24
                ? 'una vez por día'
                : "cada {$this->frecuencia_horas} h";
        } elseif ($this->veces_por_dia !== null) {
            $partes[] = $this->veces_por_dia === 1
                ? 'una vez por día'
                : "{$this->veces_por_dia} veces por día";
        }

        $partes[] = $this->via->etiqueta();

        if ($this->duracion_dias !== null) {
            $partes[] = "por {$this->duracion_dias} ".($this->duracion_dias === 1 ? 'día' : 'días');
        }

        return implode(' · ', $partes);
    }

    /**
     * Cuánto del tratamiento se cumplió. Es el dato que la especificación pide
     * para ver la adherencia, y sale de las tomas ya cargadas.
     *
     * @return array{total: int, dadas: int, pendientes: int, salteadas: int}|null
     */
    private function adherencia(): ?array
    {
        if (! $this->relationLoaded('tomas')) {
            return null;
        }

        $tomas = $this->tomas;

        return [
            'total' => $tomas->count(),
            'dadas' => $tomas->where('estado', EstadoToma::Administrada)->count(),
            'pendientes' => $tomas->where('estado', EstadoToma::Pendiente)->count(),
            'salteadas' => $tomas->where('estado', EstadoToma::Omitida)->count(),
        ];
    }
}
