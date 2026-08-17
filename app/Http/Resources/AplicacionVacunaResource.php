<?php

namespace App\Http\Resources;

use App\Models\AplicacionVacuna;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AplicacionVacuna
 */
class AplicacionVacunaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vacuna_id' => $this->vacuna_id,
            'vacuna_libre' => $this->vacuna_libre,
            'nombre_vacuna' => $this->nombre_vacuna,
            'visita_id' => $this->visita_id,
            'veterinaria_id' => $this->veterinaria_id,
            'veterinario_id' => $this->veterinario_id,
            'fecha' => $this->fecha->toDateString(),
            'fecha_legible' => $this->fecha->translatedFormat('j \d\e F \d\e Y'),
            'dosis_nro' => $this->dosis_nro,
            'marca' => $this->marca,
            'lote' => $this->lote,
            'vencimiento_lote' => $this->vencimiento_lote?->toDateString(),
            'proxima_dosis' => $this->proxima_dosis?->toDateString(),
            'reacciones' => $this->reacciones,
            'notas' => $this->notas,
        ];
    }
}
