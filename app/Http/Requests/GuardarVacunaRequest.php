<?php

namespace App\Http\Requests;

use App\Enums\Especie;
use Illuminate\Validation\Rules\Enum;

class GuardarVacunaRequest extends GuardarCatalogoRequest
{
    protected function parametro(): string
    {
        return 'vacuna';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'especie' => ['required', new Enum(Especie::class)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            // Sugerencia para precargar la próxima dosis, siempre editable
            // al aplicar (regla de negocio 6). 120 meses = 10 años de tope.
            'meses_refuerzo' => ['nullable', 'integer', 'min:1', 'max:120'],
            'obligatoria' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'meses_refuerzo' => 'refuerzo sugerido',
        ];
    }
}
