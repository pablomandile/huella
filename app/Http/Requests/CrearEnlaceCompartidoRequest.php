<?php

namespace App\Http\Requests;

use App\Enums\VigenciaEnlace;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear un enlace para mostrar la ficha sin cuenta.
 *
 * **La fecha de vencimiento no llega del cliente**, solo cuál de las tres
 * vigencias eligió. Si el formulario mandara un `expira_en`, un POST a mano
 * pondría el año 3000 y el vencimiento obligatorio dejaría de ser obligatorio.
 * El servidor calcula la fecha a partir del enum.
 */
class CrearEnlaceCompartidoRequest extends FormRequest
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
        return [
            'nombre' => ['nullable', 'string', 'max:80'],
            'vigencia' => ['required', Rule::enum(VigenciaEnlace::class)],
            'incluye_adjuntos' => ['boolean'],
        ];
    }

    public function vigencia(): VigenciaEnlace
    {
        return VigenciaEnlace::from($this->validated('vigencia'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del enlace',
            'vigencia' => 'vencimiento',
            'incluye_adjuntos' => 'estudios y recetas',
        ];
    }
}
