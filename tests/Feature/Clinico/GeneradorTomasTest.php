<?php

use App\Enums\EstadoToma;
use App\Enums\EstadoTratamiento;
use App\Models\Mascota;
use App\Models\Tratamiento;
use App\Models\User;
use App\Services\GeneradorTomasService;

/*
 * "1 comprimido cada 8 horas por 7 días" tiene que volverse 21 tomas con hora,
 * en la zona del usuario, sin pasarse de los 90 días de tope. Y al corregir la
 * posología, lo ya administrado no se puede tocar.
 */

function generador(): GeneradorTomasService
{
    return app(GeneradorTomasService::class);
}

function tratamientoDe(User $usuario, array $atributos = []): Tratamiento
{
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    return Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        ...$atributos,
    ]);
}

it('genera una toma por cada horario del tratamiento', function () {
    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 8,
        'duracion_dias' => 7,
        'fecha_inicio' => '2026-08-17',
        'hora_primera_toma' => '08:00',
    ]);

    $creadas = generador()->generar($tratamiento);

    // 21 y no 20: "cada 8 horas por 7 días" son 21 dosis, las que trae la caja.
    // El cronograma cuenta dosis, no días de almanaque.
    expect($creadas)->toBe(21)
        ->and($tratamiento->tomas()->count())->toBe(21)
        ->and($tratamiento->tomas()->where('estado', EstadoToma::Pendiente)->count())->toBe(21);
});

it('respeta la hora en la zona del usuario, no la del servidor', function () {
    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 24,
        'duracion_dias' => 1,
        'fecha_inicio' => '2026-08-17',
        'hora_primera_toma' => '08:00',
    ]);

    generador()->generar($tratamiento);

    $toma = $tratamiento->tomas()->sole();

    // Buenos Aires es UTC-3: las 8 de la mañana de acá son las 11 UTC.
    expect($toma->fecha_hora_programada->format('Y-m-d H:i'))->toBe('2026-08-17 11:00')
        // Y al usuario se le vuelve a mostrar a las 8, que es lo que anotó.
        ->and($usuario->enSuZona($toma->fecha_hora_programada)->format('H:i'))->toBe('08:00');
});

it('convierte veces por día en un intervalo de horas', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => null,
        'veces_por_dia' => 3,
        'duracion_dias' => 2,
        'hora_primera_toma' => '08:00',
    ]);

    expect($tratamiento->intervaloHoras())->toBe(8)
        ->and(generador()->generar($tratamiento))->toBe(6);
});

it('no genera nada cuando el tratamiento es a demanda', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => null,
        'veces_por_dia' => null,
        'duracion_dias' => null,
        'fecha_fin' => null,
    ]);

    // "Dárselo si le duele" se registra igual, pero sin cronograma:
    // inventarle horarios sería inventar una indicación que nadie dio.
    expect(generador()->generar($tratamiento))->toBe(0)
        ->and($tratamiento->tomas()->count())->toBe(0);
});

it('corta en el tope de 90 días cuando el tratamiento no tiene fin', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 24,
        'duracion_dias' => null,
        'fecha_fin' => null,
    ]);

    expect(generador()->generar($tratamiento))->toBe(GeneradorTomasService::DIAS_MAXIMOS);
});

it('genera las tomas pasadas de un tratamiento cargado con retraso', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 24,
        'duracion_dias' => 5,
        'fecha_inicio' => now()->subDays(3)->toDateString(),
        'hora_primera_toma' => '08:00',
    ]);

    generador()->generar($tratamiento);

    // Quien carga el miércoles lo que arrancó el domingo quiere ver —y poder
    // marcar— lo del fin de semana.
    expect($tratamiento->tomas()->count())->toBe(5)
        ->and($tratamiento->tomas()->where('fecha_hora_programada', '<', now())->count())
        ->toBeGreaterThan(0);
});

