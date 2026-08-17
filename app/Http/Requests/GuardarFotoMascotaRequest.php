<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarFotoMascotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Fallecida = modo lectura: la galería tampoco recibe fotos nuevas.
        return $this->user()->can('registrarEventos', $this->route('mascota'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'epigrafe' => ['nullable', 'string', 'max:255'],
        ];
    }
}
