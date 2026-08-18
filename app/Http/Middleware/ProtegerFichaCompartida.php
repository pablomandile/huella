<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * El perímetro de la ficha pública: lo que vale para el HTML, para las imágenes
 * y para el PDF por igual.
 *
 * Va en un middleware y no en el `.htaccess` por tres razones: vale también en
 * local y en los tests, no depende de Apache, y el CDN de Hostinger ya demostró
 * que se come headers cuando comprime con brotli.
 */
class ProtegerFichaCompartida
{
    /** Cuántos tokens inválidos por IP antes de cortar. */
    private const FALLOS_POR_MINUTO = 10;

    /** Cuánto dura el corte. */
    private const CASTIGO_SEGUNDOS = 3600;

    public function handle(Request $request, Closure $next): Response
    {
        $llave = 'ficha-fallida:'.$request->ip();

        // Con 48 caracteres de token nadie adivina uno. El límite es para que un
        // bot no llene el contador de aperturas ni se coma la CPU del hosting
        // compartido, y cuenta **solo los fallos**: un visitante legítimo nunca
        // falla, y el que falla diez veces está probando.
        if (RateLimiter::tooManyAttempts($llave, self::FALLOS_POR_MINUTO)) {
            abort(429);
        }

        $respuesta = $next($request);

        if ($respuesta->getStatusCode() === 404) {
            RateLimiter::hit($llave, self::CASTIGO_SEGUNDOS);
        }

        return $this->conHeaders($respuesta);
    }

    private function conHeaders(Response $respuesta): Response
    {
        $respuesta->headers->add([
            /*
             * En **header** y no solo en un <meta>: así cubre también las
             * imágenes y el PDF, donde no hay HTML donde poner un meta.
             *
             * Y nada de `Disallow: /compartido/` en robots.txt: eso impide
             * *rastrear*, no *indexar*, y un buscador que tiene prohibido entrar
             * nunca llega a leer este noindex. Conseguiría lo contrario.
             */
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, noimageindex',

            // El token va en el path: sin esto se lo llevaría entero cualquier
            // recurso de tercero cargado desde la página. (La ficha no carga
            // ninguno, pero la defensa no depende de que eso siga siendo cierto.)
            'Referrer-Policy' => 'no-referrer',

            /*
             * `no-store`, y **no** el `private, max-age=86400` que usan las rutas
             * autenticadas de fotos y adjuntos. Copiar aquella línea dejaría una
             * radiografía un día entero en el disco del navegador de la
             * veterinaria.
             *
             * Es una excepción deliberada a la regla del proyecto de no poner
             * `no-store` en un documento HTML —que existe para no perder el
             * back/forward cache—. Acá perderlo es justamente lo que se busca:
             * después de revocar un enlace, apretar "atrás" no puede repintar una
             * historia clínica desde la memoria del navegador. El costo lo paga
             * un invitado en una sola página, no el usuario de la app.
             */
            'Cache-Control' => 'no-store, private, max-age=0',
        ]);

        return $respuesta;
    }
}
