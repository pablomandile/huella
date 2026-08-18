<?php

namespace App\Http\Requests;

use App\Enums\TipoAdjunto;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Subida de la libreta sanitaria o del certificado de rabia.
 *
 * Van varios archivos de una: una libreta son todas sus hojas, y hacer que el
 * dueño las suba de a una con el celular en la mano es exactamente lo que hace
 * que no las suba nunca.
 */
class GuardarDocumentoMascotaRequest extends FormRequest
{
    /**
     * `registrarEventos` y no `update`: es la habilidad que aplica la regla 3
     * —una mascota fallecida pasa a modo lectura— y con ella los documentos la
     * respetan sin escribir la condición otra vez.
     */
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
            'archivos' => ['required', 'array', 'min:1', 'max:20'],
            // 10 MB por archivo, igual que los adjuntos clínicos. La foto de una
            // hoja de libreta sacada con el celular anda por los 3.
            'archivos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            // Solo los dos tipos que son documentación de la mascota: una receta
            // o una radiografía pertenecen a una visita, no acá.
            'tipo' => [
                'required',
                Rule::in(array_map(
                    fn (TipoAdjunto $tipo) => $tipo->value,
                    TipoAdjunto::documentosDeMascota(),
                )),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.required' => 'Elegí al menos un archivo.',
            'archivos.max' => 'Podés subir hasta 20 archivos por vez.',
            'archivos.*.mimes' => 'Tiene que ser una foto (JPG, PNG, WebP) o un PDF.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
            'tipo.in' => 'Ese tipo de documento no se carga acá.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'archivos' => 'archivos',
            'archivos.*' => 'archivo',
        ];
    }
}
