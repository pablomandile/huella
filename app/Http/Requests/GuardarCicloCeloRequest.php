<?php

namespace App\Http\Requests;

use App\Enums\IntensidadCelo;
use App\Models\CicloCelo;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GuardarCicloCeloRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ciclo = $this->route('ciclo');

        if ($ciclo instanceof CicloCelo) {
            return $this->user()->can('update', $ciclo);
        }

        $mascota = $this->route('mascota');

        // El módulo de celo solo existe para hembras no castradas y vivas
        // (regla de negocio 2): si no, no hay nada que registrar.
        return $mascota instanceof Mascota
            && $mascota->celo_visible
            && $this->user()->can('registrarEventos', $mascota);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date', 'before_or_equal:today'],
            // Vacía = en curso. La duración la calcula el observer al cerrarlo.
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio', 'before_or_equal:today'],
            'intensidad' => ['nullable', new Enum(IntensidadCelo::class)],
            'sintomas' => ['nullable', 'string', 'max:2000'],
            'hubo_monta' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'hubo_monta' => 'monta',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.before_or_equal' => 'No se puede cargar un celo a futuro: '
                .'para eso está la estimación.',
        ];
    }
}
