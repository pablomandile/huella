<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;

/*
 * Una URL de Inertia contesta dos cuerpos distintos según el header `X-Inertia`:
 * el HTML de arranque, o el JSON de la página. Lo único que las separa para una
 * caché es `Vary: X-Inertia`, y el CDN de Hostinger lo borra al comprimir con
 * brotli. Sin eso, el navegador guarda el JSON bajo la URL de la página y lo
 * muestra crudo cuando restaura una pestaña descartada, porque una navegación de
 * historial reusa lo guardado sin revalidar.
 *
 * El arreglo es `Cache-Control: no-store` en la respuesta XHR, y en ninguna otra.
 */

/** La versión del asset, o Inertia contesta 409 en vez de la página. */
function versionDeInertia(): string
{
    return (string) app(HandleInertiaRequests::class)->version(request());
}

it('prohíbe guardar la respuesta XHR de Inertia', function () {
    $respuesta = $this->get('/login', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => versionDeInertia(),
    ]);

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('application/json');
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});

it('deja cacheable el documento HTML, para no perder el bfcache', function () {
    /*
     * Poner `no-store` también acá sería más simple y estaría mal: Chrome
     * desactiva el back/forward cache de las páginas que lo traen, y cada
     * "atrás" pasa a ser una ida completa a la red. No da ningún síntoma que lo
     * delate, así que lo tiene que cuidar este test.
     */
    $respuesta = $this->get('/login');

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('text/html');
    expect($respuesta->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('declara X-Inertia en el Vary de las dos variantes', function () {
    $html = $this->get('/login');
    $json = $this->get('/login', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => versionDeInertia(),
    ]);

    expect($html->headers->get('Vary'))->toContain('X-Inertia');
    expect($json->headers->get('Vary'))->toContain('X-Inertia');
});

it('también protege las pantallas con sesión', function () {
    // El caso real no es el login: es el diario, que se navega por XHR y queda
    // en la caché del navegador con los datos clínicos adentro.
    $usuario = User::factory()->create();

    $respuesta = $this->actingAs($usuario)->get('/dashboard', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => versionDeInertia(),
    ]);

    $respuesta->assertOk();
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
});
