<?php

/*
 * Las reglas del service worker que, si se rompen, no dan ningún error.
 *
 * public/sw.js no lo ejecuta ningún test de PHP, pero sus decisiones no son de
 * estilo: cada una de estas, revertida, deja a alguien viendo datos que no
 * debería, o sin avisos, o con una actualización a mitad de cargar una visita.
 * Se fijan acá como texto porque es lo único que las ve antes de producción.
 */

function sw(): string
{
    return (string) file_get_contents(public_path('sw.js'));
}

it('no se actualiza solo: espera a que el usuario acepte', function () {
    // skipWaiting solo como respuesta al mensaje del toast de "Actualizar". Con
    // el `;`: el comentario que explica por qué no va en `install` la nombra.
    preg_match_all('/self\.skipWaiting\(\);/', sw(), $llamadas);

    expect($llamadas[0])->toHaveCount(1)
        ->and(sw())->toContain("event.data === 'saltar-espera'");
});

it('no toca nada de la ficha compartida', function () {
    // Ni guardarla —es una ficha ajena en un dispositivo ajeno— ni repedirla:
    // duplicaría el contador de aperturas del enlace.
    expect(sw())->toContain("url.pathname.startsWith('/compartido/')");
});

it('no guarda las recargas parciales de Inertia', function () {
    // Pisarían la copia completa de la pantalla con la mitad de las props.
    expect(sw())->toContain('x-inertia-partial-data')
        ->and(sw())->toContain('x-inertia-partial-except');
});

it('borra lo guardado al salir de la cuenta, HTML incluido', function () {
    $sw = sw();

    expect($sw)->toContain("tipo === 'borrar-datos'")
        // El HTML de arranque lleva los datos de la página embebidos.
        ->and($sw)->toContain('esHtmlDePantalla');
});

it('guarda solo HTML sano: ni redirecciones al login ni JSON ni PDF', function () {
    expect(sw())->toContain('respuesta.ok && !respuesta.redirected && esHtml');
});

it('avisa a la app cuando arrancó de una copia guardada', function () {
    // Sin esto, una carga en frío sin red muestra datos viejos sin la franja
    // que dice de cuándo son.
    expect(sw())->toContain("tipo === 'como-arranque'")
        ->and(sw())->toContain('arranqueDesdeCopia = {');
});

it('la cola sin conexión manda a la ruta que existe', function () {
    // El cliente la usa por Wayfinder; si la ruta cambia de nombre, el build
    // falla. Esto cuida el otro lado: que la ruta siga existiendo.
    expect(route('sincronizar', absolute: false))->toBe('/sincronizar');
});
