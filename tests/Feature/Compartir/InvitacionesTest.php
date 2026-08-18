<?php

use App\Enums\RolCuidador;
use App\Mail\InvitacionAMascota;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
 * Invitar a alguien a mirar la ficha de una mascota.
 *
 * No hay tabla de invitaciones: la invitación **es** una URL firmada y el pivote
 * `mascota_usuario` es la única fuente de verdad del acceso. Lo que hay que
 * proteger, entonces, es la firma —que lleva el email adentro— y el momento del
 * `attach`, que es lo único que escribe autorización.
 */

/** La URL firmada tal como la arma el controlador. Sirve para el GET y el POST. */
function invitacionPara(Mascota $mascota, string $email, string $rol = 'lector'): string
{
    return URL::temporarySignedRoute(
        'invitaciones.mostrar',
        now()->addDays(7),
        ['mascota' => $mascota->id, 'email' => mb_strtolower($email), 'rol' => $rol],
    );
}

it('le manda la invitación al correo que eligió el dueño', function () {
    Mail::fake();

    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create(['nombre' => 'Greta']);

    $this->actingAs($duenio)
        ->post(route('mascotas.invitaciones.store', $mascota), ['email' => 'Ana@Ejemplo.test', 'rol' => 'lector'])
        ->assertRedirect();

    Mail::assertSent(
        InvitacionAMascota::class,
        fn ($mail) => $mail->hasTo('ana@ejemplo.test'),
    );

    // Todavía nadie tiene acceso: el mail no concede nada por sí solo.
    expect($mascota->fresh()->cuidadores)->toHaveCount(1);
});

it('no deja invitar a quien no es propietario', function () {
    Mail::fake();

    $duenio = User::factory()->create();
    $lector = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    // Ni un invitado puede re-compartir la mascota de otro, ni un extraño.
    foreach ([$lector, $intruso] as $quien) {
        $this->actingAs($quien)
            ->post(route('mascotas.invitaciones.store', $mascota), ['email' => 'otro@ejemplo.test', 'rol' => 'lector'])
            ->assertForbidden();
    }

    Mail::assertNothingSent();
});

