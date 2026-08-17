<?php

use App\Enums\EstadoRecordatorio;
use App\Enums\TipoRecordatorio;
use App\Models\AplicacionVacuna;
use App\Models\Desparasitacion;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Visita;
use App\Services\GeneradorRecordatoriosService;

/*
 * Los recordatorios los generan los observers, nunca un controlador. Eso hace
 * que valga igual si el registro entra por el formulario, por un seeder o por
 * un import futuro: lo que se prueba acá es el modelo, sin pasar por HTTP.
 *
 * La clave de idempotencia es origen_type + origen_id + tipo.
 */

function mascotaDe(?User $usuario = null, array $atributos = []): Mascota
{
    return Mascota::factory()
        ->for($usuario ?? User::factory()->create(), 'propietario')
        ->create($atributos);
}

it('crea el recordatorio al cargar una vacuna con refuerzo al año', function () {
    $mascota = mascotaDe(null, ['nombre' => 'Greta']);
    $quintuple = Vacuna::factory()->semilla()->create([
        'nombre' => 'Quíntuple',
        'meses_refuerzo' => 12,
    ]);

    $aplicacion = AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'vacuna_id' => $quintuple->id,
        'vacuna_libre' => null,
        'fecha' => '2026-08-17',
        'proxima_dosis' => '2027-08-17',
    ]);

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->tipo)->toBe(TipoRecordatorio::Vacuna)
        ->and($recordatorio->titulo)->toBe('Refuerzo de Quíntuple para Greta')
        ->and($recordatorio->fecha_objetivo->toDateString())->toBe('2027-08-17')
        ->and($recordatorio->estado)->toBe(EstadoRecordatorio::Pendiente)
        ->and($recordatorio->origen_id)->toBe($aplicacion->id)
        ->and($recordatorio->origen_type)->toBe(AplicacionVacuna::class)
        // Una vacuna avisa con 15 días: hay que conseguir turno.
        ->and($recordatorio->dias_anticipacion)->toBe(15);
});

it('no duplica el recordatorio al volver a guardar el mismo origen', function () {
    $mascota = mascotaDe();
    $aplicacion = AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => now()->addYear()->toDateString(),
    ]);

    $aplicacion->touch();
    $aplicacion->update(['lote' => 'L123']);
    $aplicacion->save();

    expect(Recordatorio::count())->toBe(1);
});

it('mueve el recordatorio y vuelve a avisar si cambia la fecha', function () {
    $mascota = mascotaDe();
    $aplicacion = AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => '2027-08-17',
    ]);

    // Ya se mandó el mail de la fecha anterior.
    Recordatorio::sole()->update(['estado' => EstadoRecordatorio::Notificado]);

    $aplicacion->update(['proxima_dosis' => '2027-10-01']);

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->fecha_objetivo->toDateString())->toBe('2027-10-01')
        // La fecha se movió: hay que avisar de nuevo.
        ->and($recordatorio->estado)->toBe(EstadoRecordatorio::Pendiente);
});

it('descarta el recordatorio si se borra la próxima dosis', function () {
    $mascota = mascotaDe();
    $aplicacion = AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => now()->addYear()->toDateString(),
    ]);

    $aplicacion->update(['proxima_dosis' => null]);

    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Descartado);
});

it('respeta lo que el usuario ya resolvió', function () {
    $mascota = mascotaDe();
    $aplicacion = AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => '2027-08-17',
    ]);

    // El usuario dio la dosis y lo marcó como hecho.
    Recordatorio::sole()->update([
        'estado' => EstadoRecordatorio::Completado,
        'fecha_completado' => now(),
    ]);

    // Después corrige el lote de la aplicación: eso no puede resucitarlo.
    $aplicacion->update(['lote' => 'L999']);

    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Completado);
});

it('descarta el recordatorio si se da de baja la aplicación', function () {
    $mascota = mascotaDe();
    $aplicacion = AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => now()->addYear()->toDateString(),
    ]);

    $aplicacion->delete();

    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Descartado);
});

