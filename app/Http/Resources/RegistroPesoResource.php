<?php

namespace App\Http\Resources;

use App\Enums\OrigenPeso;
use App\Models\RegistroPeso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegistroPeso
 */
class RegistroPesoResource extends JsonResource
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
            'peso_kg' => $this->kilos(),
            // "18,4 kg": coma decimal y sin ceros de relleno.
            'peso_legible' => $this->pesoLegible(),
            'condicion_corporal' => $this->condicion_corporal,
            'origen' => $this->origen->value,
            'origen_etiqueta' => $this->origen->etiqueta(),
            // El gráfico dibuja distinto los de la veterinaria: su balanza y la
            // de casa no coinciden, y eso no es variación real.
            'en_veterinaria' => $this->origen === OrigenPeso::Veterinaria,
            'visita_id' => $this->visita_id,
            'notas' => $this->notas,
        ];
    }

    private function pesoLegible(): string
    {
        $kilos = $this->kilos();
        $decimales = fmod($kilos, 1.0) === 0.0 ? 0 : 1;

        return number_format($kilos, $decimales, ',', '.').' kg';
    }
}
