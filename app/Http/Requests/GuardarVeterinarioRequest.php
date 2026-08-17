<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuardarVeterinarioRequest extends GuardarCatalogoRequest
{
    protected function parametro(): string
    {
        return 'veterinario';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:140'],
            'matricula' => ['nullable', 'string', 'max:60'],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            // Solo veterinarias propias: nadie asocia un profesional a la
            // agenda de otra cuenta pasando un id a mano.
            'veterinaria_id' => [
                'nullable',
                Rule::exists('veterinarias', 'id')
                    ->where('usuario_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'veterinaria_id' => 'veterinaria',
        ];
    }
}
