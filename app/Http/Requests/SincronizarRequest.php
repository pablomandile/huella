<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La forma del lote que manda la cola sin conexión. Solo la forma: cada
 * operación adentro se autoriza y valida con el FormRequest de su ruta de
 * siempre (ver `SincronizacionService`), así que acá no se repite ninguna regla
 * de dominio.
 */
class SincronizarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya exige sesión; qué puede tocar cada operación lo decide su
        // propio FormRequest.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Tope por envío: la app manda tandas más chicas.
            'ops' => ['required', 'array', 'list', 'max:100'],
            'ops.*' => ['array'],
        ];
    }
}
