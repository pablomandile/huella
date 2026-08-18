<?php

namespace App\Http\Requests;

use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use App\Rules\ImagenLegible;
use Illuminate\Validation\Rules\Enum;

class GuardarAlimentoRequest extends GuardarCatalogoRequest
{
    protected function parametro(): string
    {
        return 'alimento';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'marca' => ['nullable', 'string', 'max:120'],
            'nombre' => ['required', 'string', 'max:140'],
            'tipo' => ['required', new Enum(TipoAlimento::class)],
            'gama' => ['nullable', new Enum(GamaAlimento::class)],
            'especie' => ['required', new Enum(Especie::class)],
            'etapa' => ['required', new Enum(EtapaVida::class)],
            'presentacion' => ['nullable', 'string', 'max:80'],
            'medicado' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
            /*
             * La foto del paquete. No es fillable en el modelo —la ruta la
             * escribe `ImagenService`—, así que estas dos claves pasan por
             * `validated()` sin que `fill()` las toque, y las procesa el hook
             * `despuesDeGuardar` del controlador.
             *
             * 5 MB alcanza de sobra: se recomprime a WebP y se usa para
             * reconocer una bolsa, no para leer la tabla nutricional.
             */
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', new ImagenLegible],
            'quitar_foto' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.image' => 'La foto tiene que ser una imagen.',
            'foto.max' => 'La foto puede pesar hasta 5 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'etapa' => 'etapa de vida',
            'foto' => 'foto del paquete',
        ];
    }
}
