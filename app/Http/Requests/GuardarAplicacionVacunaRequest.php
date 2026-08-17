<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResuelveMascotaDeLaRuta;
use App\Models\AplicacionVacuna;
use App\Models\Mascota;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class GuardarAplicacionVacunaRequest extends FormRequest
{
    use ResuelveMascotaDeLaRuta;

    public function authorize(): bool
    {
        $aplicacion = $this->route('aplicacion');

        if ($aplicacion instanceof AplicacionVacuna) {
            return $this->user()->can('update', $aplicacion);
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
            'vacuna_id' => ['nullable', $this->vacunaDisponible()],
            'vacuna_libre' => ['nullable', 'string', 'max:120'],
            'visita_id' => ['nullable', $this->visitaDeLaMascota()],
            'veterinaria_id' => ['nullable', $this->propioDelUsuario('veterinarias')],
            'veterinario_id' => ['nullable', $this->propioDelUsuario('veterinarios')],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'dosis_nro' => ['nullable', 'integer', 'min:1', 'max:20'],
            'marca' => ['nullable', 'string', 'max:120'],
            'lote' => ['nullable', 'string', 'max:60'],
            'vencimiento_lote' => ['nullable', 'date'],
            // Sugerida por los meses de refuerzo del catálogo, siempre editable
            // (regla de negocio 6). Puede quedar vacía: no todas llevan refuerzo.
            'proxima_dosis' => ['nullable', 'date', 'after:fecha'],
            'reacciones' => ['nullable', 'string', 'max:2000'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $delCatalogo = $this->input('vacuna_id');
                $aMano = trim((string) $this->input('vacuna_libre', ''));

                if (! $delCatalogo && $aMano === '') {
                    $validador->errors()->add(
                        'vacuna_libre',
                        'Elegí una vacuna del catálogo o escribí su nombre.',
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
            'vacuna_id' => 'vacuna',
            'vacuna_libre' => 'vacuna',
            'dosis_nro' => 'número de dosis',
            'proxima_dosis' => 'próxima dosis',
            'vencimiento_lote' => 'vencimiento del lote',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La aplicación no puede ser a futuro: para eso está la próxima dosis.',
            'proxima_dosis.after' => 'La próxima dosis tiene que ser posterior a la aplicación.',
        ];
    }

    private function vacunaDisponible(): Exists
    {
        $usuario = $this->user()->id;

        return Rule::exists('vacunas', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $usuario));
    }

    private function propioDelUsuario(string $tabla): Exists
    {
        return Rule::exists($tabla, 'id')
            ->where('usuario_id', $this->user()->id)
            ->whereNull('deleted_at');
    }

    private function visitaDeLaMascota(): Exists
    {
        return Rule::exists('visitas', 'id')
            ->where('mascota_id', $this->mascotaIdDeLaRuta('aplicacion'))
            ->whereNull('deleted_at');
    }
}
