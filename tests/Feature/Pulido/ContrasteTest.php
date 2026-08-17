<?php

/*
 * Contraste WCAG de la paleta real de resources/css/app.css.
 *
 * AA pide 4.5:1 para texto normal y 3:1 para componentes de interfaz y estados
 * de foco. Está como test y no como planilla aparte porque la paleta se toca:
 * cualquiera que cambie un color se entera acá y no en una auditoría seis meses
 * después.
 *
 * Ojo con --border: es el borde decorativo (tarjetas, separadores) y queda a
 * propósito por debajo de 3:1. El que identifica un control es --input, y ese sí
 * tiene que cumplir.
 */

/**
 * Las variables de color de un selector, ya convertidas a RGB.
 *
 * @return array<string, array{float, float, float}>
 */
function paletaDe(string $selector): array
{
    $css = (string) file_get_contents(resource_path('css/app.css'));

    if (! preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s', $css, $bloque)) {
        return [];
    }

    preg_match_all('/--([\w-]+):\s*hsl\(([^)]+)\)/', $bloque[1], $vars, PREG_SET_ORDER);

    $paleta = [];

    foreach ($vars as $var) {
        $paleta[$var[1]] = hslARgb($var[2]);
    }

    return $paleta;
}

/**
 * @return array{float, float, float}
 */
function hslARgb(string $hsl): array
{
    $partes = preg_split('/[\s,\/]+/', trim($hsl)) ?: [];
    $h = (float) $partes[0];
    $s = (float) rtrim($partes[1], '%') / 100;
    $l = (float) rtrim($partes[2], '%') / 100;

    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    [$r, $g, $b] = match (true) {
        $h < 60 => [$c, $x, 0.0],
        $h < 120 => [$x, $c, 0.0],
        $h < 180 => [0.0, $c, $x],
        $h < 240 => [0.0, $x, $c],
        $h < 300 => [$x, 0.0, $c],
        default => [$c, 0.0, $x],
    };

    return [($r + $m) * 255, ($g + $m) * 255, ($b + $m) * 255];
}

/**
 * @param  array{float, float, float}  $rgb
 */
function luminanciaRelativa(array $rgb): float
{
    $canal = fn (float $v): float => ($v /= 255) <= 0.03928
        ? $v / 12.92
        : (($v + 0.055) / 1.055) ** 2.4;

    return 0.2126 * $canal($rgb[0]) + 0.7152 * $canal($rgb[1]) + 0.0722 * $canal($rgb[2]);
}

/**
 * @param  array{float, float, float}  $frente
 * @param  array{float, float, float}  $fondo
 */
