<?php

namespace App\Http\Resources;

use App\Models\CicloCelo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CicloCelo
 */
class CicloCeloResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_inicio_legible' => $this->fecha_inicio->translatedFormat('j \d\e F \d\e Y'),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'duracion_dias' => $this->duracion_dias,
            'en_curso' => $this->estaEnCurso(),
            'intensidad' => $this->intensidad?->value,
            'intensidad_etiqueta' => $this->intensidad?->etiqueta(),
            'sintomas' => $this->sintomas,
            'hubo_monta' => $this->hubo_monta,
            'proxima_estimada' => $this->proxima_estimada?->toDateString(),
            'notas' => $this->notas,
        ];
    }
}
