<?php

namespace App\Http\Resources;

use App\Models\Vacuna;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vacuna
 */
class VacunaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'es_semilla' => $this->esSemilla(),
            'etiqueta' => $this->nombre,
            'detalle' => $this->refuerzoLegible(),
            'nombre' => $this->nombre,
            'especie' => $this->especie->value,
            'especie_etiqueta' => $this->especie->etiqueta(),
            'descripcion' => $this->descripcion,
            'meses_refuerzo' => $this->meses_refuerzo,
            'obligatoria' => $this->obligatoria,
        ];
    }

    /**
     * Los meses de refuerzo son una **sugerencia** que precarga la próxima
     * dosis en la fase 5; siempre queda editable (regla de negocio 6).
     */
    private function refuerzoLegible(): ?string
    {
        return match (true) {
            $this->meses_refuerzo === null => null,
            $this->meses_refuerzo === 12 => 'Refuerzo al año',
            $this->meses_refuerzo % 12 === 0 => 'Refuerzo cada '.($this->meses_refuerzo / 12).' años',
            $this->meses_refuerzo === 1 => 'Refuerzo al mes',
            default => "Refuerzo a los {$this->meses_refuerzo} meses",
        };
    }
}
