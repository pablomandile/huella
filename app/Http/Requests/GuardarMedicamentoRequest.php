<?php

namespace App\Http\Requests;

use App\Enums\CategoriaMedicamento;
use Illuminate\Validation\Rules\Enum;

class GuardarMedicamentoRequest extends GuardarCatalogoRequest
{
    protected function parametro(): string
    {
        return 'medicamento';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre_comercial' => ['required', 'string', 'max:140'],
            'droga' => ['nullable', 'string', 'max:140'],
            'laboratorio' => ['nullable', 'string', 'max:120'],
            'presentacion' => ['nullable', 'string', 'max:120'],
            'categoria' => ['required', new Enum(CategoriaMedicamento::class)],
            'requiere_receta' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre_comercial' => 'nombre',
            'categoria' => 'categoría',
            'requiere_receta' => 'receta',
        ];
    }
}
