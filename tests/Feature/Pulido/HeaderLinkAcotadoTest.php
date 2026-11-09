<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

/*
 * El header `Link` lleva un preload por cada chunk que Vite precarga. El inicio
 * dibuja la ficha completa y llegó a 8,5 KB: LiteSpeed o el CDN de Hostinger
 * cortan los headers de más de 8 KB con un 500 propio, después de que Laravel ya
 * contestó 200. No queda nada en el log y en local no se reproduce, porque ahí no
 * hay proxy. Pasó en la mudanza a bambudev, al primer login.
 *
 * Los tests corren sin manifest de Vite, así que el header no se puede medir acá:
 * se fija el tope en el grupo `web`.
 */
it('acota los preloads del header Link', function () {
    $web = app(Kernel::class)->getMiddlewareGroups()['web'];

    expect($web)
        ->toContain(AddLinkHeadersForPreloadedAssets::using(20))
        ->not->toContain(AddLinkHeadersForPreloadedAssets::class);
});
