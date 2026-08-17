<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Los errores se muestran con la página Inertia, no con la pantalla
         * blanca de Laravel: así conservan el idioma, el tema y la marca. Un
         * error que se ve como otra app asusta más que el error.
         *
         * Solo estos códigos: el resto los maneja Laravel como siempre. Y en
         * local se deja pasar todo, porque ahí el stack trace de Ignition vale
         * mucho más que una pantalla linda.
         */
        $exceptions->respond(function (SymfonyResponse $respuesta, Throwable $e, Request $request) {
            $enPantalla = [403, 404, 419, 500, 503];

            if (app()->environment('local') || ! in_array($respuesta->getStatusCode(), $enPantalla, true)) {
                return $respuesta;
            }

            // Un 419 es sesión vencida: se reintenta y suele resolverse solo.
            if ($respuesta->getStatusCode() === 419) {
                return back()->with('warning', 'Se venció la sesión. Probá de nuevo.');
            }

            /*
             * El mensaje de la excepción **no** se muestra. Un 404 de route
             * binding trae "No query results for model [App\Models\Mascota]
             * 99999": expone la clase y confirma qué ids existen, y al usuario
             * no le dice nada útil. Los textos de la página ya explican cada
             * código.
             */
            return Inertia::render('Error', [
                'status' => $respuesta->getStatusCode(),
                'autenticado' => $request->user() !== null,
            ])
                ->toResponse($request)
                ->setStatusCode($respuesta->getStatusCode());
        });
    })->create();
