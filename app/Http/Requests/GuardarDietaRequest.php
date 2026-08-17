<?php

namespace App\Http\Requests;

use App\Models\Dieta;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class GuardarDietaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dieta = $this->route('dieta');

        if ($dieta instanceof Dieta) {
            return $this->user()->can('update', $dieta);
        }

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
            'alimento_id' => ['required', $this->alimentoDisponible()],
            'veterinario_id' => ['nullable', $this->veterinarioPropio()],
            'fecha_inicio' => ['required', 'date'],
            // Vacía = vigente. El cierre de la anterior lo hace DietaService.
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'racion_diaria_g' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'tomas_por_dia' => ['nullable', 'integer', 'min:1', 'max:10'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'prescripta' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'alimento_id' => 'alimento',
            'veterinario_id' => 'veterinario',
            'fecha_inicio' => 'desde cuándo',
            'fecha_fin' => 'hasta cuándo',
            'racion_diaria_g' => 'ración diaria',
            'tomas_por_dia' => 'tomas por día',
        ];
    }

    private function alimentoDisponible(): Exists
    {
        $usuario = $this->user()->id;

        return Rule::exists('alimentos', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $usuario));
    }

    private function veterinarioPropio(): Exists
    {
        return Rule::exists('veterinarios', 'id')
            ->where('usuario_id', $this->user()->id)
            ->whereNull('deleted_at');
    }
}
