<?php

use App\Enums\EstadoToma;
use App\Enums\EstadoTratamiento;
use App\Models\Mascota;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * "Medicación de hoy" es la pantalla que se abre tres veces por día. Lo que
 * importa: que "hoy" sea el día del usuario y no del servidor, que la deuda de
 * días anteriores no desaparezca, y que marcar sea un tap.
 */

it('muestra las tomas de hoy en el día del usuario, no del servidor', function () {
    // 00:30 UTC del 18 son las 21:30 del 17 en Buenos Aires: la toma es de
    // "ayer" para el servidor y de "hoy" para el usuario.
    Carbon::setTestNow('2026-08-18 00:30:00');

    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);

    TomaMedicamento::factory()->for($tratamiento)->create([
        // 21:00 del 17 en Buenos Aires.
        'fecha_hora_programada' => '2026-08-18 00:00:00',
    ]);

    $this->actingAs($usuario)
        ->get(route('medicacion.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('tomas', 1)
            ->where('tomas.0.hora', '21:00')
            ->where('tomas.0.atrasada', false),
        );

    Carbon::setTestNow();
});

it('marca una toma como dada en un solo pedido', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);
    $toma = TomaMedicamento::factory()->for($tratamiento)->create();

    $this->actingAs($usuario)
        ->from(route('medicacion.index'))
        ->patch(route('medicacion.update', $toma), ['estado' => 'administrada'])
        ->assertRedirect(route('medicacion.index'));

    $toma->refresh();

    expect($toma->estado)->toBe(EstadoToma::Administrada)
        ->and($toma->fecha_hora_real)->not->toBeNull();
});

it('deja desmarcar lo que se marcó por error', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);
    $toma = TomaMedicamento::factory()->for($tratamiento)->administrada()->create();

    $this->actingAs($usuario)
        ->patch(route('medicacion.update', $toma), ['estado' => 'pendiente']);

    $toma->refresh();

    // Al volver a pendiente la hora real se limpia: si no se dio, no hay hora.
    expect($toma->estado)->toBe(EstadoToma::Pendiente)
        ->and($toma->fecha_hora_real)->toBeNull();
});

it('muestra las atrasadas de días anteriores separadas de las de hoy', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);

    TomaMedicamento::factory()->for($tratamiento)->create([
        'fecha_hora_programada' => now()->subDays(2),
    ]);
    TomaMedicamento::factory()->for($tratamiento)->create([
        'fecha_hora_programada' => now(),
    ]);

    $this->actingAs($usuario)
        ->get(route('medicacion.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('tomas', 2)
            ->where('tomas.0.atrasada', true)
            ->where('tomas.1.atrasada', false),
        );
});

it('no arrastra deuda de más de una semana', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);

    // Marcar una toma de hace un mes ya no le sirve a nadie.
    TomaMedicamento::factory()->for($tratamiento)->create([
        'fecha_hora_programada' => now()->subMonth(),
    ]);

    $this->actingAs($usuario)
        ->get(route('medicacion.index'))
        ->assertInertia(fn ($pagina) => $pagina->has('tomas', 0));
});

it('finaliza solo los tratamientos cuyo cronograma terminó', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $terminado = Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'fecha_inicio' => now()->subDays(20)->toDateString(),
        'duracion_dias' => 7,
        'estado' => EstadoTratamiento::Activo,
    ]);

    $enCurso = Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'fecha_inicio' => now()->toDateString(),
        'duracion_dias' => 7,
        'estado' => EstadoTratamiento::Activo,
    ]);
    TomaMedicamento::factory()->for($enCurso)->create([
        'fecha_hora_programada' => now()->addDays(2),
    ]);

    $cronico = Tratamiento::factory()->cronico()->create([
        'mascota_id' => $mascota->id,
        'estado' => EstadoTratamiento::Activo,
    ]);

    $this->artisan('huella:cerrar-tratamientos')->assertSuccessful();

    expect($terminado->refresh()->estado)->toBe(EstadoTratamiento::Finalizado)
        ->and($enCurso->refresh()->estado)->toBe(EstadoTratamiento::Activo)
        // Un crónico sin fecha de fin no se cierra solo, aunque el tope de 90
        // días haya dejado de generarle tomas.
        ->and($cronico->refresh()->estado)->toBe(EstadoTratamiento::Activo);
});

it('la ficha de la mascota muestra lo que está tomando ahora', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'medicamento_libre' => 'Cefalexina',
        'estado' => EstadoTratamiento::Activo,
    ]);
    Tratamiento::factory()->finalizado()->create([
        'mascota_id' => $mascota->id,
        'medicamento_libre' => 'Lo de antes',
    ]);

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('tratamientosEnCurso', 1)
            ->where('tratamientosEnCurso.0.nombre_medicamento', 'Cefalexina'),
        );
});
