<?php

/*
 * El tamaño de letra vive en tres archivos que nada ata entre sí:
 *
 *   - el script inline de app.blade.php, que lo aplica antes del primer pintado;
 *   - app.css, que define cuánto escala cada valor;
 *   - el composable useTamanoLetra.ts, que lo cambia desde Ajustes.
 *
 * Si alguien renombra un valor en uno solo —'grande' por 'large', como en Mi
 * Billetera— no hay error ni build que falle: la opción se puede elegir, se
 * guarda, y la letra no cambia. O cambia en vivo y al recargar vuelve a mediano.
 */

function archivo(string $ruta): string
{
    return (string) file_get_contents(base_path($ruta));
}

/** Los valores que el script del <head> acepta para poner en <html>. */
function valoresDelHead(): array
{
    preg_match_all("/t === '(\w+)'/", archivo('resources/views/app.blade.php'), $m);

    return $m[1];
}

it('cada valor que aplica el head tiene su escala en el CSS', function () {
    $css = archivo('resources/css/app.css');

    expect(valoresDelHead())->toEqualCanonicalizing(['pequeno', 'grande']);

    foreach (valoresDelHead() as $valor) {
        expect($css)->toContain(":root[data-tamano-letra='{$valor}']");
    }
});

it('el composable ofrece los mismos valores que entiende el head', function () {
    $ts = archivo('resources/js/composables/useTamanoLetra.ts');

    foreach ([...valoresDelHead(), 'mediano'] as $valor) {
        expect($ts)->toContain("valor: '{$valor}'");
    }

    // Y guarda bajo la misma clave que lee el head.
    expect($ts)->toContain("const CLAVE = 'tamanoLetra';")
        ->and(archivo('resources/views/app.blade.php'))->toContain("localStorage.getItem('tamanoLetra')");
});

it('los tamaños de texto de Tailwind pasan por la escala', function () {
    $css = archivo('resources/css/app.css');

    // Sin estos, `text-sm` y compañía seguirían en rem fijo y elegir un
    // tamaño solo cambiaría el texto suelto del body.
    foreach (['xs', 'sm', 'base', 'lg', 'xl', '2xl'] as $tamano) {
        expect($css)->toMatch("/--text-{$tamano}: calc\([\d.]+rem \* var\(--font-scale\)\);/");
    }
});
