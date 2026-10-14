<?php

/*
 * El <html> lleva su color de fondo en un <style> inline de app.blade.php,
 * antes de que llegue app.css. No es decorativo: pinta el primer frame y, sobre
 * todo, la zona de rebote del scroll en iOS, que queda fuera del <body> y por lo
 * tanto fuera de cualquier `bg-background`.
 *
 * El problema es que esos dos colores viven en archivos distintos y nada los
 * ata. Si alguien cambia la paleta y no toca el blade, no hay error ni build que
 * falle: simplemente aparece una franja blanca —o negra— al final del scroll, en
 * el celular, que es donde nadie va a mirar mientras edita CSS.
 */

/** El valor de una variable del bloque indicado de app.css, tal cual está escrito. */
function variableDeCss(string $selector, string $variable): ?string
{
    $css = (string) file_get_contents(resource_path('css/app.css'));

    if (! preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s', $css, $bloque)) {
        return null;
    }

    if (! preg_match('/--'.preg_quote($variable, '/').':\s*([^;]+);/', $bloque[1], $valor)) {
        return null;
    }

    return trim($valor[1]);
}

/** Los `background-color` del <style> inline del blade raíz, por selector. */
function fondosDelBladeRaiz(): array
{
    $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

    preg_match_all(
        '/(html(?:\.dark)?)\s*\{\s*background-color:\s*([^;]+);/',
        $blade,
        $reglas,
        PREG_SET_ORDER,
    );

    $fondos = [];

    foreach ($reglas as $regla) {
        $fondos[$regla[1]] = trim($regla[2]);
    }

    return $fondos;
}

it('pinta el html con el mismo fondo que la paleta', function () {
    $fondos = fondosDelBladeRaiz();

    expect($fondos)->toHaveKeys(['html', 'html.dark']);

    expect($fondos['html'])->toBe(
        variableDeCss(':root', 'background'),
        'el fondo claro del blade no coincide con --background de app.css',
    );

    expect($fondos['html.dark'])->toBe(
        variableDeCss('.dark', 'background'),
        'el fondo oscuro del blade no coincide con --background de .dark',
    );
});
