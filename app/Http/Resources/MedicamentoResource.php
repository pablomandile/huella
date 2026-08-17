<?php

namespace App\Http\Resources;

use App\Models\Medicamento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Medicamento
 */
class MedicamentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'es_semilla' => $this->esSemilla(),
            'etiqueta' => $this->nombre_comercial,
            'detalle' => collect([$this->droga, $this->presentacion])
                ->filter()
                ->implode(' · ') ?: null,
            'nombre_comercial' => $this->nombre_comercial,
            'droga' => $this->droga,
            'laboratorio' => $this->laboratorio,
            'presentacion' => $this->presentacion,
            'categoria' => $this->categoria->value,
            'categoria_etiqueta' => $this->categoria->etiqueta(),
            'requiere_receta' => $this->requiere_receta,
            'notas' => $this->notas,
        ];
    }
}
