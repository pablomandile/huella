<?php

namespace App\Http\Resources;

use App\Models\Adjunto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Adjunto
 */
class AdjuntoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'nombre_original' => $this->nombre_original,
            'descripcion' => $this->descripcion,
            'es_imagen' => $this->es_imagen,
            'tamanio_legible' => $this->tamanio_legible,
            // Siempre por controlador: el disco es privado y la URL directa
            // no existe (requisito de privacidad de la especificación).
            'url' => route('adjuntos.mostrar', $this->id),
            'miniatura_url' => $this->es_imagen
                ? route('adjuntos.mostrar', ['adjunto' => $this->id, 'min' => 1])
                : null,
            'descarga_url' => route('adjuntos.mostrar', ['adjunto' => $this->id, 'descargar' => 1]),
        ];
    }
}
