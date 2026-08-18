<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editar el epígrafe o la fecha de una foto de la galería.
 *
 * Sin `foto`: reemplazar la imagen sería otra foto, y la que está ya puede ser la
 * de perfil o estar compartida con otra entrada. Para cambiar la imagen se sube
 * una nueva y se borra esta.
 */
class ActualizarFotoMascotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Igual que el alta: una mascota fallecida pasa a modo lectura.
        return $this->user()->can('registrarEventos', $this->route('mascota'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'epigrafe' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['epigrafe' => 'epígrafe'];
    }
}
