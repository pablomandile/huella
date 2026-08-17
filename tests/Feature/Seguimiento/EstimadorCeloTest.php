<?php

use App\Enums\TipoRecordatorio;
use App\Models\CicloCelo;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\User;
use App\Services\EstimadorCeloService;

/*
 * El criterio de la fase: con tres ciclos cargados, la estimación usa el
 * promedio real y no el valor por defecto.
 *
 * Y algo igual de importante: la estimación viaja siempre con su nivel de
 * confianza. Una fecha sola se lee como un dato, y esto es un promedio.
 */

function hembraEntera(?User $usuario = null): Mascota
{
    return Mascota::factory()
        ->for($usuario ?? User::factory()->create(), 'propietario')
        ->hembra()
        ->entera()
        ->create(['nombre' => 'Greta']);
}

function estimador(): EstimadorCeloService
{
    return app(EstimadorCeloService::class);
}

it('con tres ciclos usa el promedio real y no los 180 por defecto', function () {
    $mascota = hembraEntera();

    // Intervalos de 200 y 220 días: el promedio es 210, no 180.
    CicloCelo::factory()->empezoEl('2025-01-10')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2025-07-29')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2026-03-06')->create(['mascota_id' => $mascota->id]);

    $estimacion = estimador()->para($mascota->fresh());

    expect($estimacion['intervalos'])->toBe([200, 220])
        ->and($estimacion['dias_promedio'])->toBe(210)
        ->and($estimacion['usa_promedio_real'])->toBeTrue()
        ->and($estimacion['dias_promedio'])->not->toBe(EstimadorCeloService::DIAS_POR_DEFECTO)
        // Sobre el último inicio, no sobre el primero.
        ->and($estimacion['fecha']->toDateString())->toBe('2026-10-02')
        ->and($estimacion['confianza'])->toBe('media')
        ->and($estimacion['detalle'])->toContain('200, 220');
});

it('sin ciclos no estima fecha y lo dice', function () {
    $mascota = hembraEntera();

    $estimacion = estimador()->para($mascota);

    expect($estimacion['fecha'])->toBeNull()
        ->and($estimacion['usa_promedio_real'])->toBeFalse()
        ->and($estimacion['confianza'])->toBe('muy_baja')
        ->and($estimacion['detalle'])->toContain('Todavía no cargaste ningún ciclo');
});

it('con un solo ciclo cae al valor de referencia y avisa que no es de ella', function () {
    $mascota = hembraEntera();
    CicloCelo::factory()->empezoEl('2026-03-01')->create(['mascota_id' => $mascota->id]);

    $estimacion = estimador()->para($mascota->fresh());

    // Un solo ciclo no da ningún intervalo que promediar.
    expect($estimacion['intervalos'])->toBe([])
        ->and($estimacion['dias_promedio'])->toBe(EstimadorCeloService::DIAS_POR_DEFECTO)
        ->and($estimacion['usa_promedio_real'])->toBeFalse()
        ->and($estimacion['confianza'])->toBe('muy_baja')
        ->and($estimacion['confianza_etiqueta'])->toBe('Valor de referencia, no de ella')
        ->and($estimacion['fecha']->toDateString())->toBe('2026-08-28');
});

it('con dos ciclos ya usa el intervalo real, con confianza baja', function () {
    $mascota = hembraEntera();
    CicloCelo::factory()->empezoEl('2025-09-01')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2026-04-01')->create(['mascota_id' => $mascota->id]);

    $estimacion = estimador()->para($mascota->fresh());

    expect($estimacion['intervalos'])->toBe([212])
        ->and($estimacion['dias_promedio'])->toBe(212)
        ->and($estimacion['usa_promedio_real'])->toBeTrue()
        // Un solo intervalo alcanza para dejar de inventar, no para confiar.
        ->and($estimacion['confianza'])->toBe('baja');
});

it('el observer guarda la estimación y crea el recordatorio', function () {
    $usuario = User::factory()->create(['dias_anticipacion_celo' => 21]);
    $mascota = hembraEntera($usuario);

    CicloCelo::factory()->empezoEl('2025-06-01')->create(['mascota_id' => $mascota->id]);
    $ultimo = CicloCelo::factory()->empezoEl('2026-01-01')->create(['mascota_id' => $mascota->id]);

    $recordatorio = Recordatorio::where('tipo', TipoRecordatorio::Celo)->abiertos()->sole();

    expect($ultimo->fresh()->proxima_estimada)->not->toBeNull()
        ->and($recordatorio->titulo)->toBe('Se estima el próximo celo de Greta')
        // El nivel de confianza va en la descripción del recordatorio.
        ->and($recordatorio->descripcion)->toContain('Promedio')
        // Y respeta la anticipación que eligió el usuario.
        ->and($recordatorio->dias_anticipacion)->toBe(21);
});

