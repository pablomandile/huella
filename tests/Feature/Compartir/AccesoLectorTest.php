<?php

use App\Enums\EstadoRecordatorio;
use App\Enums\RolCuidador;
use App\Mail\RecordatoriosDelDia;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * Un lector mira la ficha que le compartieron; no se hace cargo de ella.
 *
 * La distinción vive en `User::mascotasACargo()`. Sin ella, todo lo que el
 * proyecto resuelve por `$usuario->mascotas()` —que es la relación del pivote—
 * empieza a incluir mascotas ajenas en cuanto existe el primer lector.
 *
 * El caso grave no es que el lector vea de más: es que **se lleva el aviso del
 * dueño**. Los recordatorios cuelgan de la mascota, no del usuario, y el comando
 * los marca como notificados por id.
 */

/**
 * Una mascota con su dueño y un lector invitado.
 *
 * **El lector se crea primero, y eso importa.** El comando de avisos recorre a
 * los usuarios con `chunkById`, o sea por id ascendente. Con el lector después
 * del dueño, el dueño gana la carrera y se lleva el mail igual aunque la
 * consulta esté mal: el test pasaría sin probar nada. Dándole al lector el id
 * más bajo se fija el orden en el que el bug sí se manifiesta.
 *
 * @return array{0: User, 1: Mascota, 2: User}
 */
function fichaCompartida(): array
{
    $lector = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $duenio = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create(['nombre' => 'Greta']);
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    expect($lector->id)->toBeLessThan($duenio->id);

    return [$duenio, $mascota, $lector];
}

it('no le manda al lector el aviso que es del dueño', function () {
    Mail::fake();

    [$duenio, $mascota, $lector] = fichaCompartida();

    Recordatorio::factory()->for($mascota)->porAvisar()->create([
        'hora_notificacion' => '09:00',
    ]);

    // Mediodía en Buenos Aires: ya pasó la hora de notificación de los dos.
    Carbon::setTestNow(Carbon::parse('2026-08-17 15:00:00'));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    // Un solo mail, y al dueño. Si el lector entrara en la consulta, podría caer
    // primero en el chunkById, llevarse el aviso y dejarlo en "notificado": el
    // dueño no se enteraría nunca de que vence la antirrábica.
    Mail::assertSentCount(1);
    Mail::assertSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($duenio->email));
    Mail::assertNotSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($lector->email));

    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Notificado);

    Carbon::setTestNow();
});

it('no le muestra al lector la medicación de una mascota compartida', function () {
    [$duenio, $mascota, $lector] = fichaCompartida();

    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);
    TomaMedicamento::factory()->for($tratamiento)->create([
        'fecha_hora_programada' => now(),
    ]);

    // El dueño la ve; el lector no. Marcarla le daría 403, así que ofrecérsela
    // sería una lista de cosas para hacer que no puede tocar.
    $this->actingAs($duenio)
        ->get(route('medicacion.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->has('tomas', 1));

    $this->actingAs($lector)
        ->get(route('medicacion.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->has('tomas', 0));
});

it('no le muestra al lector la agenda de una mascota compartida', function () {
    [$duenio, $mascota, $lector] = fichaCompartida();

    Recordatorio::factory()->for($mascota)->porAvisar()->create();

    $this->actingAs($lector)
        ->get(route('recordatorios.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('abiertos', 0)
            // Tampoco en el combo del alta manual: ofrecerla termina en un 403.
            ->has('mascotas', 0),
        );
});

it('no exporta en "mis datos" la mascota que le compartieron', function () {
    [$duenio, $mascota, $lector] = fichaCompartida();
    $propia = Mascota::factory()->for($lector, 'propietario')->create(['nombre' => 'Tobi']);

    $datos = $this->actingAs($lector)
        ->get(route('exportacion.datos'))
        ->assertOk()
        ->json();

    $nombres = collect($datos['mascotas'])->pluck('nombre');

    expect($nombres)->toContain($propia->nombre)
        ->and($nombres)->not->toContain($mascota->nombre);
});

it('el diario de una ficha compartida usa el día del propietario', function () {
    // 00:30 UTC del 18 son las 21:30 del 17 en Buenos Aires.
    Carbon::setTestNow('2026-08-18 00:30:00');

    $duenio = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // El lector mira desde Madrid, donde ya es el 18.
    $lector = User::factory()->create(['zona_horaria' => 'Europe/Madrid']);
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    // Manda la zona de la casa donde vive la mascota, no la de quien mira.
    $this->actingAs($lector)
        ->get(route('mascotas.diario.index', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('hoy', '2026-08-17'));

    Carbon::setTestNow();
});

it('le da al lector la ficha en modo lectura', function () {
    [$duenio, $mascota, $lector] = fichaCompartida();

    $this->actingAs($lector)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('puedeEditar', false)
            ->where('puedeRegistrar', false)
            ->where('mascota.rol', 'lector')
            ->where('mascota.es_propia', false),
        );

    $this->actingAs($duenio)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('puedeEditar', true)
            ->where('mascota.es_propia', true),
        );
});
