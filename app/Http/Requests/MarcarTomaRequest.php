<?php

namespace App\Http\Requests;

use App\Enums\EstadoToma;
use App\Models\TomaMedicamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Marcar una toma como dada o salteada. Es la acción más frecuente de la app y
 * tiene que ser un tap: por eso valida lo mínimo.
 */
class MarcarTomaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $toma = $this->route('toma');

        return $toma instanceof TomaMedicamento
            && $this->user()->can('update', $toma->tratamiento);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', new Enum(EstadoToma::class)],
            // Si no viene, se toma el momento del tap.
            'fecha_hora_real' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }
}