it('el recordatorio de celo cuelga del ciclo más reciente', function () {
    $mascota = hembraEntera();

    CicloCelo::factory()->empezoEl('2025-06-01')->create(['mascota_id' => $mascota->id]);
    $segundo = CicloCelo::factory()->empezoEl('2026-01-01')->create(['mascota_id' => $mascota->id]);

    // Cargar un ciclo viejo que faltaba mejora el promedio, pero la estimación
    // sigue saliendo del más reciente.
    CicloCelo::factory()->empezoEl('2024-11-01')->create(['mascota_id' => $mascota->id]);

    // El anterior no se borra: queda descartado, y el abierto es el del
    // ciclo más reciente.
    $recordatorio = Recordatorio::where('tipo', TipoRecordatorio::Celo)->abiertos()->sole();

    expect($recordatorio->origen_id)->toBe($segundo->id);
});

it('calcula la duración del ciclo al cerrarlo', function () {
    $mascota = hembraEntera();

    $ciclo = CicloCelo::factory()->enCurso()->create([
        'mascota_id' => $mascota->id,
        'fecha_inicio' => '2026-08-01',
    ]);

    expect($ciclo->duracion_dias)->toBeNull()
        ->and($ciclo->estaEnCurso())->toBeTrue();

    $ciclo->update(['fecha_fin' => '2026-08-20']);

    // Inclusivo: del 1 al 20 son 20 días, no 19.
    expect($ciclo->fresh()->duracion_dias)->toBe(20);
});

it('una mascota castrada no genera estimación ni recordatorio', function () {
    $mascota = Mascota::factory()
        ->for(User::factory()->create(), 'propietario')
        ->hembra()
        ->castrada()
        ->create();

    CicloCelo::factory()->empezoEl('2026-01-01')->create(['mascota_id' => $mascota->id]);

    // Regla de negocio 2: castrada, no hay celo que estimar.
    expect(Recordatorio::where('tipo', TipoRecordatorio::Celo)->count())->toBe(0);
});

it('al castrar después se descartan los recordatorios de celo ya creados', function () {
    $mascota = hembraEntera();
    CicloCelo::factory()->empezoEl('2025-06-01')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2026-01-01')->create(['mascota_id' => $mascota->id]);

    expect(Recordatorio::where('tipo', TipoRecordatorio::Celo)->abiertos()->count())->toBe(1);

    $mascota->update(['castrado' => true]);

    expect(Recordatorio::where('tipo', TipoRecordatorio::Celo)->abiertos()->count())->toBe(0)
        ->and($mascota->fresh()->celo_visible)->toBeFalse();
});

it('al borrar un ciclo se recalcula la estimación', function () {
    $mascota = hembraEntera();
    CicloCelo::factory()->empezoEl('2025-01-10')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2025-07-29')->create(['mascota_id' => $mascota->id]);
    $ultimo = CicloCelo::factory()->empezoEl('2026-03-06')->create(['mascota_id' => $mascota->id]);

    expect(estimador()->para($mascota->fresh())['dias_promedio'])->toBe(210);

    $ultimo->delete();

    // Sin el tercero queda un solo intervalo: 200.
    expect(estimador()->para($mascota->fresh())['dias_promedio'])->toBe(200);
});

it('marca la estimación como vencida si la fecha ya pasó', function () {
    $mascota = hembraEntera();

    // Dos ciclos viejos: la estimación cae en el pasado porque el celo ocurrió
    // y nunca se cargó.
    CicloCelo::factory()->empezoEl('2024-01-01')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2024-07-01')->create(['mascota_id' => $mascota->id]);

    $estimacion = estimador()->para($mascota->fresh());

    expect($estimacion['vencida'])->toBeTrue()
        // Y el texto lo dice, en vez de anunciar como "próximo" algo que pasó.
        ->and($estimacion['detalle'])->toContain('La fecha estimada ya pasó');
});

it('no marca como vencida una estimación futura', function () {
    $mascota = hembraEntera();

    CicloCelo::factory()->empezoEl(now()->subMonths(14)->toDateString())
        ->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl(now()->subMonths(4)->toDateString())
        ->create(['mascota_id' => $mascota->id]);

    $estimacion = estimador()->para($mascota->fresh());

    expect($estimacion['vencida'])->toBeFalse()
        ->and($estimacion['detalle'])->not->toContain('ya pasó');
});
