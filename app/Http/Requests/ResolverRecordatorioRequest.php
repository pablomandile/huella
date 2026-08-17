<?php

namespace App\Http\Requests;

use App\Models\Recordatorio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las tres cosas que se pueden hacer con un recordatorio desde la bandeja:
 * marcarlo hecho, posponerlo o descartarlo.
 */
class ResolverRecordatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recordatorio = $this->route('recordatorio');

        return $recordatorio instanceof Recordatorio
            && $this->user()->can('update', $recordatorio);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['completar', 'posponer', 'descartar'])],
            // Solo para posponer: cuántos días se corre la fecha.
            'dias' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
