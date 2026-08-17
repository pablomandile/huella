<?php

namespace App\Http\Requests;

use App\Enums\OrigenPeso;
use App\Http\Requests\Concerns\ResuelveMascotaDeLaRuta;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GuardarPesoRequest extends FormRequest
{
    use ResuelveMascotaDeLaRuta;

    public function authorize(): bool
    {
        $peso = $this->route('peso');

        if ($peso instanceof RegistroPeso) {
            return $this->user()->can('update', $peso);
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
            'fecha' => ['required', 'date', 'before_or_equal:today', $this->unicoDelDia()],
            // Rango amplio: hay chihuahuas de 1 kg y mastines de 90.
            'peso_kg' => ['required', 'numeric', 'min:0.05', 'max:200'],
            // Escala de condición corporal 1-9, la que usan los veterinarios.
            'condicion_corporal' => ['nullable', 'integer', 'min:1', 'max:9'],
            'origen' => ['required', new Enum(OrigenPeso::class)],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'peso_kg' => 'peso',
            'condicion_corporal' => 'condición corporal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'No se puede cargar un peso a futuro.',
        ];
    }

    /**
     * Un peso por día y por origen: dos mediciones de la misma balanza el mismo
     * día son una corrección, no dos datos.
     *
     * **No usa `Rule::unique`, y eso importa.** Una columna `date` no se guarda
     * igual en todos los motores: MySQL la trunca a la fecha, pero SQLite —el
     * de los tests— escribe `2026-08-17 00:00:00`. `Rule::unique` compara por
     * igualdad exacta, así que ahí nunca encuentra el duplicado: la validación
     * pasa, y lo que corta es la restricción de la base, con un error 500 en la
     * cara del usuario en vez de un mensaje.
     *
     * `whereDate` genera `date(fecha) = ?`, que funciona en los dos.
     */
    protected function unicoDelDia(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            $peso = $this->route('peso');
            // Al editar hay que excluirse a sí mismo, o el registro choca
            // consigo mismo y nunca se puede guardar.
            $propioId = $peso instanceof RegistroPeso ? $peso->id : null;

            $yaExiste = RegistroPeso::query()
                ->where('mascota_id', $this->mascotaIdDeLaRuta('peso'))
                ->where('origen', $this->input('origen', OrigenPeso::Casa->value))
                ->whereDate('fecha', $valor)
                ->when($propioId !== null, fn ($consulta) => $consulta->whereKeyNot($propioId))
                ->exists();

            if ($yaExiste) {
                $fallar(
                    'Ya cargaste un peso de ese día con la misma balanza. '
                    .'Editá el que está en vez de sumar otro.',
                );
            }
        };
    }
}
