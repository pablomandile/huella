<?php

use App\Models\EnlaceCompartido;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
 * La demo es una sola cuenta compartida por todos los que la prueban. Lo que la
 * protege es ProtegerDemo; acá se fija que actúe con el flag, y que sin el flag
 * —la productiva, que corre el mismo código— no cambie nada.
 */

it('sin la demo activa no cambia nada', function () {
    config(['demo.enabled' => false]);

    $this->get('/register')->assertOk();
    $this->get('/login')->assertInertia(fn ($pagina) => $pagina->where('demo', null));
});

it('en la demo no hay registro ni recuperar la clave', function () {
    config(['demo.enabled' => true]);

    $this->get('/register')->assertRedirect(route('login'));
    $this->post('/register', [
        'name' => 'Intruso',
        'email' => 'intruso@example.com',
        'password' => 'una-clave-larga',
        'password_confirmation' => 'una-clave-larga',
    ])->assertRedirect(route('login'));
    $this->get('/forgot-password')->assertRedirect(route('login'));
    $this->post('/forgot-password', ['email' => config('demo.email')])->assertRedirect(route('login'));

    expect(User::where('email', 'intruso@example.com')->exists())->toBeFalse();
});

it('el login muestra los datos de la demo', function () {
    config(['demo.enabled' => true]);

    $this->get('/login')->assertInertia(fn ($pagina) => $pagina
        ->where('demo.email', config('demo.email'))
        ->where('demo.password', config('demo.password')));
});

it('la cuenta compartida no se puede cambiar', function () {
    config(['demo.enabled' => true]);
    $usuario = User::factory()->create(['password' => 'demo1234']);

    $this->actingAs($usuario)
        ->from('/settings/profile')
        ->patch('/settings/profile', ['name' => 'Otro', 'email' => 'otro@example.com'])
        ->assertRedirect('/settings/profile');

    $this->actingAs($usuario)
        ->delete('/settings/profile', ['password' => 'demo1234']);

    $this->actingAs($usuario)
        ->put('/settings/password', [
            'current_password' => 'demo1234',
            'password' => 'nueva-clave-larga',
            'password_confirmation' => 'nueva-clave-larga',
        ]);

    $this->actingAs($usuario)->post('/user/two-factor-authentication');

    $usuario->refresh();

    expect($usuario->email)->not->toBe('otro@example.com')
        ->and($usuario->trashed())->toBeFalse()
        ->and(Hash::check('demo1234', $usuario->password))->toBeTrue()
        ->and($usuario->two_factor_secret)->toBeNull();
});

it('nada sale hacia afuera: ni invitaciones ni enlaces públicos', function () {
    config(['demo.enabled' => true]);
    $mascota = Mascota::factory()->create();
    $duenio = $mascota->propietario;

    $this->actingAs($duenio)
        ->post(route('mascotas.enlaces.store', $mascota), ['vigencia' => '30'])
        ->assertRedirect();

    $this->actingAs($duenio)
        ->post(route('mascotas.invitaciones.store', $mascota), ['email' => 'alguien@example.com', 'rol' => 'lector'])
        ->assertRedirect();

    expect(EnlaceCompartido::count())->toBe(0);
});

it('en la demo se sigue pudiendo usar la app', function () {
    config(['demo.enabled' => true]);
    $mascota = Mascota::factory()->create();

    $this->actingAs($mascota->propietario)
        ->post(route('mascotas.pesos.store', $mascota), [
            'fecha' => now()->toDateString(),
            'peso_kg' => '12.5',
            'origen' => 'casa',
        ])
        ->assertSessionHasNoErrors();

    expect($mascota->pesos()->count())->toBe(1);
});
