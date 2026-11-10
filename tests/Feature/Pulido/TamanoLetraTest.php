<?php

declare(strict_types=1);

use App\Enums\TamanoLetra;
use App\Http\Middleware\AceptoTerminos;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * El tamaño de letra lo decide el servidor: cuenta → cookie → default, escrito
 * en el <html> de arranque. Cada caso cuida una decisión que, si se rompe, no
 * da ningún error: la letra simplemente no cambia, o vuelve sola a Mediano.
 */

it('viene en Mediano por defecto, no en el más chico', function (): void {
    expect(TamanoLetra::porDefecto())->toBe(TamanoLetra::Mediano)
        ->and(User::factory()->create()->tamano_letra)->toBe(TamanoLetra::Mediano);
});

/*
 * En % y no en px: un px en la raíz pisa el tamaño que la persona ya puso en su
 * navegador. Y el CSS tiene que decir lo mismo que el enum: son dos archivos
 * que nada ata.
 */
it('expresa los tamaños en porcentaje, iguales en el enum y en el CSS', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    foreach (TamanoLetra::cases() as $caso) {
        expect($caso->porcentaje())->toEndWith('%')
            ->and($css)->toMatch(
                "/html\\[data-tamano-letra='{$caso->value}'\\] \\{\\s*font-size: ".preg_quote($caso->porcentaje(), '/').';/'
            );
    }

    expect(TamanoLetra::Pequeno->porcentaje())->toBe('100%')
        ->and($css)->toMatch('/:root \{\s*font-size: '.preg_quote(TamanoLetra::porDefecto()->porcentaje(), '/').';/')
        ->and($css)->not->toContain('--font-scale');
});

it('escribe el atributo en el html, sin depender de JavaScript', function (): void {
    $html = $this->get('/login')->getContent();

    expect($html)->toContain('data-tamano-letra="mediano"')
        ->not->toContain("localStorage.getItem('tamanoLetra')");
});

it('un invitado puede cambiar la letra y se recuerda por la cookie', function (): void {
    $this->put(route('tamano-letra.update'), ['tamano_letra' => 'grande'])
        ->assertRedirect()
        ->assertCookie('tamano_letra', 'grande', encrypted: false);

    expect($this->withUnencryptedCookie('tamano_letra', 'grande')->get('/login')->getContent())
        ->toContain('data-tamano-letra="grande"');
});

it('a quien tiene sesión se lo guarda en la cuenta', function (): void {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->put(route('tamano-letra.update'), ['tamano_letra' => 'pequeno'])
        ->assertRedirect()
        ->assertCookie('tamano_letra', 'pequeno', encrypted: false);

    expect($usuario->refresh()->tamano_letra)->toBe(TamanoLetra::Pequeno);
});

/*
 * El caso que motiva la columna: un teléfono nuevo, sin la cookie, o con una
 * cookie de otra época. Manda la cuenta.
 */
it('manda la cuenta por encima de la cookie', function (): void {
    $usuario = User::factory()->create();
    $usuario->forceFill(['tamano_letra' => TamanoLetra::Grande])->save();

    $html = $this->actingAs($usuario)
        ->withUnencryptedCookie('tamano_letra', 'pequeno')
        ->get(route('appearance.edit'))
        ->getContent();

    expect($html)->toContain('data-tamano-letra="grande"');
});

/*
 * En la demo la cuenta es una sola y compartida: si se guardara ahí, un
 * visitante que agranda la letra se la agrandaría a todos los demás.
 */
it('en la demo no toca la cuenta compartida: manda la cookie', function (): void {
    config(['demo.enabled' => true]);
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->put(route('tamano-letra.update'), ['tamano_letra' => 'grande'])
        ->assertCookie('tamano_letra', 'grande', encrypted: false);

    expect($usuario->refresh()->tamano_letra)->toBe(TamanoLetra::Mediano);

    $html = $this->actingAs($usuario)
        ->withUnencryptedCookie('tamano_letra', 'pequeno')
        ->get(route('appearance.edit'))
        ->getContent();

    expect($html)->toContain('data-tamano-letra="pequeno"');
});

/*
 * Quien no llega a leer la pantalla de aceptación es quien más necesita
 * agrandar la letra.
 */
it('se puede cambiar sin haber aceptado los términos', function (): void {
    expect(AceptoTerminos::EXENTAS)->toContain('tamano-letra.update');

    $usuario = User::factory()->sinAceptarTerminos()->create();

    $this->actingAs($usuario)
        ->from(route('terminos.aceptar'))
        ->put(route('tamano-letra.update'), ['tamano_letra' => 'grande'])
        ->assertRedirect(route('terminos.aceptar'));

    expect($usuario->refresh()->tamano_letra)->toBe(TamanoLetra::Grande);
});

it('rechaza un tamaño que no existe', function (): void {
    $this->put(route('tamano-letra.update'), ['tamano_letra' => 'gigante'])
        ->assertSessionHasErrors('tamano_letra');
});

it('una cookie con basura cae en el tamaño por defecto', function (): void {
    expect($this->withUnencryptedCookie('tamano_letra', 'jajaja')->get('/login')->getContent())
        ->toContain('data-tamano-letra="mediano"');
});

it('Apariencia recibe los tres tamaños con su escala', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('settings/Appearance')
            ->where('tamanoLetra', 'mediano')
            ->has('tamanosLetra', 3)
            ->has('tamanosLetra.0', fn (AssertableInertia $opcion) => $opcion
                ->where('valor', 'pequeno')
                ->where('escala', 100)
                ->etc()
            )
        );
});
