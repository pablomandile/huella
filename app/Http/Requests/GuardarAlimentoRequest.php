<?php

namespace App\Http\Requests;

use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
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
        ];
    }
}
