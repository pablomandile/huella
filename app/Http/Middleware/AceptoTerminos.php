<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frena a quien no aceptó la versión vigente de los términos y la privacidad.
 *
 * Cubre los tres casos que no pasan por la casilla del registro: las cuentas
 * de antes de que existieran los textos, el alta con Google, y cualquier
 * cambio futuro de versión (`config('legales.version')`).
 *
 * Va en todo el grupo `web` y no ruta por ruta: así una ruta nueva nace
 * cubierta, y lo que se exceptúa está a la vista en una sola lista.
 */
class AceptoTerminos
{
    /**
     * Lo que se puede hacer sin aceptar. Leer los textos, salir, y los dos
     * derechos que no pueden depender de aceptar nada: llevarse los datos y
     * borrar la cuenta. `sincronizar` también pasa: lo cargado sin conexión se
     * registró bajo la aceptación anterior, y frenarlo trabaría la cola para
     * siempre, sin avisar.
     *
     * @var list<string>
     */
    public const EXENTAS = [
        'terminos',
        'terminos.*',
        'privacidad',
        'cookies',
        'acerca',
        'home',
        'offline',
        'logout',
        'profile.destroy',
        'exportacion.datos',
        'sincronizar',
        'verification.*',
        'compartido.*',
        'storage.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || $usuario->aceptoTerminosVigentes() || $this->exenta($request)) {
            return $next($request);
        }

        /*
         * El precalentado del modo sin conexión pide pantallas en segundo plano.
         * Redirigirlo pisaría `url.intended` con la última que pidió —después
         * de aceptar, el usuario no volvería a donde iba— y además bajaría la
         * pantalla de aceptación una vez por cada una.
         */
        if ($request->hasHeader('X-Precalentado')) {
            return response()->noContent(409);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Antes de seguir tenés que aceptar los términos y la política de privacidad.',
            ], 409);
        }

        // `guest()` guarda la URL pedida, si era un GET, para volver ahí al aceptar.
        return redirect()->guest(route('terminos.aceptar'));
    }

    private function exenta(Request $request): bool
    {
        $nombre = $request->route()?->getName();

        // Sin nombre: rutas de paquetes (devtools, .well-known), no pantallas.
        return $nombre === null || Str::is(self::EXENTAS, $nombre);
    }
}
