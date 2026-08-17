<?php

namespace App\Http\Requests;

use App\Enums\Especie;
use App\Enums\Sexo;
use App\Enums\TipoPelaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

class GuardarMascotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'especie' => ['required', new Enum(Especie::class)],
            'raza' => ['nullable', 'string', 'max:120'],
            'sexo' => ['required', new Enum(Sexo::class)],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_nacimiento_estimada' => ['boolean'],
            'fecha_adopcion' => ['nullable', 'date', 'before_or_equal:today'],
            'color' => ['nullable', 'string', 'max:80'],
            'tipo_pelaje' => ['nullable', new Enum(TipoPelaje::class)],
            'senias_particulares' => ['nullable', 'string', 'max:2000'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'microchip' => ['nullable', 'string', 'max:40', $this->microchipUnico()],
            'fecha_microchip' => ['nullable', 'date'],
            'libreta_sanitaria' => ['nullable', 'string', 'max:60'],
            'pedigree' => ['nullable', 'string', 'max:60'],
            'castrado' => ['boolean'],
            'fecha_castracion' => ['nullable', 'date', 'before_or_equal:today'],
            'seguro_compania' => ['nullable', 'string', 'max:120'],
            'seguro_poliza' => ['nullable', 'string', 'max:80'],
            'seguro_vencimiento' => ['nullable', 'date'],
            'fecha_fallecimiento' => ['nullable', 'date', 'before_or_equal:today'],
            // La cámara del celular saca fotos pesadas: 10 MB de tope.
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'especie' => 'especie',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'microchip' => 'microchip',
            'foto' => 'foto',
        ];
    }

    /**
     * El microchip es único por usuario, no global: dos cuentas distintas no
     * deben chocar entre sí. En la edición se ignora la propia mascota.
     */
    protected function microchipUnico(): Rule|Unique
    {
        $regla = Rule::unique('mascotas', 'microchip')
            ->where('usuario_id', $this->user()->id)
            ->whereNull('deleted_at');

        if ($mascota = $this->route('mascota')) {
            $regla->ignore($mascota);
        }

        return $regla;
    }
}
