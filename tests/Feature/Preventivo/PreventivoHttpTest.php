<?php

use App\Enums\EstadoRecordatorio;
use App\Models\AplicacionVacuna;
use App\Models\Desparasitacion;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Veterinaria;

it('registra una vacuna con próxima dosis y deja el recordatorio listo', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    $quintuple = Vacuna::factory()->semilla()->create([
        'nombre' => 'Quíntuple',
        'meses_refuerzo' => 12,
    ]);

    $this->actingAs($usuario)
        ->from(route('mascotas.preventivo.index', $mascota))
        ->post(route('mascotas.vacunas.store', $mascota), [
            'vacuna_id' => $quintuple->id,
            'fecha' => now()->subDay()->toDateString(),
            'proxima_dosis' => now()->addYear()->toDateString(),
            'dosis_nro' => 1,
            'marca' => 'Nobivac',
            'lote' => 'L4523',
        ])
        ->assertRedirect();

    $aplicacion = AplicacionVacuna::sole();

    expect($aplicacion->nombre_vacuna)->toBe('Quíntuple')
        ->and($aplicacion->mascota_id)->toBe($mascota->id);

    // El recordatorio lo generó el observer, no el controlador.
    $recordatorio = Recordatorio::sole();
    expect($recordatorio->titulo)->toBe('Refuerzo de Quíntuple para Greta')
        ->and($recordatorio->estado)->toBe(EstadoRecordatorio::Pendiente);
});

it('no acepta una aplicación con fecha futura', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    // Para lo que viene está la próxima dosis, no la fecha de aplicación.
    $this->actingAs($usuario)
        ->post(route('mascotas.vacunas.store', $mascota), [
            'vacuna_libre' => 'Antirrábica',
            'fecha' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('fecha');
});

it('exige que la próxima dosis sea posterior a la aplicación', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.vacunas.store', $mascota), [
            'vacuna_libre' => 'Antirrábica',
            'fecha' => now()->toDateString(),
            'proxima_dosis' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('proxima_dosis');
});

it('rechaza una vacuna sin nombre', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.vacunas.store', $mascota), [
            'fecha' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('vacuna_libre');
});

it('registra una desparasitación con el peso del día', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.desparasitaciones.store', $mascota), [
            'medicamento_libre' => 'Drontal Plus',
            'tipo' => 'interna',
            'fecha' => now()->toDateString(),
            'dosis' => '1 comprimido',
            'peso_al_momento' => 12.5,
            'proxima_fecha' => now()->addMonths(3)->toDateString(),
        ])
        ->assertRedirect();

    $desparasitacion = Desparasitacion::sole();

    expect($desparasitacion->peso_al_momento)->toBe('12.50')
        ->and(Recordatorio::sole()->tipo->value)->toBe('desparasitacion');
});

it('muestra el semáforo de vacunación en la pantalla de preventivo', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'vacuna_libre' => 'Antirrábica',
        'fecha' => now()->subYear()->toDateString(),
        'proxima_dosis' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($usuario)
        ->get(route('mascotas.preventivo.index', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('preventivo/Index')
            ->where('estadoVacunacion.estado', 'vencida')
            ->where('estadoVacunacion.detalle', fn ($d) => str_contains($d, 'Antirrábica')),
        );
});

it('el semáforo dice sin datos cuando no hay nada cargado', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    // No decide qué vacunas le corresponden: eso sería aconsejar.
    $this->actingAs($usuario)
        ->get(route('mascotas.preventivo.index', $mascota))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('estadoVacunacion.estado', 'sin_datos'),
        );
});

it('el semáforo avisa cuando falta poco', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'fecha' => now()->subYear()->toDateString(),
        'proxima_dosis' => now()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($usuario)
        ->get(route('mascotas.preventivo.index', $mascota))
        ->assertInertia(fn ($pagina) => $pagina->where('estadoVacunacion.estado', 'proxima'));
});

it('lista la bandeja con lo abierto de todas las mascotas', function () {
    $usuario = User::factory()->create();
    $greta = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    $simon = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Simón']);
    $ajena = Mascota::factory()->create();

    Recordatorio::factory()->for($greta)->create(['titulo' => 'De Greta']);
    Recordatorio::factory()->for($simon)->create(['titulo' => 'De Simón']);
    Recordatorio::factory()->for($ajena)->create(['titulo' => 'Ajeno']);
    Recordatorio::factory()->for($greta)->completado()->create(['titulo' => 'Ya hecho']);

    $this->actingAs($usuario)
        ->get(route('recordatorios.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('recordatorios/Index')
            ->has('abiertos', 2)
            ->has('resueltos', 1)
            ->has('mascotas', 2),
        );
});

it('marca un recordatorio como hecho', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $recordatorio = Recordatorio::factory()->for($mascota)->create();

    $this->actingAs($usuario)
        ->patch(route('recordatorios.resolver', $recordatorio), ['accion' => 'completar'])
        ->assertRedirect();

    $recordatorio->refresh();

    expect($recordatorio->estado)->toBe(EstadoRecordatorio::Completado)
        ->and($recordatorio->fecha_completado)->not->toBeNull();
});

