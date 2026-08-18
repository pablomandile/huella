<?php

namespace App\Http\Requests;

use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invitar a alguien a la ficha de una mascota, como lector o como cuidador.
 *
 * **`Propietario` no es una opción y nunca puede serlo.** La regla lo acota acá
 * con una lista blanca de dos casos, no con "cualquier caso del enum menos uno":
 * si mañana el enum suma un rol, esta regla lo deja afuera hasta que alguien
 * decida lo contrario, en vez de habilitarlo solo.
 */
class InvitarCuidadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mascota = $this->route('mascota');

        return $mascota instanceof Mascota
            && $this->user()->can('compartir', $mascota);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $this->noSeaUnoMismo(),
                $this->noTengaAccesoYa(),
            ],
            'rol' => ['required', Rule::in(RolCuidador::invitables())],
        ];
    }

    /**
     * El email normalizado, que es con el que se firma la invitación y contra el
     * que después se compara la cuenta que la acepta.
     */
    public function emailNormalizado(): string
    {
        return mb_strtolower(trim($this->validated('email')));
    }

    public function rol(): RolCuidador
    {
        return RolCuidador::from($this->validated('rol'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'correo', 'rol' => 'permiso'];
    }

    private function noSeaUnoMismo(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            if (mb_strtolower(trim((string) $valor)) === mb_strtolower($this->user()->email)) {
                $falla('Esa es tu propia dirección: la ficha ya es tuya.');
            }
        };
    }

    /**
     * Que no tenga acceso ya.
     *
     * Revelar que esa persona ya está no filtra nada: es una acción previa del
     * propio dueño, que ve el listado de accesos justo al lado del formulario.
     * Lo que sí se cuida es no decir nunca si el correo **tiene cuenta** en
     * Huella, y eso se resuelve en el controlador, que contesta siempre igual.
     */
    private function noTengaAccesoYa(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            $mascota = $this->route('mascota');

            if (! $mascota instanceof Mascota) {
                return;
            }

            $usuario = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $valor))])
                ->first();

            if ($usuario && $mascota->rolDe($usuario) !== null) {
                $falla('Ya le compartiste la ficha a esa persona.');
            }
        };
    }
}
