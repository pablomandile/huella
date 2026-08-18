<?php

namespace App\Http\Resources;

use App\Enums\RolCuidador;
use App\Models\Mascota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Mascota
 */
class MascotaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // El rol de quien está mirando, para que el front pueda distinguir una
        // ficha propia de una compartida. `cuidadores` ya viene en `Mascota::$with`,
        // así que no cuesta ninguna query.
        $usuario = $request->user();
        $rol = $usuario ? $this->rolDe($usuario) : null;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'especie' => $this->especie->value,
            'especie_etiqueta' => $this->especie->etiqueta(),
            'raza' => $this->raza,
            'sexo' => $this->sexo->value,
            'sexo_etiqueta' => $this->sexo->etiqueta(),
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'fecha_nacimiento_estimada' => $this->fecha_nacimiento_estimada,
            'fecha_adopcion' => $this->fecha_adopcion?->toDateString(),
            'edad' => $this->edad,
            'color' => $this->color,
            'tipo_pelaje' => $this->tipo_pelaje?->value,
            'tipo_pelaje_etiqueta' => $this->tipo_pelaje?->etiqueta(),
            'senias_particulares' => $this->senias_particulares,
            'descripcion' => $this->descripcion,
            'microchip' => $this->microchip,
            'fecha_microchip' => $this->fecha_microchip?->toDateString(),
            'libreta_sanitaria' => $this->libreta_sanitaria,
            'pedigree' => $this->pedigree,
            'castrado' => $this->castrado,
            'fecha_castracion' => $this->fecha_castracion?->toDateString(),
            'seguro_compania' => $this->seguro_compania,
            'seguro_poliza' => $this->seguro_poliza,
            'seguro_vencimiento' => $this->seguro_vencimiento?->toDateString(),
            'fallecida' => $this->fallecida,
            'fecha_fallecimiento' => $this->fecha_fallecimiento?->toDateString(),
            'celo_visible' => $this->celo_visible,
            'foto_url' => $this->urlFoto(),
            'foto_miniatura_url' => $this->urlFoto(miniatura: true),
            // De quién es, para que un invitado sepa a quién le está mirando la
            // ficha. Solo el nombre de pila: el contacto no hace falta.
            'propietario_nombre' => $this->propietario->name,
            'rol' => $rol?->value,
            'rol_etiqueta' => $rol?->etiqueta(),
            'es_propia' => $rol === RolCuidador::Propietario,
        ];
    }

    private function urlFoto(bool $miniatura = false): ?string
    {
        if (! $this->foto_perfil) {
            return null;
        }

        return route('mascotas.foto-perfil', [
            'mascota' => $this->id,
            'min' => $miniatura ? 1 : null,
            // Cambia con cada edición: invalida la caché HTTP al reemplazar la foto.
            'v' => $this->updated_at?->timestamp,
        ]);
    }
}