it('sí deja compartir la ficha de una mascota fallecida', function () {
    Mail::fake();

    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create([
        'fecha_fallecimiento' => now()->subMonth()->toDateString(),
    ]);

    // `compartir` va por Propietario y no por `registrarEventos`: la ficha de una
    // mascota que se murió es justo una de las que uno quiere poder mandar.
    $this->actingAs($duenio)
        ->post(route('mascotas.invitaciones.store', $mascota), ['email' => 'ana@ejemplo.test', 'rol' => 'lector'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('rechaza una invitación sin firma o con la firma vencida', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // Sin firma.
    $this->actingAs($invitado)
        ->get(route('invitaciones.mostrar', ['mascota' => $mascota, 'email' => 'ana@ejemplo.test']))
        ->assertForbidden();

    $url = invitacionPara($mascota, 'ana@ejemplo.test');

    $this->travel(8)->days();

    $this->actingAs($invitado)->get($url)->assertForbidden();
    $this->actingAs($invitado)->post($url)->assertForbidden();

    expect($mascota->fresh()->cuidadores)->toHaveCount(1);
});

it('no le da el acceso a quien entró con otra cuenta', function () {
    $duenio = User::factory()->create();
    $otro = User::factory()->create(['email' => 'quien-sea@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $url = invitacionPara($mascota, 'ana@ejemplo.test');

    // Ve la pantalla, pero se le dice que la invitación no es suya.
    $this->actingAs($otro)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('invitaciones/Aceptar')
            ->where('estado', 'otra_cuenta'),
        );

    // Y el POST no concede nada: reenviar el mail no regala el acceso.
    $this->actingAs($otro)->post($url)->assertForbidden();

    expect($mascota->fresh()->cuidadores)->toHaveCount(1);
});

it('no le da el acceso a quien todavía no verificó su email', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->unverified()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $url = invitacionPara($mascota, 'ana@ejemplo.test');

    $this->actingAs($invitado)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('estado', 'sin_verificar'));

    // Sin esto, cualquiera podría registrarse declarando el email de otro.
    $this->actingAs($invitado)->post($url)->assertForbidden();

    expect($mascota->fresh()->cuidadores)->toHaveCount(1);
});

it('muestra la invitación sin sesión y sin ningún dato clínico', function () {
    $duenio = User::factory()->create(['name' => 'Pablo']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create([
        'nombre' => 'Greta',
        'microchip' => '900123456789012',
    ]);

    $respuesta = $this->get(invitacionPara($mascota, 'ana@ejemplo.test'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('estado', 'sin_sesion')
            ->where('invitadoPor', 'Pablo')
            ->where('mascota.nombre', 'Greta')
            // Cualquiera con el enlace llega hasta acá: no puede ver el
            // historial ni los datos de contacto del dueño.
            ->missing('visitas')
            ->missing('mascota.microchip'),
        );

    $respuesta->assertDontSee($duenio->email);
    $respuesta->assertDontSee('900123456789012');
});

it('concede el acceso como lector y nada más', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $url = invitacionPara($mascota, 'ana@ejemplo.test');

    // El rol sale de la URL **firmada**, no del cuerpo: mandar `propietario` en
    // el POST no cambia nada.
    $this->actingAs($invitado)
        ->post($url, ['rol' => 'propietario'])
        ->assertRedirect(route('mascotas.show', $mascota));

    $this->assertDatabaseHas('mascota_usuario', [
        'mascota_id' => $mascota->id,
        'usuario_id' => $invitado->id,
        'rol' => 'lector',
    ]);

    $this->actingAs($invitado)->get(route('mascotas.show', $mascota))->assertOk();
});

it('no degrada al propietario que abre su propia invitación', function () {
    $duenio = User::factory()->create(['email' => 'pablo@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $url = invitacionPara($mascota, 'pablo@ejemplo.test');

    // El riesgo real es `syncWithoutDetaching`, que ACTUALIZA los atributos del
    // pivote cuando la fila ya existe: el dueño quedaría de lector de su propia
    // mascota, y el unique(mascota_id, usuario_id) no lo frenaría porque no
    // inserta. Se acepta dos veces para que el descuido salte.
    $this->actingAs($duenio)->post($url)->assertRedirect();
    $this->actingAs($duenio)->post($url)->assertRedirect();

    expect($mascota->fresh()->cuidadores)->toHaveCount(1)
        ->and($mascota->fresh()->rolDe($duenio))->toBe(RolCuidador::Propietario);

    $this->actingAs($duenio)
        ->put(route('mascotas.update', $mascota), [
            'nombre' => 'Greta',
            'especie' => 'perro',
            'sexo' => 'hembra',
        ])
        ->assertRedirect();
});

it('deja al dueño revocar el acceso', function () {
    $duenio = User::factory()->create();
    $lector = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    $this->actingAs($duenio)
        ->delete(route('mascotas.accesos.destroy', [$mascota, $lector]))
        ->assertRedirect();

    $this->actingAs($lector)->get(route('mascotas.show', $mascota))->assertForbidden();
});

it('deja al invitado irse solo, y no deja sacar al propietario', function () {
    $duenio = User::factory()->create();
    $lector = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    // Un lector no saca a nadie más...
    $this->actingAs($lector)
        ->delete(route('mascotas.accesos.destroy', [$mascota, $duenio]))
        ->assertForbidden();

    // ...ni siquiera el propio dueño se puede sacar a sí mismo: dejaría la
    // mascota sin nadie que pueda volver a entrar.
    $this->actingAs($duenio)
        ->delete(route('mascotas.accesos.destroy', [$mascota, $duenio]))
        ->assertForbidden();

    // Pero irse solo sí.
    $this->actingAs($lector)
        ->delete(route('mascotas.accesos.destroy', [$mascota, $lector]))
        ->assertRedirect(route('mascotas.index'));

    expect($mascota->fresh()->cuidadores)->toHaveCount(1);
});

it('no deja invitar dos veces a la misma persona', function () {
    Mail::fake();

    $duenio = User::factory()->create();
    $lector = User::factory()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    $this->actingAs($duenio)
        ->post(route('mascotas.invitaciones.store', $mascota), ['email' => 'ana@ejemplo.test', 'rol' => 'lector'])
        ->assertSessionHasErrors('email');

    // Ni a uno mismo: la ficha ya es suya.
    $this->actingAs($duenio)
        ->post(route('mascotas.invitaciones.store', $mascota), ['email' => $duenio->email, 'rol' => 'lector'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

it('no revela si el correo invitado tiene cuenta en Huella', function () {
    Mail::fake();

    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    User::factory()->create(['email' => 'registrada@ejemplo.test']);

    // La respuesta tiene que ser la misma exista o no la cuenta, o el formulario
    // se convierte en un detector de usuarios de Huella.
    $respuestas = collect(['registrada@ejemplo.test', 'desconocida@ejemplo.test'])
        ->map(fn (string $email) => $this->actingAs($duenio)
            ->post(route('mascotas.invitaciones.store', $mascota), ['email' => $email, 'rol' => 'lector'])
            ->assertSessionHasNoErrors()
            ->getSession()
            ->get('success'),
        );

    expect($respuestas[0])->toBe('Le mandamos la invitación a registrada@ejemplo.test.')
        ->and($respuestas[1])->toBe('Le mandamos la invitación a desconocida@ejemplo.test.');

    // Y las dos mandan mail: tampoco hay un canal por tiempo de respuesta.
    Mail::assertSentCount(2);
});

it('concede el rol de cuidador cuando el dueño lo eligió', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($invitado)
        ->post(invitacionPara($mascota, 'ana@ejemplo.test', 'cuidador'))
        ->assertRedirect(route('mascotas.show', $mascota));

    expect($mascota->fresh()->rolDe($invitado))->toBe(RolCuidador::Cuidador);
});

it('no deja invitar a nadie como propietario', function () {
    Mail::fake();

    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // La regla es una lista blanca de dos casos, no "cualquiera menos uno": la
    // propiedad de una ficha no se regala por un formulario.
    $this->actingAs($duenio)
        ->post(route('mascotas.invitaciones.store', $mascota), [
            'email' => 'ana@ejemplo.test',
            'rol' => 'propietario',
        ])
        ->assertSessionHasErrors('rol');

    Mail::assertNothingSent();
});

it('invalida la invitación si le tocan el rol a la URL', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // El rol viaja firmado: ascenderse cambiando la query rompe la firma entera.
    $url = str_replace('rol=lector', 'rol=cuidador', invitacionPara($mascota, 'ana@ejemplo.test'));

    $this->actingAs($invitado)->get($url)->assertForbidden();
    $this->actingAs($invitado)->post($url)->assertForbidden();

    expect($mascota->fresh()->cuidadores)->toHaveCount(1);
});

it('cae a lector si la invitación no dice ningún rol', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->create(['email' => 'ana@ejemplo.test']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // Una invitación firmada antes de que el rol existiera en la URL sigue
    // siendo válida, y concede lo que menos puede.
    $url = URL::temporarySignedRoute(
        'invitaciones.mostrar',
        now()->addDays(7),
        ['mascota' => $mascota->id, 'email' => 'ana@ejemplo.test'],
    );

    $this->actingAs($invitado)->post($url)->assertRedirect();

    expect($mascota->fresh()->rolDe($invitado))->toBe(RolCuidador::Lector);
});

it('deja al dueño cambiarle el permiso a quien ya tiene acceso', function () {
    $duenio = User::factory()->create();
    $invitado = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($invitado->id, ['rol' => RolCuidador::Lector->value]);

    // Sin esto habría que sacarlo, volver a invitarlo y que acepte de nuevo.
    $this->actingAs($duenio)
        ->patch(route('mascotas.accesos.update', [$mascota, $invitado]), ['rol' => 'cuidador'])
        ->assertRedirect();

    expect($mascota->fresh()->rolDe($invitado))->toBe(RolCuidador::Cuidador);

    // Y volver atrás.
    $this->actingAs($duenio)
        ->patch(route('mascotas.accesos.update', [$mascota, $invitado]), ['rol' => 'lector'])
        ->assertRedirect();

    expect($mascota->fresh()->rolDe($invitado))->toBe(RolCuidador::Lector);

    // Sigue habiendo una sola fila: `updateExistingPivot`, no un `attach` que
    // chocaría contra el unique.
    expect($mascota->fresh()->cuidadores)->toHaveCount(2);
});

it('no deja tocarle el rol al propietario', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // Ni siquiera él mismo: se quedaría sin su propia ficha y sin nadie que
    // pueda devolvérsela.
    $this->actingAs($duenio)
        ->patch(route('mascotas.accesos.update', [$mascota, $duenio]), ['rol' => 'lector'])
        ->assertForbidden();

    expect($mascota->fresh()->rolDe($duenio))->toBe(RolCuidador::Propietario);
});
