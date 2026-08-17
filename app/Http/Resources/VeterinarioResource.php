<?php

namespace App\Http\Resources;

use App\Models\Veterinario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Veterinario
 */
class VeterinarioResource extends JsonResource
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
            'detalle' => collect([$this->especialidad, $this->veterinaria?->nombre])
                ->filter()
                ->implode(' · ') ?: null,
            'nombre' => $this->nombre,
            'matricula' => $this->matricula,
            'especialidad' => $this->especialidad,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'notas' => $this->notas,
            'activo' => $this->activo,
            'veterinaria_id' => $this->veterinaria_id,
            'veterinaria_nombre' => $this->veterinaria?->nombre,
        ];
    }
}
