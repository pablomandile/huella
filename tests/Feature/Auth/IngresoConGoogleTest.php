<?php

use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioDeGoogle;

/*
 * Ingreso con Google.
 *
 * La regla que más importa: **una cuenta por email**. Si Google devuelve un email
 * que ya existe en Huella, es la misma persona y se le vincula la cuenta. Crear
 * una segunda dejaría dos historias clínicas para la misma mascota, cada una
 * invisible desde la otra.
 */

/** Configura las credenciales, como si estuvieran en el .env. */
function conCredencialesDeGoogle(): void
{
    config()->set('services.google.client_id', 'id-de-prueba');
    config()->set('services.google.client_secret', 'secreto-de-prueba');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
}

/**
 * Lo que devuelve Socialite tras el intercambio con Google.
 *
 * @param  array<string, mixed>  $crudo
 */
function respuestaDeGoogle(
    string $id = '1234567890',
    string $email = 'nuevo@gmail.com',
    ?string $nombre = 'Ana Pérez',
    array $crudo = ['verified_email' => true],
): UsuarioDeGoogle {
    $usuario = new UsuarioDeGoogle;
    $usuario->id = $id;
    $usuario->email = $email;
    $usuario->name = $nombre;
    $usuario->user = $crudo;

    return $usuario;
}

/** Deja a Socialite devolviendo esa respuesta, sin salir a la red. */
function socialiteDevuelve(UsuarioDeGoogle $usuario): void
{
    $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $driver->shouldReceive('user')->andReturn($usuario);

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
}

it('esconde la opción mientras no haya credenciales', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    // El front decide con este prop si muestra el botón.
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('googleHabilitado', false));

    // Y las rutas no existen: ofrecer un botón que lleva a un 500 es peor que no
    // ofrecerlo.
    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();
});

it('ofrece la opción cuando está configurada', function () {
    conCredencialesDeGoogle();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('googleHabilitado', true));

    $this->get(route('google.redirect'))
        ->assertRedirectContains('accounts.google.com');
});

it('crea la cuenta la primera vez, sin contraseña y con el email ya verificado', function () {
    conCredencialesDeGoogle();
    socialiteDevuelve(respuestaDeGoogle(email: 'ana@gmail.com', nombre: 'Ana Pérez'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $usuario = User::sole();

    expect($usuario->email)->toBe('ana@gmail.com')
        ->and($usuario->name)->toBe('Ana Pérez')
        ->and($usuario->google_id)->toBe('1234567890')
        // Nunca eligió una contraseña: guardarle una al azar la haría figurar
        // como que puede entrar con email y clave cuando no puede.
        ->and($usuario->password)->toBeNull()
        // Google ya verificó el email; pedirle que confirme el mismo email sería
        // hacerlo esperar un mail para nada.
        ->and($usuario->email_verified_at)->not->toBeNull()
        // Y queda con la zona horaria por defecto, que es la que asume todo el
        // resto del proyecto.
        ->and($usuario->zona_horaria)->toBe('America/Argentina/Buenos_Aires');

    $this->assertAuthenticatedAs($usuario);
});

it('no crea una segunda cuenta para un email que ya existe', function () {
    conCredencialesDeGoogle();

    $existente = User::factory()->create([
        'email' => 'pablo@gmail.com',
        'password' => Hash::make('la-de-siempre'),
    ]);
    $mascota = Mascota::factory()->for($existente, 'propietario')->create();

    socialiteDevuelve(respuestaDeGoogle(email: 'pablo@gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    // Una sola cuenta: la de siempre, ahora también vinculada a Google.
    expect(User::count())->toBe(1);

    $existente->refresh();
    expect($existente->google_id)->toBe('1234567890')
        // Y no perdió la contraseña: puede seguir entrando por email.
        ->and(Hash::check('la-de-siempre', $existente->password))->toBeTrue();

    $this->assertAuthenticatedAs($existente);

    // Lo que importa de verdad: sigue viendo sus mascotas.
    $this->get(route('mascotas.show', $mascota))->assertOk();
});

it('reconoce la cuenta por su id de Google aunque haya cambiado el email', function () {
    conCredencialesDeGoogle();

    $usuario = User::factory()->create([
        'email' => 'viejo@gmail.com',
        'google_id' => '1234567890',
    ]);

    socialiteDevuelve(respuestaDeGoogle(email: 'nuevo@gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    // El id de Google es estable; el email lo cambia el usuario cuando quiere.
    expect(User::count())->toBe(1)
        ->and($usuario->refresh()->email)->toBe('nuevo@gmail.com');
});

it('da por verificado el email de quien se había registrado y no lo había confirmado', function () {
    conCredencialesDeGoogle();

    $usuario = User::factory()->unverified()->create(['email' => 'pendiente@gmail.com']);

    socialiteDevuelve(respuestaDeGoogle(email: 'pendiente@gmail.com'));

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    expect($usuario->refresh()->email_verified_at)->not->toBeNull();
});

it('rechaza una cuenta de Google con el email sin verificar', function () {
    conCredencialesDeGoogle();

    // Sin esta validación, cualquiera podría reclamar la cuenta de otro con solo
    // declarar su dirección de correo.
    socialiteDevuelve(respuestaDeGoogle(
        email: 'ajeno@gmail.com',
        crudo: ['verified_email' => false],
    ));

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(User::count())->toBe(0);
    $this->assertGuest();
});

it('rechaza una respuesta sin email', function () {
    conCredencialesDeGoogle();
    socialiteDevuelve(respuestaDeGoogle(email: ''));

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(User::count())->toBe(0);
});

it('vuelve al login sin cartel de error si el usuario canceló en Google', function () {
    conCredencialesDeGoogle();

    // Cancelar no es un error: no hay nada que avisar.
    $this->get(route('google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionMissing('error');

    expect(User::count())->toBe(0);
});

it('no filtra el detalle técnico cuando Google falla', function () {
    conCredencialesDeGoogle();

    $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $driver->shouldReceive('user')->andThrow(
        new RuntimeException('Client error: 401 con el token AIzaSyDdI0hCZtE6vy'),
    );
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $respuesta = $this->get(route('google.callback'));

    $respuesta->assertRedirect(route('login'));
    // El mensaje de Socialite trae partes de la respuesta de Google: al usuario
    // se le muestra uno propio.
    expect(session('error'))->toBe(
        'No pudimos entrar con Google. Probá de nuevo o usá tu email y contraseña.',
    );
});

it('no rehace el flujo si ya hay una sesión abierta', function () {
    conCredencialesDeGoogle();

    // Alguien con la sesión abierta que vuelve a pasar por acá ya está adentro, y
    // repetir el flujo solo puede terminar cambiándole la cuenta sin querer.
    $this->actingAs(User::factory()->create())
        ->get(route('google.redirect'))
        ->assertRedirect(route('dashboard'));
});
