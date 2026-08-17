<?php

namespace App\Http\Requests;

use App\Enums\TipoDesparasitacion;
use App\Models\Desparasitacion;
use App\Models\Mascota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

class GuardarDesparasitacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $desparasitacion = $this->route('desparasitacion');

        if ($desparasitacion instanceof Desparasitacion) {
            return $this->user()->can('update', $desparasitacion);
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
            'medicamento_id' => ['nullable', $this->medicamentoDisponible()],
            'medicamento_libre' => ['nullable', 'string', 'max:140'],
            'tipo' => ['required', new Enum(TipoDesparasitacion::class)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'dosis' => ['nullable', 'string', 'max:80'],
            // La dosis de un antiparasitario depende del peso, así que el del
            // día se guarda junto con la aplicación.
            'peso_al_momento' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'proxima_fecha' => ['nullable', 'date', 'after:fecha'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'medicamento_id' => 'antiparasitario',
            'medicamento_libre' => 'antiparasitario',
            'peso_al_momento' => 'peso',
            'proxima_fecha' => 'próxima fecha',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La aplicación no puede ser a futuro: para eso está la próxima fecha.',
            'proxima_fecha.after' => 'La próxima tiene que ser posterior a esta aplicación.',
        ];
    }

    private function medicamentoDisponible(): Exists
    {
        $usuario = $this->user()->id;

        return Rule::exists('medicamentos', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $usuario));
    }
}
