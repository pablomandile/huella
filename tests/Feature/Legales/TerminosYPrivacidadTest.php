<?php

use App\Console\Commands\PurgarCuentasEliminadas;
use App\Http\Middleware\AceptoTerminos;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * Términos y privacidad: una versión vigente en `config('legales.version')`, la
 * constancia de quién la aceptó en `users`, y el middleware `AceptoTerminos`
 * que frena a quien no la aceptó, salvo para lo que tiene que poder hacer igual:
 * leer los textos, salir, llevarse sus datos y borrar su cuenta.
 */

function datosDeRegistro(array $extra = []): array
{
    return [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$extra,
    ];
}

it('no deja registrarse sin aceptar', function () {
    $this->post(route('register.store'), datosDeRegistro())
        ->assertSessionHasErrors('terminos');

    $this->assertGuest();
});

it('al registrarse con la casilla deja la constancia y no vuelve a preguntar', function () {
    $this->post(route('register.store'), datosDeRegistro(['terminos' => '1']));

    $usuario = User::where('email', 'ana@example.com')->firstOrFail();

    expect($usuario->terminos_version)->toBe(config('legales.version'))
        ->and($usuario->terminos_aceptados_en)->not->toBeNull();

    $usuario->markEmailAsVerified();
    $this->actingAs($usuario)->get(route('dashboard'))->assertOk();
});

it('a una cuenta sin aceptar la manda a aceptar, y después vuelve a donde iba', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();

    $this->actingAs($usuario)
        ->get(route('mascotas.index'))
        ->assertRedirect(route('terminos.aceptar'));

    $this->get(route('terminos.aceptar'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('terminos/Aceptar')
            ->where('primeraVez', true)
        );

    $this->post(route('terminos.aceptar.store'), ['terminos' => '1'])
        ->assertRedirect(route('mascotas.index'));

    expect($usuario->fresh()->aceptoTerminosVigentes())->toBeTrue();
});

it('aceptar exige marcar la casilla', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();

    $this->actingAs($usuario)
        ->post(route('terminos.aceptar.store'))
        ->assertSessionHasErrors('terminos');

    expect($usuario->fresh()->terminos_version)->toBeNull();
});

it('subir la versión vuelve a pedir la aceptación', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario)->get(route('dashboard'))->assertOk();

    config()->set('legales.version', '2099-01-01');

    $this->get(route('dashboard'))->assertRedirect(route('terminos.aceptar'));
    $this->get(route('terminos.aceptar'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('primeraVez', false));
});

it('sin aceptar igual puede leer, salir, llevarse sus datos y borrar la cuenta', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();
    $this->actingAs($usuario);

    foreach (['terminos', 'privacidad', 'cookies', 'acerca'] as $ruta) {
        $this->get(route($ruta))->assertOk();
    }

    $this->get(route('exportacion.datos'))->assertOk();
    $this->postJson(route('sincronizar'), ['ops' => []])->assertStatus(422);

    $this->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');
    expect($usuario->fresh()->trashed())->toBeTrue();
});

it('un pedido JSON sin aceptar recibe 409 y no una redirección', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();

    $this->actingAs($usuario)
        ->getJson(route('dashboard'))
        ->assertStatus(409);
});

it('las exentas son solo las que se listan: el resto de las rutas con sesión quedan cubiertas', function () {
    // El middleware va en todo el grupo web, así que cubre solo una ruta nueva.
    // Lo que se cuida acá es que la lista de exentas no crezca sin querer:
    // cada nombre exento tiene que ser uno de estos, a propósito.
    $exentasConSesion = collect(Route::getRoutes())
        ->filter(fn ($r) => collect($r->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || str_starts_with($m, 'auth:')))
        ->map(fn ($r) => $r->getName())
        ->filter(fn ($nombre) => $nombre !== null && Str::is(AceptoTerminos::EXENTAS, $nombre))
        ->sort()
        ->values()
        ->all();

    expect($exentasConSesion)->toBe([
        'exportacion.datos',
        'logout',
        'profile.destroy',
        'sincronizar',
        'terminos.aceptar',
        'terminos.aceptar.store',
        'verification.notice',
        'verification.send',
        'verification.verify',
    ]);
});

it('las páginas de términos y privacidad se abren con sesión y sin ella', function (string $ruta) {
    $this->get(route($ruta))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Legal')
            ->where('auth.user', null)
            ->has('html')
        );

    $this->actingAs(User::factory()->create())
        ->get(route($ruta))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Legal'));
})->with(['terminos', 'privacidad']);

it('la política promete el plazo de purga que aplica el comando', function () {
    $politica = (string) file_get_contents(resource_path('legales/privacidad.md'));

    expect($politica)->toContain('**30 días**')
        ->and(PurgarCuentasEliminadas::DIAS)->toBe(30);
});

it('Perfil ofrece descargar los datos, que es lo que promete la política', function () {
    $perfil = (string) file_get_contents(resource_path('js/pages/settings/Profile.vue'));

    expect($perfil)->toContain('misDatos().url')
        ->and((string) file_get_contents(resource_path('legales/privacidad.md')))
        ->toContain('Descargar mis datos');
});

it('el service worker no guarda una respuesta Inertia que llegó redirigida', function () {
    // Un pedido a /dashboard redirigido a aceptar los términos vuelve 200 con
    // X-Inertia: sin esto se guardaría como la copia del inicio, y sin conexión
    // el inicio mostraría la pantalla de aceptación.
    $sw = (string) file_get_contents(public_path('sw.js'));

    expect($sw)->toMatch('/!parcial &&\s*!respuesta\.redirected &&\s*respuesta\.status === 200/');
});

it('el precalentado sin aceptar recibe 409 y no pisa a dónde volver', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();
    $this->actingAs($usuario);

    $this->get(route('mascotas.index'))->assertRedirect(route('terminos.aceptar'));
    $this->get(route('dashboard'), ['X-Precalentado' => '1', 'X-Inertia' => 'true'])->assertStatus(409);

    $this->post(route('terminos.aceptar.store'), ['terminos' => '1'])
        ->assertRedirect(route('mascotas.index'));
});
