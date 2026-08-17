<?php

namespace App\Http\Resources;

use App\Models\Desparasitacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Desparasitacion
 */
class DesparasitacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medicamento_id' => $this->medicamento_id,
            'medicamento_libre' => $this->medicamento_libre,
            'nombre_medicamento' => $this->nombre_medicamento,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'fecha' => $this->fecha->toDateString(),
            'fecha_legible' => $this->fecha->translatedFormat('j \d\e F \d\e Y'),
            'dosis' => $this->dosis,
            'peso_al_momento' => $this->peso_al_momento,
            // "18,4 kg" y no "18.40": coma decimal y sin ceros de relleno.
            'peso_legible' => $this->pesoLegible(),
            'proxima_fecha' => $this->proxima_fecha?->toDateString(),
            'notas' => $this->notas,
        ];
    }

    private function pesoLegible(): ?string
    {
        if ($this->peso_al_momento === null) {
            return null;
        }

        $kilos = (float) $this->peso_al_momento;
        $decimales = fmod($kilos, 1.0) === 0.0 ? 0 : 1;

        return number_format($kilos, $decimales, ',', '.').' kg';
    }
}
