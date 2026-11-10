<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TamanoLetra;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tamaño de letra antes de renderizar, para que el atributo ya
 * viaje en el `<html>` de arranque.
 *
 * A diferencia del tema, **no hace falta un script inline**: no hay opción
 * "según el sistema", así que el servidor sabe la respuesta y la escribe. Cero
 * parpadeo y cero JavaScript en el arranque.
 */
class HandleTamanoLetra
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('tamanoLetra', self::resolver($request));

        return $next($request);
    }

    /**
     * **Manda la cuenta, después la cookie, después el default.**
     *
     * La cuenta es la que viaja entre dispositivos. La cookie cubre a quien no
     * inició sesión y evita una consulta por request de un invitado.
     *
     * En la demo la cuenta es una sola y compartida: si mandara, un visitante
     * que agranda la letra se la agrandaría a todos. Ahí manda la cookie.
     */
    public static function resolver(Request $request): TamanoLetra
    {
        $usuario = $request->user();

        if ($usuario instanceof User && ! config('demo.enabled')) {
            return $usuario->tamano_letra;
        }

        // Una cookie mandada con corchetes llega como array.
        $cookie = $request->cookie('tamano_letra');

        return TamanoLetra::desde(is_string($cookie) ? $cookie : null);
    }
}
