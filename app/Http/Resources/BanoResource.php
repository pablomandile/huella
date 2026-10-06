<?php

namespace App\Http\Resources;

use App\Models\Bano;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bano
 */
class BanoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'fecha_legible' => $this->fecha->translatedFormat('j \d\e F \d\e Y'),
            'lugar' => $this->lugar->value,
            'lugar_etiqueta' => $this->lugar->etiqueta(),
            'notas' => $this->notas,
        ];
    }
}