it('al regenerar no toca lo administrado ni lo omitido', function () {
    /*
     * El reloj se congela al mediodía porque el test necesita **tres** tomas ya
     * pasadas, y cuántas hay depende de la hora: arrancando ayer a las 08:00 cada
     * 8 horas, a las 00:30 de hoy solo pasaron dos. Al mediodía pasaron cuatro,
     * y a esa hora UTC y Buenos Aires coinciden en el día de calendario.
     */
    $this->travelTo(now()->setTime(12, 0));

    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 8,
        'duracion_dias' => 7,
        'fecha_inicio' => now()->subDay()->toDateString(),
        'hora_primera_toma' => '08:00',
    ]);

    generador()->generar($tratamiento);

    // Se marcan dos como dadas y una como salteada.
    $pasadas = $tratamiento->tomas()
        ->where('fecha_hora_programada', '<', now())
        ->orderBy('fecha_hora_programada')
        ->take(3)
        ->get();

    expect($pasadas)->toHaveCount(3);

    $pasadas[0]->update(['estado' => EstadoToma::Administrada, 'fecha_hora_real' => now()]);
    $pasadas[1]->update(['estado' => EstadoToma::Administrada, 'fecha_hora_real' => now()]);
    $pasadas[2]->update(['estado' => EstadoToma::Omitida]);

    // Ahora el veterinario cambia la frecuencia a cada 12 horas.
    $tratamiento->update(['frecuencia_horas' => 12]);
    generador()->regenerar($tratamiento->fresh());

    $tratamiento->refresh();

    expect($tratamiento->tomas()->where('estado', EstadoToma::Administrada)->count())->toBe(2)
        ->and($tratamiento->tomas()->where('estado', EstadoToma::Omitida)->count())->toBe(1);
});

it('al regenerar conserva las pendientes vencidas: son una deuda real', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 8,
        'duracion_dias' => 7,
        'fecha_inicio' => now()->subDays(2)->toDateString(),
        'hora_primera_toma' => '08:00',
    ]);

    generador()->generar($tratamiento);

    $vencidasAntes = $tratamiento->tomas()
        ->where('estado', EstadoToma::Pendiente)
        ->where('fecha_hora_programada', '<', now())
        ->count();

    expect($vencidasAntes)->toBeGreaterThan(0);

    $tratamiento->update(['frecuencia_horas' => 24]);
    generador()->regenerar($tratamiento->fresh());

    $vencidasDespues = $tratamiento->tomas()
        ->where('estado', EstadoToma::Pendiente)
        ->where('fecha_hora_programada', '<', now())
        ->count();

    // Borrarlas sería falsear la adherencia: "no le di la de ayer" es un dato.
    expect($vencidasDespues)->toBe($vencidasAntes);
});

it('al regenerar reemplaza las futuras pendientes por el cronograma nuevo', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 24,
        'duracion_dias' => 10,
        'fecha_inicio' => now()->toDateString(),
        'hora_primera_toma' => '08:00',
    ]);

    generador()->generar($tratamiento);
    $futurasAntes = $tratamiento->tomas()->where('fecha_hora_programada', '>', now())->count();

    $tratamiento->update(['frecuencia_horas' => 8]);
    generador()->regenerar($tratamiento->fresh());

    $futurasDespues = $tratamiento->tomas()->where('fecha_hora_programada', '>', now())->count();

    // Tres veces por día en vez de una: tiene que haber muchas más.
    expect($futurasDespues)->toBeGreaterThan($futurasAntes);
});

it('un tratamiento suspendido no recibe tomas nuevas', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 8,
        'duracion_dias' => 7,
        'fecha_inicio' => now()->toDateString(),
    ]);

    generador()->generar($tratamiento);

    $tratamiento->update(['estado' => EstadoTratamiento::Suspendido]);

    expect(generador()->regenerar($tratamiento->fresh()))->toBe(0)
        // Y las que quedaban por delante se limpian: ya no hay que darlas.
        ->and($tratamiento->tomas()->where('fecha_hora_programada', '>', now())->count())->toBe(0);
});

it('no duplica tomas si se regenera dos veces sin cambios', function () {
    $usuario = User::factory()->create();
    $tratamiento = tratamientoDe($usuario, [
        'frecuencia_horas' => 12,
        'duracion_dias' => 5,
        'fecha_inicio' => now()->toDateString(),
        'hora_primera_toma' => '08:00',
    ]);

    generador()->generar($tratamiento);
    $total = $tratamiento->tomas()->count();

    generador()->regenerar($tratamiento->fresh());
    generador()->regenerar($tratamiento->fresh());

    expect($tratamiento->tomas()->count())->toBe($total);
});
