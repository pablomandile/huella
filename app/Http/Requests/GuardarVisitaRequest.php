<?php

namespace App\Http\Requests;

use App\Enums\TipoAdjunto;
use App\Enums\TipoVisita;
use App\Enums\ViaAdministracion;
use App\Models\Mascota;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

/**
 * Alta y edición de una visita, con los tratamientos y los adjuntos que salen
 * de ella en el mismo envío.
 *
 * Es el formulario más cargado de la app y se completa parado en el mostrador
 * de la veterinaria, así que casi todo es opcional: lo único que se exige es
 * cuándo fue. Un registro incompleto sirve; uno que no se pudo guardar, no.
 */
class GuardarVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mascota = $this->route('mascota');
        $visita = $this->route('visita');

        if (! $mascota instanceof Mascota) {
            return false;
        }

        // Al editar se pide permiso sobre la visita; al crear, sobre la mascota.
        return $visita === null
            ? $this->user()->can('registrarEventos', $mascota)
            : $this->user()->can('update', $visita);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date'],
            'tipo' => ['required', new Enum(TipoVisita::class)],
            'veterinaria_id' => ['nullable', $this->existeEnCatalogo('veterinarias')],
            'veterinario_id' => ['nullable', $this->existeEnCatalogo('veterinarios')],
            'motivo' => ['nullable', 'string', 'max:255'],
            'diagnostico' => ['nullable', 'string', 'max:5000'],
            'indicaciones' => ['nullable', 'string', 'max:5000'],
            // Rango fisiológico amplio a propósito: no es tarea de la app
            // decidir qué temperatura es posible.
            'temperatura' => ['nullable', 'numeric', 'min:20', 'max:45'],
            'costo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'proximo_control' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:5000'],

            // Tratamientos que se cargan junto con la visita.
            'tratamientos' => ['array', 'max:10'],
            'tratamientos.*.medicamento_id' => ['nullable', $this->existeEnCatalogo('medicamentos', semilla: true)],
            'tratamientos.*.medicamento_libre' => ['nullable', 'string', 'max:140'],
            'tratamientos.*.dosis' => ['required', 'string', 'max:80'],
            'tratamientos.*.via' => ['required', new Enum(ViaAdministracion::class)],
            'tratamientos.*.frecuencia_horas' => ['nullable', 'integer', 'min:1', 'max:720'],
            'tratamientos.*.veces_por_dia' => ['nullable', 'integer', 'min:1', 'max:24'],
            'tratamientos.*.fecha_inicio' => ['required', 'date'],
            'tratamientos.*.duracion_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
            'tratamientos.*.hora_primera_toma' => ['nullable', 'date_format:H:i'],
            'tratamientos.*.notas' => ['nullable', 'string', 'max:1000'],

            // Recetas y estudios. El tipo es uno solo para todo el lote: al pie
            // del mostrador se saca la foto de la receta y listo; el detalle se
            // ajusta después desde la ficha de la visita.
            'adjuntos' => ['array', 'max:5'],
            'adjuntos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'tipo_adjunto' => ['nullable', new Enum(TipoAdjunto::class)],
        ];
    }

    /**
     * Un tratamiento sin nombre de medicamento no se puede dar: o sale del
     * catálogo o se escribe a mano, pero algo tiene que haber.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                foreach ($this->input('tratamientos', []) as $i => $tratamiento) {
                    $delCatalogo = $tratamiento['medicamento_id'] ?? null;
                    $aMano = trim((string) ($tratamiento['medicamento_libre'] ?? ''));

                    if (! $delCatalogo && $aMano === '') {
                        $validador->errors()->add(
                            "tratamientos.{$i}.medicamento_libre",
                            'Elegí un medicamento del catálogo o escribí su nombre.',
                        );
                    }
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
            'fecha_hora' => 'fecha y hora',
            'veterinaria_id' => 'veterinaria',
            'veterinario_id' => 'veterinario',
            'proximo_control' => 'próximo control',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tratamientos.*.dosis.required' => 'Falta la dosis de uno de los medicamentos.',
            'tratamientos.*.fecha_inicio.required' => 'Falta desde cuándo se da uno de los medicamentos.',
            'adjuntos.*.mimes' => 'Los archivos tienen que ser fotos (JPG, PNG, WebP) o PDF.',
            'adjuntos.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }

    /**
     * Solo lo que el usuario puede elegir: lo propio y, donde corresponda, la
     * semilla del sistema. Nadie referencia el catálogo de otra cuenta.
     */
    private function existeEnCatalogo(string $tabla, bool $semilla = false): Exists
    {
        $regla = Rule::exists($tabla, 'id')->whereNull('deleted_at');
        $usuario = $this->user()->id;

        return $semilla
            ? $regla->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $usuario))
            : $regla->where('usuario_id', $usuario);
    }
}
