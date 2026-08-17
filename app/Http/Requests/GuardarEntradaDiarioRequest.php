<?php

namespace App\Http\Requests;

use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GuardarEntradaDiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entrada = $this->route('entrada');

        if ($entrada instanceof EntradaDiario) {
            return $this->user()->can('update', $entrada);
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
            'fecha' => ['required', 'date'],
            // El título es opcional: muchas notas son una línea y ponerle
            // título sería trabajo de más para algo que se escribe al pasar.
            'titulo' => ['nullable', 'string', 'max:160'],
            'contenido' => ['required', 'string', 'max:5000'],
            'categoria' => ['required', new Enum(CategoriaEntrada::class)],
            'animo' => ['nullable', new Enum(Animo::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'contenido' => 'nota',
            'categoria' => 'categoría',
        ];
    }
}
