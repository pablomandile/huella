<?php

namespace App\Http\Requests;

use App\Enums\EstadoTratamiento;
use App\Enums\ViaAdministracion;
use App\Http\Requests\Concerns\ResuelveMascotaDeLaRuta;
use App\Models\Mascota;
use App\Models\Tratamiento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

/**
 * Un tratamiento por su cuenta: el que se agrega a una visita ya cargada, o el
 * antiparasitario de rutina que no pasó por ninguna consulta.
 */
class GuardarTratamientoRequest extends FormRequest
{
    use ResuelveMascotaDeLaRuta;

    public function authorize(): bool
    {
        $tratamiento = $this->route('tratamiento');

        if ($tratamiento instanceof Tratamiento) {
            return $this->user()->can('update', $tratamiento);
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
            // La visita, si el tratamiento salió de una. Tiene que ser de la
            // misma mascota: nadie cuelga un tratamiento de la consulta de otra.
            'visita_id' => ['nullable', $this->visitaDeLaMascota()],
            'medicamento_id' => ['nullable', $this->medicamentoDisponible()],
            'medicamento_libre' => ['nullable', 'string', 'max:140'],
            'dosis' => ['required', 'string', 'max:80'],
            'via' => ['required', new Enum(ViaAdministracion::class)],
            'frecuencia_horas' => ['nullable', 'integer', 'min:1', 'max:720'],
            'veces_por_dia' => ['nullable', 'integer', 'min:1', 'max:24'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'duracion_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
            'hora_primera_toma' => ['nullable', 'date_format:H:i'],
            'estado' => ['nullable', new Enum(EstadoTratamiento::class)],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $delCatalogo = $this->input('medicamento_id');
                $aMano = trim((string) $this->input('medicamento_libre', ''));

                if (! $delCatalogo && $aMano === '') {
                    $validador->errors()->add(
                        'medicamento_libre',
                        'Elegí un medicamento del catálogo o escribí su nombre.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'medicamento_id' => 'medicamento',
            'medicamento_libre' => 'medicamento',
            'frecuencia_horas' => 'frecuencia',
            'veces_por_dia' => 'veces por día',
            'fecha_inicio' => 'fecha de inicio',
            'duracion_dias' => 'duración',
            'hora_primera_toma' => 'hora de la primera toma',
        ];
    }

    private function visitaDeLaMascota(): Exists
    {
        return Rule::exists('visitas', 'id')
            ->where('mascota_id', $this->mascotaIdDeLaRuta('tratamiento'))
            ->whereNull('deleted_at');
    }

    /** El catálogo del sistema más el propio, nunca el de otra cuenta. */
    private function medicamentoDisponible(): Exists
    {
        $usuario = $this->user()->id;

        return Rule::exists('medicamentos', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $usuario));
    }
}
