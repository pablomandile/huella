<?php

use App\Enums\EstadoRecordatorio;
use App\Enums\RolCuidador;
use App\Mail\RecordatoriosDelDia;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\RegistroPeso;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * El cuidador: alguien a quien el dueño le dio permiso para **cargar**, no solo
 * para mirar. Es el caso de quien le da la medicación mientras el dueño viaja.
 *
 * Tiene tres límites que no se mueven: no comparte la ficha con nadie más, no da
 * de baja la mascota, y no se lleva los datos en el JSON de exportación. Y uno
 * que es consecuencia del diseño de los avisos: el mail va solo al dueño.
 */

/**
 * Una mascota con su dueño y un cuidador.
 *
 * El cuidador se crea primero por el mismo motivo que en `AccesoLectorTest`: el
 * comando de avisos recorre a los usuarios por id ascendente, y con el cuidador
 * después del dueño el dueño gana la carrera y el test pasaría sin probar nada.
 *
 * @return array{0: User, 1: Mascota, 2: User}
 */
function fichaConCuidador(): array
{
    $cuidador = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $duenio = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create(['nombre' => 'Greta']);
    $mascota->cuidadores()->attach($cuidador->id, ['rol' => RolCuidador::Cuidador->value]);

    expect($cuidador->id)->toBeLessThan($duenio->id);

    return [$duenio, $mascota, $cuidador];
}

it('deja al cuidador registrar eventos en la ficha', function () {
    [, $mascota, $cuidador] = fichaConCuidador();

    $this->actingAs($cuidador)
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 18.4,
            'fecha' => now()->toDateString(),
            'origen' => 'casa',
        ])
        ->assertSessionHasNoErrors();

    expect(RegistroPeso::sole()->kilos())->toBe(18.4);

    $this->actingAs($cuidador)
        ->post(route('mascotas.entradas.store', $mascota), [
            'fecha' => now()->toDateString(),
            'contenido' => 'Le di la de las 8 sin problema.',
            'categoria' => 'general',
        ])
        ->assertSessionHasNoErrors();

    // Y también la ficha en sí: `update` pasa por `puedeEditar()`.
    $this->actingAs($cuidador)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('puedeEditar', true)
            ->where('puedeRegistrar', true)
            ->where('mascota.rol', 'cuidador')
            // Editar no es ser dueño: la ficha sigue siendo de otro.
            ->where('mascota.es_propia', false),
        );
});

it('le muestra al cuidador la medicación y la agenda de la mascota', function () {
    [, $mascota, $cuidador] = fichaConCuidador();

    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);
    $toma = TomaMedicamento::factory()->for($tratamiento)->create([
        'fecha_hora_programada' => now(),
    ]);
    Recordatorio::factory()->for($mascota)->porAvisar()->create();

    // Es el punto de la feature: el cuidador tiene que ver qué darle hoy.
    $this->actingAs($cuidador)
        ->get(route('medicacion.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->has('tomas', 1));

    $this->actingAs($cuidador)
        ->get(route('recordatorios.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->has('abiertos', 1));

    // Y marcarla, que es la acción de un tap que justifica todo lo demás.
    $this->actingAs($cuidador)
        ->patch(route('medicacion.update', $toma), ['estado' => 'administrada'])
        ->assertSessionHasNoErrors();
});

it('no deja al cuidador compartir la ficha ni darla de baja', function () {
    [$duenio, $mascota, $cuidador] = fichaConCuidador();

    // Compartir es del propietario: si no, el cuidador podría repartir la ficha
    // de otro. `MascotaPolicy::compartir` va por Propietario y no por puedeEditar.
    $this->actingAs($cuidador)
        ->post(route('mascotas.invitaciones.store', $mascota), [
            'email' => 'otro@ejemplo.test',
            'rol' => 'cuidador',
        ])
        ->assertForbidden();

    $this->actingAs($cuidador)
        ->post(route('mascotas.enlaces.store', $mascota), ['vigencia' => '30'])
        ->assertForbidden();

    // Ni sacar del medio al dueño.
    $this->actingAs($cuidador)
        ->delete(route('mascotas.accesos.destroy', [$mascota, $duenio]))
        ->assertForbidden();

    // Ni dar de baja la mascota: eso es solo del propietario.
    $this->actingAs($cuidador)
        ->delete(route('mascotas.destroy', $mascota))
        ->assertForbidden();
});

it('no deja al cuidador ascenderse a sí mismo', function () {
    [, $mascota, $cuidador] = fichaConCuidador();

    // `cambiarAcceso` autoriza por `compartir` y no por `revocarAcceso`: irse uno
    // mismo es legítimo, subirse el permiso no.
    $this->actingAs($cuidador)
        ->patch(route('mascotas.accesos.update', [$mascota, $cuidador]), [
            'rol' => 'cuidador',
        ])
        ->assertForbidden();
});

it('le manda el aviso solo al dueño, aunque haya un cuidador', function () {
    Mail::fake();

    [$duenio, $mascota, $cuidador] = fichaConCuidador();

    Recordatorio::factory()->for($mascota)->porAvisar()->create([
        'hora_notificacion' => '09:00',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-17 15:00:00'));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    /*
     * Un solo mail, y al dueño. No es por ahorrarle correo al cuidador: el
     * recordatorio cuelga de la mascota y el comando lo marca notificado **por
     * id**, así que si los dos entraran en la consulta el que corriera primero se
     * lo llevaría y el otro no lo recibiría nunca. El cuidador ve igual lo que
     * hay que hacer, en la agenda de la app.
     */
    Mail::assertSentCount(1);
    Mail::assertSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($duenio->email));
    Mail::assertNotSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($cuidador->email));

    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Notificado);

    Carbon::setTestNow();
});

it('no le deja al cuidador exportar la mascota que no es suya', function () {
    [, $mascota, $cuidador] = fichaConCuidador();
    $propia = Mascota::factory()->for($cuidador, 'propietario')->create(['nombre' => 'Tobi']);

    // Poder editar no es poder llevarse: el JSON incluye el historial completo y
    // las URLs de descarga de todos los adjuntos.
    $datos = $this->actingAs($cuidador)
        ->get(route('exportacion.datos'))
        ->assertOk()
        ->json();

    $nombres = collect($datos['mascotas'])->pluck('nombre');

    expect($nombres)->toContain($propia->nombre)
        ->and($nombres)->not->toContain($mascota->nombre);
});
