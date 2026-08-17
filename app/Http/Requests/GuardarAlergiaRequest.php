<?php

namespace App\Http\Requests;

use App\Enums\SeveridadAlergia;
use App\Enums\TipoAlergia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GuardarAlergiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('registrarEventos', $this->route('mascota'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', new Enum(TipoAlergia::class)],
            'agente' => ['required', 'string', 'max:140'],
            'severidad' => ['nullable', new Enum(SeveridadAlergia::class)],
            'fecha_deteccion' => ['nullable', 'date', 'before_or_equal:today'],
            'sintomas' => ['nullable', 'string', 'max:2000'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'agente' => 'agente',
            'tipo' => 'tipo',
        ];
    }
}
