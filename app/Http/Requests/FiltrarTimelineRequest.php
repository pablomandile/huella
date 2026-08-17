<?php

namespace App\Http\Requests;

use App\Services\TimelineService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los filtros del diario llegan por query string, así que se validan igual: un
 * `tipos[]` con basura o un rango invertido no puede tirar la pantalla.
 */
class FiltrarTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la mascota la autoriza el controlador
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipos' => ['sometimes', 'array'],
            'tipos.*' => [Rule::in(TimelineService::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'busqueda' => ['nullable', 'string', 'max:120'],
            'cursor' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'El «hasta» tiene que ser posterior al «desde».',
        ];
    }

    /**
     * @return array{tipos: list<string>, desde: string|null, hasta: string|null, busqueda: string|null}
     */
    public function filtros(): array
    {
        /** @var list<string> $tipos */
        $tipos = array_values($this->safe()->array('tipos'));

        return [
            'tipos' => $tipos,
            'desde' => $this->safe()->string('desde')->toString() ?: null,
            'hasta' => $this->safe()->string('hasta')->toString() ?: null,
            'busqueda' => trim($this->safe()->string('busqueda')->toString()) ?: null,
        ];
    }
}
