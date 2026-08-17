<?php

namespace App\Http\Resources;

use App\Models\Alimento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alimento
 */
class AlimentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'es_semilla' => $this->esSemilla(),
            'etiqueta' => collect([$this->marca, $this->nombre])->filter()->implode(' '),
            'detalle' => collect([$this->tipo->etiqueta(), $this->presentacion])
                ->filter()
                ->implode(' · ') ?: null,
            'marca' => $this->marca,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'gama' => $this->gama?->value,
            'gama_etiqueta' => $this->gama?->etiqueta(),
            'especie' => $this->especie->value,
            'especie_etiqueta' => $this->especie->etiqueta(),
            'etapa' => $this->etapa->value,
            'etapa_etiqueta' => $this->etapa->etiqueta(),
            'presentacion' => $this->presentacion,
            'medicado' => $this->medicado,
            'notas' => $this->notas,
        ];
    }
}
