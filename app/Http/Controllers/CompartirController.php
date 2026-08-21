<?php

namespace App\Http\Controllers;

use App\Enums\RolCuidador;
use App\Http\Requests\CambiarRolCuidadorRequest;
use App\Http\Requests\CrearEnlaceCompartidoRequest;
use App\Http\Requests\InvitarCuidadorRequest;
use App\Mail\InvitacionAMascota;
use App\Models\EnlaceCompartido;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Dar y quitar acceso a la ficha de una mascota.
 *
 * Todo lo que hay acá es del **propietario** (`MascotaPolicy::compartir`), con
 * una excepción: un invitado se puede quitar a sí mismo.
 */
class CompartirController extends Controller
{
    /** Cuánto dura la invitación sin aceptar. */
    private const DIAS_DE_INVITACION = 7;

    /**
     * Invitar por email.
     *
     * No hay tabla de invitaciones: la invitación **es** una URL firmada, y el
     * pivote `mascota_usuario` es la única fuente de verdad del acceso. Los tres
     * estados posibles ya son representables —pendiente: no hay fila; aceptada:
     * la fila con su rol; revocada: la fila borrada— y una tabla aparte sería una
     * máquina de estados paralela que se puede desincronizar de esa.
     *
     * La firma incluye el email, así que **reenviar el mail no sirve de nada**:
     * al aceptar se compara contra la cuenta que abrió la sesión.
     *
     * **El rol también viaja firmado.** No es lo mismo que aceptarlo del cliente:
     * lo eligió el dueño en un formulario que pasó por la Policy, y la firma lo
     * vuelve inalterable —cambiar `rol=cuidador` en la URL la invalida entera—.
     * Igual se revalida al aceptar, por si algún día la clave se filtra.
     */
    public function invitar(InvitarCuidadorRequest $request, Mascota $mascota): RedirectResponse
    {
        $usuario = $request->user();
        $email = $request->emailNormalizado();
        $rol = $request->rol();
        $vence = $usuario->hoy()->addDays(self::DIAS_DE_INVITACION);

        $url = URL::temporarySignedRoute(
            'invitaciones.mostrar',
            $vence,
            ['mascota' => $mascota->id, 'email' => $email, 'rol' => $rol->value],
        );

        // Un solo mail, idéntico tenga o no tenga cuenta el destinatario: el
        // enlace lo lleva a registrarse si hace falta. Así el formulario no puede
        // usarse para averiguar quién está registrado en Huella.
        Mail::to($email)->send(new InvitacionAMascota(
            quienInvita: $usuario,
            mascota: $mascota,
            rol: $rol,
            url: $url,
            vencimiento: $vence->translatedFormat('j \d\e F'),
        ));

        return back()->with('success', "Le mandamos la invitación a {$email}.");
    }

    /**
     * Cambiar el permiso de alguien que ya tiene acceso.
     *
     * Sin esto, pasar a un lector a cuidador obligaría a sacarlo y volver a
     * invitarlo, y a que la otra persona acepte de nuevo: tres pasos para una
     * decisión que es del dueño y de nadie más.
     */
    public function cambiarAcceso(
        CambiarRolCuidadorRequest $request,
        Mascota $mascota,
        User $usuario,
    ): RedirectResponse {
        // Al propietario no se le cambia el rol: se quedaría sin su propia ficha.
        abort_if($mascota->rolDe($usuario) === RolCuidador::Propietario, 403);
        abort_if($mascota->rolDe($usuario) === null, 404);

        $rol = $request->rol();

        // `updateExistingPivot` y no `attach`: la fila ya está, y `attach` la
        // duplicaría contra el unique.
        $mascota->cuidadores()->updateExistingPivot($usuario->id, ['rol' => $rol->value]);

        return back()->with(
            'success',
            "{$usuario->name} ahora es {$rol->etiqueta()} de {$mascota->nombre}.",
        );
    }

    /**
     * Quitarle el acceso a alguien, o irse uno mismo.
     *
     * `detach` y no un borrado lógico: el acceso es la fila del pivote y no hay
     * nada que conservar. Volver a invitar crea una nueva.
     */
    public function revocarAcceso(Request $request, Mascota $mascota, User $usuario): RedirectResponse
    {
        Gate::authorize('revocarAcceso', [$mascota, $usuario]);

        $mascota->cuidadores()->detach($usuario->id);

        // Quien se fue solo ya no puede ver la ficha: mandarlo ahí sería un 403.
        if ($request->user()->is($usuario)) {
            return redirect()
                ->route('mascotas.index')
                ->with('success', "Ya no ves la ficha de {$mascota->nombre}.");
        }

        return back()->with('success', "{$usuario->name} ya no ve la ficha.");
    }

    /**
     * Crear un enlace para mostrar la ficha sin cuenta.
     *
     * El token se genera y se muestra **una sola vez en esta respuesta**, en el
     * flash. Después queda en el listado, porque se guarda en claro justamente
     * para que el dueño lo pueda volver a copiar (ver la migración).
     */
    public function crearEnlace(CrearEnlaceCompartidoRequest $request, Mascota $mascota): RedirectResponse
    {
        $usuario = $request->user();

        $enlace = new EnlaceCompartido($request->safe()->only(['nombre', 'incluye_adjuntos']));
        $enlace->token = EnlaceCompartido::nuevoToken();
        $enlace->creado_por = $usuario->id;
        // La fecha la calcula el servidor a partir del enum, nunca el cliente, y
        // es el fin del día **del dueño**: que "vence el 17/09" signifique eso.
        // NULL cuando eligió que no venza; el enum es el único que decide eso.
        $dias = $request->vigencia()->dias();

        $enlace->expira_en = $dias === null
            ? null
            : $usuario->hoy()->addDays($dias)->endOfDay()->utc();

        $mascota->enlaces()->save($enlace);

        return back()->with('success', 'Enlace creado. Copialo y compartilo.');
    }

    /**
     * Revocar un enlace: se borra la fila y deja de resolver al instante.
     *
     * Lo que ya está pintado en una pestaña abierta no se puede borrar —eso hay
     * que asumirlo—, pero cada imagen, cada adjunto y cada recarga es un pedido
     * nuevo que vuelve a pasar por acá y muere.
     */
    public function revocarEnlace(Mascota $mascota, EnlaceCompartido $enlace): RedirectResponse
    {
        Gate::authorize('compartir', $mascota);

        // Que el enlace sea de esta mascota, y no de otra cuyo id se adivinó.
        abort_unless($enlace->mascota_id === $mascota->id, 404);

        $enlace->delete();

        return back()->with('success', 'El enlace dejó de funcionar.');
    }
}
