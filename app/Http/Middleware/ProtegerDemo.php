<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * En la demo (APP_DEMO=true) hay un solo usuario, compartido por todos los que
 * la prueban.
 *
 * - **Nadie entra por otro lado**: sin registro, sin Google, sin recuperar la
 *   clave. Esas pantallas van al login, donde está el botón de la demo.
 * - **La cuenta no se toca**: ni la clave, ni el email, ni el 2FA, ni las
 *   llaves de acceso, ni borrarla. Un visitante que le pone 2FA deja afuera a
 *   todos los demás hasta el reset de la noche.
 * - **Nada sale hacia afuera**: invitaciones y enlaces públicos. Una invitación
 *   manda un mail a cualquier dirección desde este dominio, y un enlace público
 *   sin vencimiento serviría cualquier imagen que un visitante suba.
 *
 * Todo lo demás —cargar visitas, marcar tomas, subir fotos, borrar mascotas— sí:
 * es lo que se quiere mostrar, y se resiembra cada noche.
 *
 * Con el flag apagado (la productiva) no hace nada.
 */
class ProtegerDemo
{
    /** Pantallas que no existen en la demo: van al login. */
    private const PANTALLAS = [
        'register', 'register.store',
        'google.redirect', 'google.callback',
        'password.request', 'password.email', 'password.reset', 'password.update',
    ];

    /** Acciones sobre la cuenta o hacia afuera: vuelven atrás con un aviso. */
    private const ACCIONES = [
        'user-password.update', 'profile.update', 'profile.destroy',
        'two-factor.enable', 'two-factor.confirm', 'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'passkey.store', 'passkey.destroy',
        'mascotas.invitaciones.store', 'mascotas.enlaces.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $ruta = $request->route()?->getName();

        // El POST de /register de Fortify puede no tener nombre.
        if (in_array($ruta, self::PANTALLAS, true) || $request->is('register')) {
            return redirect()->route('login');
        }

        if (in_array($ruta, self::ACCIONES, true)) {
            // Por el canal de toasts de Inertia: es el único que escucha el front.
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Eso no se puede en la demo: la cuenta es compartida.',
            ]);

            return back();
        }

        return $next($request);
    }
}