it('pospone un recordatorio y vuelve a quedar pendiente de aviso', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $recordatorio = Recordatorio::factory()->for($mascota)->notificado()->create([
        'fecha_objetivo' => '2026-09-01',
    ]);

    $this->actingAs($usuario)
        ->patch(route('recordatorios.resolver', $recordatorio), [
            'accion' => 'posponer',
            'dias' => 7,
        ])
        ->assertRedirect();

    $recordatorio->refresh();

    expect($recordatorio->fecha_objetivo->toDateString())->toBe('2026-09-08')
        // Se corrió la fecha: hay que volver a avisar.
        ->and($recordatorio->estado)->toBe(EstadoRecordatorio::Pendiente);
});

it('un recurrente completado se corre al siguiente intervalo', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $recordatorio = Recordatorio::factory()->for($mascota)->create([
        'fecha_objetivo' => '2026-09-01',
        'recurrente' => true,
        'intervalo_dias' => 90,
    ]);

    $this->actingAs($usuario)
        ->patch(route('recordatorios.resolver', $recordatorio), ['accion' => 'completar']);

    $recordatorio->refresh();

    // No desaparece: para eso sirve marcarlo recurrente.
    expect($recordatorio->estado)->toBe(EstadoRecordatorio::Pendiente)
        ->and($recordatorio->fecha_objetivo->toDateString())->toBe('2026-11-30');
});

it('vuelve a abrir lo que se resolvió por error', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $recordatorio = Recordatorio::factory()->for($mascota)->completado()->create();

    $this->actingAs($usuario)
        ->patch(route('recordatorios.reabrir', $recordatorio))
        ->assertRedirect();

    $recordatorio->refresh();

    expect($recordatorio->estado)->toBe(EstadoRecordatorio::Pendiente)
        ->and($recordatorio->fecha_completado)->toBeNull();
});

it('da de alta un recordatorio propio', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.recordatorios.store', $mascota), [
            'titulo' => 'Cortarle las uñas',
            'fecha_objetivo' => now()->addWeek()->toDateString(),
            'dias_anticipacion' => 2,
        ])
        ->assertRedirect();

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->titulo)->toBe('Cortarle las uñas')
        ->and($recordatorio->tipo->value)->toBe('personalizado')
        ->and($recordatorio->tipo->esAutomatico())->toBeFalse();
});

it('no deja borrar un recordatorio automático', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => now()->addYear()->toDateString(),
    ]);

    $recordatorio = Recordatorio::sole();

    // Volvería a nacer de su origen: sería un botón que no hace nada. Para
    // esos está descartar.
    $this->actingAs($usuario)
        ->delete(route('recordatorios.destroy', $recordatorio))
        ->assertForbidden();

    expect(Recordatorio::count())->toBe(1);
});

it('borra un recordatorio propio', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $recordatorio = Recordatorio::factory()->for($mascota)->create();

    $this->actingAs($usuario)
        ->delete(route('recordatorios.destroy', $recordatorio))
        ->assertRedirect();

    expect(Recordatorio::count())->toBe(0);
});

it('no deja tocar el preventivo ni los recordatorios de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();
    $aplicacion = AplicacionVacuna::factory()->create(['mascota_id' => $mascota->id]);
    $recordatorio = Recordatorio::factory()->for($mascota)->create();

    $this->actingAs($intruso)
        ->get(route('mascotas.preventivo.index', $mascota))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->post(route('mascotas.vacunas.store', $mascota), [
            'vacuna_libre' => 'Intrusa',
            'fecha' => now()->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('mascotas.vacunas.destroy', [$mascota, $aplicacion]))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->patch(route('recordatorios.resolver', $recordatorio), ['accion' => 'completar'])
        ->assertForbidden();

    expect($recordatorio->refresh()->estado)->toBe(EstadoRecordatorio::Pendiente);
});

it('no deja referenciar la veterinaria de otra cuenta al vacunar', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $ajena = Veterinaria::factory()->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.vacunas.store', $mascota), [
            'vacuna_libre' => 'Antirrábica',
            'fecha' => now()->toDateString(),
            'veterinaria_id' => $ajena->id,
        ])
        ->assertSessionHasErrors('veterinaria_id');
});

it('una mascota fallecida no recibe vacunas nuevas', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create([
        'fecha_fallecimiento' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($usuario)
        ->post(route('mascotas.vacunas.store', $mascota), [
            'vacuna_libre' => 'Antirrábica',
            'fecha' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('la ficha muestra el semáforo y lo que hay que agendar', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'vacuna_libre' => 'Quíntuple',
        'fecha' => now()->subYear()->toDateString(),
        'proxima_dosis' => now()->addDays(20)->toDateString(),
    ]);

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('estadoVacunacion.estado', 'proxima')
            ->has('recordatorios', 1)
            ->where('recordatorios.0.tipo', 'vacuna'),
        );
});

it('pide autenticación en todo el módulo', function () {
    $this->get(route('recordatorios.index'))->assertRedirect(route('login'));
});
