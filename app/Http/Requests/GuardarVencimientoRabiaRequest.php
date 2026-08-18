<?php

namespace App\Http\Requests;

use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La fecha de vencimiento del certificado de rabia.
 *
 * Va por su propia acción y no por el formulario de la mascota porque se carga
 * con el papel en la mano, en la misma pantalla donde se sube.
 */
class GuardarVencimientoRabiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mascota = $this->route('mascota');

        return $mascota instanceof Mascota
            && $this->user()->can('registrarEventos', $mascota);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Se acepta una fecha ya pasada: un certificado vencido es un dato
             * real y útil —dice que hay que renovarlo—, y prohibirlo obligaría a
             * mentir para poder cargarlo. Vacío borra el vencimiento y su aviso.
             *
             * El tope de 10 años ataja el dedazo de año (2036 por 2026), que si
             * no deja el recordatorio dormido para siempre.
             */
            'rabia_vencimiento' => ['nullable', 'date', 'before_or_equal:+10 years'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rabia_vencimiento.before_or_equal' => 'Revisá el año: la fecha quedó demasiado lejos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['rabia_vencimiento' => 'fecha de vencimiento'];
    }
}
