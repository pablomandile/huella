<?php

namespace App\Http\Resources;

use App\Models\Dieta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dieta
 */
class DietaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alimento_id' => $this->alimento_id,
            'alimento' => $this->whenLoaded('alimento', fn () => trim(sprintf(
                '%s %s',
                $this->alimento->marca ?? '',
                $this->alimento->nombre,
            ))),
            'alimento_medicado' => $this->whenLoaded(
                'alimento',
                fn () => $this->alimento->medicado,
            ),
            'veterinario_id' => $this->veterinario_id,
            'veterinario' => $this->whenLoaded(
                'veterinario',
                fn () => $this->veterinario?->nombre,
            ),
            'fecha_inicio' => $this->fecha_inicio->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'periodo' => $this->periodo(),
            'vigente' => $this->estaVigente(),
            'racion_diaria_g' => $this->racion_diaria_g,
            'tomas_por_dia' => $this->tomas_por_dia,
            'racion_legible' => $this->racionLegible(),
            'motivo' => $this->motivo,
            'prescripta' => $this->prescripta,
            'notas' => $this->notas,
        ];
    }

    private function periodo(): string
    {
        $desde = $this->fecha_inicio->translatedFormat('j M Y');

        return $this->fecha_fin === null
            ? "Desde el {$desde}"
            : "{$desde} — ".$this->fecha_fin->translatedFormat('j M Y');
    }

    /** "300 g por día, en 2 tomas". */
    private function racionLegible(): ?string
    {
        if ($this->racion_diaria_g === null) {
            return null;
        }

        $texto = "{$this->racion_diaria_g} g por día";

        if ($this->tomas_por_dia !== null && $this->tomas_por_dia > 1) {
            $texto .= ", en {$this->tomas_por_dia} tomas";
        }

        return $texto;
    }
}
