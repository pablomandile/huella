<?php

namespace App\Http\Requests;

class GuardarVeterinariaRequest extends GuardarCatalogoRequest
{
    protected function parametro(): string
    {
        return 'veterinaria';
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarSitioWeb();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:140'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'horarios' => ['nullable', 'string', 'max:255'],
            'urgencias_24h' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activa' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'sitio_web' => 'sitio web',
            'urgencias_24h' => 'atención de urgencias',
        ];
    }
}
