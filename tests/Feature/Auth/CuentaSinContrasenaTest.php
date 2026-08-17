<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
 * Quien entra con Google no tiene contraseña, y eso toca todo lo que la pide.
 *
 * El caso que motivó estos tests: la pantalla de seguridad exige reconfirmar la
 * contraseña antes de mostrarse. Para una cuenta sin contraseña esa confirmación
 * no puede pasar nunca, así que quedaba encerrada afuera —y ahí adentro están el
 * 2FA, las llaves de acceso y la posibilidad de definirse una contraseña—.
 */

it('deja entrar a la pantalla de seguridad sin pedir una contraseña que no existe', function () {
    $usuario = User::factory()->create(['password' => null, 'google_id' => '123']);

    $this->actingAs($usuario)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('settings/Security')
            // El formulario cambia: pide definir una contraseña, no cambiarla.
            ->where('tieneContrasena', false),
        );
});

it('le sigue pidiendo confirmación a quien sí tiene contraseña', function () {
    $usuario = User::factory()->create(['password' => Hash::make('la-de-siempre')]);

    // Sin confirmar en esta sesión, Laravel manda a confirmar. Que la cuenta de
    // Google no pida nada no puede aflojar esto para el resto.
    $this->actingAs($usuario)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

it('permite definir una contraseña sin pedir la anterior', function () {
    $usuario = User::factory()->create(['password' => null, 'google_id' => '123']);

    $this->actingAs($usuario)
        ->put(route('user-password.update'), [
            'password' => 'una-contrasena-nueva-larga',
            'password_confirmation' => 'una-contrasena-nueva-larga',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('una-contrasena-nueva-larga', $usuario->refresh()->password))->toBeTrue();
});

it('ahora puede entrar también con email y contraseña', function () {
    // El sentido de definir una contraseña: no quedar atado a Google para
    // siempre.
    $usuario = User::factory()->create(['password' => null, 'google_id' => '123']);

    $this->actingAs($usuario)->put(route('user-password.update'), [
        'password' => 'una-contrasena-nueva-larga',
        'password_confirmation' => 'una-contrasena-nueva-larga',
    ]);

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $usuario->email,
        'password' => 'una-contrasena-nueva-larga',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($usuario->fresh());
});

it('a quien tiene contraseña le sigue exigiendo la actual para cambiarla', function () {
    $usuario = User::factory()->create(['password' => Hash::make('la-de-siempre')]);

    $this->actingAs($usuario)
        ->put(route('user-password.update'), [
            'password' => 'otra-contrasena-larga',
            'password_confirmation' => 'otra-contrasena-larga',
        ])
        ->assertSessionHasErrors('current_password');

    // Y la vieja sigue siendo la válida.
    expect(Hash::check('la-de-siempre', $usuario->refresh()->password))->toBeTrue();
});

it('no deja entrar con contraseña vacía a una cuenta que no tiene ninguna', function () {
    // El agujero que hay que evitar: si `null` se tratara como "cualquier cosa
    // sirve", una cuenta de Google se abriría con la contraseña en blanco.
    $usuario = User::factory()->create(['password' => null, 'google_id' => '123']);

    foreach (['', ' ', 'null'] as $intento) {
        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => $intento,
        ]);

        $this->assertGuest();
    }
});
