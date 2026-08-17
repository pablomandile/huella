<?php

namespace App\Http\Resources;

use App\Models\Veterinaria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Veterinaria
 */
class VeterinariaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'es_semilla' => $this->esSemilla(),
            // Las dos líneas que dibuja el combo cuando lo elegís.
            'etiqueta' => $this->nombre,
            'detalle' => $this->localidad,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'localidad' => $this->localidad,
            'telefono' => $this->telefono,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'sitio_web' => $this->sitio_web,
            'horarios' => $this->horarios,
            'urgencias_24h' => $this->urgencias_24h,
            'notas' => $this->notas,
            'activa' => $this->activa,
        ];
    }
}
