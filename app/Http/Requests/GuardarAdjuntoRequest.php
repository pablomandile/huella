<?php

namespace App\Http\Requests;

use App\Enums\TipoAdjunto;
use App\Models\Visita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GuardarAdjuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visita = $this->route('visita');

        return $visita instanceof Visita && $this->user()->can('update', $visita);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 10 MB por archivo, según la especificación. Una foto de receta
            // del celular anda por los 3.
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'tipo' => ['required', new Enum(TipoAdjunto::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.mimes' => 'Tiene que ser una foto (JPG, PNG, WebP) o un PDF.',
            'archivo.max' => 'El archivo puede pesar hasta 10 MB.',
        ];
    }
}
