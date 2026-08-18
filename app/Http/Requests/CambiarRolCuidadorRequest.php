<?php

namespace App\Http\Requests;

use App\Enums\RolCuidador;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambiar el permiso de alguien que ya tiene acceso a la ficha.
 *
 * Autoriza por `compartir` y no por `revocarAcceso`: sacarse el acceso uno mismo
 * es legítimo, pero **subirse el propio permiso no lo es**. Un lector que pudiera
 * llamar a esto se ascendería a cuidador solo.
 */
class CambiarRolCuidadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mascota = $this->route('mascota');

        return $mascota instanceof Mascota
            && $this->user()->can('compartir', $mascota);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // La misma lista blanca que la invitación: `Propietario` no se concede.
        return [
            'rol' => ['required', Rule::in(RolCuidador::invitables())],
        ];
    }

    public function rol(): RolCuidador
    {
        return RolCuidador::from($this->validated('rol'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['rol' => 'permiso'];
    }
}
