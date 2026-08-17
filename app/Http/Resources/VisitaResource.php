<?php

namespace App\Http\Resources;

use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Visita
 */
class VisitaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Guardado en UTC, mostrado en el reloj del usuario.
        $local = $request->user()->enSuZona($this->fecha_hora);

        return [
            'id' => $this->id,
            'mascota_id' => $this->mascota_id,
            'fecha_hora' => $local?->toIso8601String(),
            // Ya formateado para el <input datetime-local> de la edición.
            'fecha_hora_local' => $local?->format('Y-m-d\TH:i'),
            'fecha_legible' => $local?->translatedFormat('D j \d\e F \d\e Y, H:i'),
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'motivo' => $this->motivo,
            'diagnostico' => $this->diagnostico,
            'indicaciones' => $this->indicaciones,
            'temperatura' => $this->temperatura,
            'costo' => $this->costo,
            'moneda' => $this->moneda,
            'proximo_control' => $this->proximo_control?->toDateString(),
            'notas' => $this->notas,
            'veterinaria_id' => $this->veterinaria_id,
            'veterinaria_nombre' => $this->whenLoaded(
                'veterinaria',
                fn () => $this->veterinaria?->nombre,
            ),
            'veterinario_id' => $this->veterinario_id,
            'veterinario_nombre' => $this->whenLoaded(
                'veterinario',
                fn () => $this->veterinario?->nombre,
            ),
            // ->resolve() y no solo ::collection(): un Resource anidado sin
            // resolver se serializa con su envoltorio `data`, y el front
            // recibiría `tratamientos.data` en vez de `tratamientos`.
            'tratamientos' => $this->whenLoaded(
                'tratamientos',
                fn () => TratamientoResource::collection($this->tratamientos)->resolve(),
                [],
            ),
            'adjuntos' => $this->whenLoaded(
                'adjuntos',
                fn () => AdjuntoResource::collection($this->adjuntos)->resolve(),
                [],
            ),
        ];
    }
}