function razonDeContraste(array $frente, array $fondo): float
{
    $a = luminanciaRelativa($frente);
    $b = luminanciaRelativa($fondo);

    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

/**
 * Un color con opacidad sobre otro, como lo compone el navegador.
 *
 * @param  array{float, float, float}  $frente
 * @param  array{float, float, float}  $fondo
 * @return array{float, float, float}
 */
function sobre(array $frente, array $fondo, float $alfa): array
{
    return [
        $frente[0] * $alfa + $fondo[0] * (1 - $alfa),
        $frente[1] * $alfa + $fondo[1] * (1 - $alfa),
        $frente[2] * $alfa + $fondo[2] * (1 - $alfa),
    ];
}

/**
 * Pares que se usan de verdad en la interfaz, con el mínimo que les toca.
 *
 * @return list<array{string, string, float, string}>
 */
function paresDeContraste(): array
{
    return [
        // Texto: 4.5:1.
        ['foreground', 'background', 4.5, 'texto general'],
        ['muted-foreground', 'background', 4.5, 'texto secundario'],
        ['muted-foreground', 'muted', 4.5, 'texto secundario sobre gris'],
        ['card-foreground', 'card', 4.5, 'texto en tarjeta'],
        ['popover-foreground', 'popover', 4.5, 'texto en popover'],
        ['primary-foreground', 'primary', 4.5, 'texto del botón principal'],
        ['secondary-foreground', 'secondary', 4.5, 'texto del botón secundario'],
        ['accent-foreground', 'accent', 4.5, 'texto sobre acento'],
        ['sidebar-foreground', 'sidebar-background', 4.5, 'texto de la sidebar'],
        ['sidebar-accent-foreground', 'sidebar-accent', 4.5, 'ítem activo de la sidebar'],
        ['sidebar-primary-foreground', 'sidebar-primary', 4.5, 'botón en la sidebar'],

        // Interfaz y foco: 3:1.
        ['primary', 'background', 3.0, 'ícono de marca'],
        ['ring', 'background', 3.0, 'anillo de foco'],
        ['ring', 'card', 3.0, 'anillo de foco en tarjeta'],
        // El rojo también se escribe como texto (la hora de una toma atrasada,
        // los mensajes de error), así que le toca 4.5 y no el 3 de un ícono.
        ['destructive', 'background', 4.5, 'texto de error'],
        ['input', 'background', 3.0, 'borde de un control'],
    ];
}

it('cumple contraste AA en modo claro', function () {
    $paleta = paletaDe(':root');

    expect($paleta)->not->toBeEmpty();

    $fallan = [];

    foreach (paresDeContraste() as [$frente, $fondo, $minimo, $que]) {
        $razon = razonDeContraste($paleta[$frente], $paleta[$fondo]);

        if ($razon < $minimo) {
            $fallan[] = sprintf(
                '%s (%s sobre %s): %.2f:1, mínimo %.1f',
                $que, $frente, $fondo, $razon, $minimo,
            );
        }
    }

    expect($fallan)->toBe([], implode("\n", $fallan));
});

it('cumple contraste AA en modo oscuro', function () {
    $paleta = paletaDe('.dark');

    expect($paleta)->not->toBeEmpty();

    $fallan = [];

    foreach (paresDeContraste() as [$frente, $fondo, $minimo, $que]) {
        $razon = razonDeContraste($paleta[$frente], $paleta[$fondo]);

        if ($razon < $minimo) {
            $fallan[] = sprintf(
                '%s (%s sobre %s): %.2f:1, mínimo %.1f',
                $que, $frente, $fondo, $razon, $minimo,
            );
        }
    }

    expect($fallan)->toBe([], implode("\n", $fallan));
});

it('el botón de borrar se lee en los dos modos', function () {
    // En claro se pinta con el rojo pleno; en oscuro, con `bg-destructive/60`,
    // que lo mezcla con el fondo. Medir el pleno en oscuro daría un resultado
    // que no existe en pantalla.
    $claro = paletaDe(':root');
    $oscuro = paletaDe('.dark');

    $enClaro = razonDeContraste($claro['destructive-foreground'], $claro['destructive']);
    $enOscuro = razonDeContraste(
        $oscuro['destructive-foreground'],
        sobre($oscuro['destructive'], $oscuro['background'], 0.6),
    );

    expect($enClaro)->toBeGreaterThanOrEqual(4.5, sprintf('modo claro: %.2f:1', $enClaro))
        ->and($enOscuro)->toBeGreaterThanOrEqual(4.5, sprintf('modo oscuro: %.2f:1', $enOscuro));
});

it('no deja el anillo de foco translúcido', function () {
    // Un botón primario no tiene borde: el anillo es su único indicador de foco.
    // Al 50% de opacidad daba 2.15:1 sobre blanco, por debajo del 3:1 que pide
    // WCAG 1.4.11. Si alguien vuelve a correr `shadcn add`, esto lo detecta.
    $translucidos = [];

    foreach (glob(resource_path('js/components/ui').'/*/*.{vue,ts}', GLOB_BRACE) ?: [] as $archivo) {
        $contenido = (string) file_get_contents($archivo);

        if (preg_match('/focus-visible:ring-(?:ring|destructive)\/\d+/', $contenido, $m)) {
            $translucidos[] = basename(dirname($archivo)).'/'.basename($archivo).': '.$m[0];
        }
    }

    expect($translucidos)->toBe([], implode("\n", $translucidos));
});