it('genera el recordatorio de una desparasitación', function () {
    $mascota = mascotaDe(null, ['nombre' => 'Simón']);

    Desparasitacion::factory()->create([
        'mascota_id' => $mascota->id,
        'fecha' => '2026-08-17',
        'proxima_fecha' => '2026-11-17',
        'tipo' => 'interna',
    ]);

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->tipo)->toBe(TipoRecordatorio::Desparasitacion)
        ->and($recordatorio->titulo)->toBe('Desparasitar a Simón')
        ->and($recordatorio->dias_anticipacion)->toBe(7);
});

it('genera el recordatorio del próximo control de una visita', function () {
    $mascota = mascotaDe(null, ['nombre' => 'Greta']);

    Visita::factory()->for($mascota)->create([
        'motivo' => 'Gastroenteritis',
        'proximo_control' => now()->addDays(10)->toDateString(),
    ]);

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->tipo)->toBe(TipoRecordatorio::Control)
        ->and($recordatorio->titulo)->toBe('Control de Greta')
        ->and($recordatorio->descripcion)->toContain('Gastroenteritis');
});

it('genera el recordatorio del vencimiento del seguro', function () {
    $mascota = mascotaDe(null, [
        'nombre' => 'Greta',
        'seguro_compania' => 'Mascotas Seguras',
        'seguro_vencimiento' => now()->addMonths(6)->toDateString(),
    ]);

    $recordatorio = Recordatorio::where('tipo', TipoRecordatorio::Seguro)->sole();

    expect($recordatorio->titulo)->toBe('Vence el seguro de Greta')
        ->and($recordatorio->descripcion)->toBe('Mascotas Seguras')
        ->and($recordatorio->mascota_id)->toBe($mascota->id);
});

it('descarta los recordatorios de celo al marcar la castración', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaDe($usuario, ['sexo' => 'hembra', 'castrado' => false]);

    // Un recordatorio de celo (los generará el estimador en la fase 6) y uno
    // de vacuna, para comprobar que solo se va el que corresponde.
    Recordatorio::factory()->for($mascota)->create(['tipo' => TipoRecordatorio::Celo]);
    Recordatorio::factory()->for($mascota)->create(['tipo' => TipoRecordatorio::Vacuna]);

    $mascota->update(['castrado' => true]);

    expect(Recordatorio::where('tipo', TipoRecordatorio::Celo)->sole()->estado)
        ->toBe(EstadoRecordatorio::Descartado)
        ->and(Recordatorio::where('tipo', TipoRecordatorio::Vacuna)->sole()->estado)
        ->toBe(EstadoRecordatorio::Pendiente);
});

it('descarta todos los recordatorios al marcar el fallecimiento', function () {
    $mascota = mascotaDe();
    Recordatorio::factory()->for($mascota)->create(['tipo' => TipoRecordatorio::Vacuna]);
    Recordatorio::factory()->for($mascota)->create(['tipo' => TipoRecordatorio::Control]);

    $mascota->update(['fecha_fallecimiento' => now()->toDateString()]);

    // Modo lectura: conserva todo su historial, pero no puede seguir avisando
    // que le toca la antirrábica.
    expect(Recordatorio::whereIn('estado', EstadoRecordatorio::abiertos())->count())->toBe(0);
});

it('no crea recordatorios nuevos para una mascota fallecida', function () {
    $mascota = mascotaDe(null, ['fecha_fallecimiento' => now()->subMonth()->toDateString()]);

    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => now()->addYear()->toDateString(),
    ]);

    expect(Recordatorio::count())->toBe(0);
});

it('usa los días de anticipación de celo que eligió el usuario', function () {
    $usuario = User::factory()->create(['dias_anticipacion_celo' => 21]);
    $mascota = mascotaDe($usuario);

    $generador = app(GeneradorRecordatoriosService::class);
    $recordatorio = $generador->sincronizar(
        origen: $mascota,
        mascota: $mascota,
        tipo: TipoRecordatorio::Celo,
        fecha: now()->addMonths(3),
        titulo: 'Próximo celo',
    );

    expect($recordatorio->dias_anticipacion)->toBe(21);
});
