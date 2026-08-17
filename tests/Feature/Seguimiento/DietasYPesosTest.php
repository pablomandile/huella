<?php

use App\Models\Alimento;
use App\Models\Dieta;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\User;
use App\Services\DietaService;
use Illuminate\Database\UniqueConstraintViolationException;

/*
 * Regla de negocio 1: una sola dieta vigente por mascota. Al iniciar una nueva
 * se cierra la anterior con la fecha del día anterior, dentro de una
 * transacción.
 *
 * La base no puede garantizarlo sola —MySQL admite múltiples NULL en un índice
 * único—, así que esto es la única defensa y conviene tenerla bien cubierta.
 */

function servicioDietas(): DietaService
{
    return app(DietaService::class);
}

function mascotaConUsuario(): Mascota
{
    return Mascota::factory()->for(User::factory()->create(), 'propietario')->create();
}

it('cierra la dieta anterior al iniciar una nueva', function () {
    $mascota = mascotaConUsuario();
    $balanceado = Alimento::factory()->semilla()->create(['nombre' => 'Adulto']);
    $renal = Alimento::factory()->semilla()->create(['nombre' => 'Renal']);

    $primera = servicioDietas()->iniciar($mascota, [
        'alimento_id' => $balanceado->id,
        'fecha_inicio' => '2026-01-01',
        'racion_diaria_g' => 300,
    ]);

    $segunda = servicioDietas()->iniciar($mascota, [
        'alimento_id' => $renal->id,
        'fecha_inicio' => '2026-08-17',
        'racion_diaria_g' => 250,
    ]);

    // La anterior se cierra el día antes de que empiece la nueva.
    expect($primera->fresh()->fecha_fin->toDateString())->toBe('2026-08-16')
        ->and($segunda->fresh()->estaVigente())->toBeTrue()
        ->and($mascota->dietas()->vigente()->count())->toBe(1);

    servicioDietas()->verificarUnicaVigente($mascota);
});

it('nunca deja dos dietas vigentes, ni con varios cambios seguidos', function () {
    $mascota = mascotaConUsuario();

    foreach (range(1, 5) as $mes) {
        servicioDietas()->iniciar($mascota, [
            'alimento_id' => Alimento::factory()->semilla()->create()->id,
            'fecha_inicio' => sprintf('2026-%02d-01', $mes),
        ]);
    }

    expect($mascota->dietas()->count())->toBe(5)
        ->and($mascota->dietas()->vigente()->count())->toBe(1)
        // La vigente es la última que arrancó.
        ->and($mascota->dietas()->vigente()->sole()->fecha_inicio->toDateString())
        ->toBe('2026-05-01');

    servicioDietas()->verificarUnicaVigente($mascota);
});

it('si la nueva arranca el mismo día que la anterior, la anterior duró un día', function () {
    $mascota = mascotaConUsuario();

    $primera = servicioDietas()->iniciar($mascota, [
        'alimento_id' => Alimento::factory()->semilla()->create()->id,
        'fecha_inicio' => '2026-08-17',
    ]);

    servicioDietas()->iniciar($mascota, [
        'alimento_id' => Alimento::factory()->semilla()->create()->id,
        'fecha_inicio' => '2026-08-17',
    ]);

    // Cerrarla "el día anterior" le daría una fecha de fin previa a su inicio.
    expect($primera->fresh()->fecha_fin->toDateString())->toBe('2026-08-17');
});

it('la dieta de una mascota no cierra la de otra', function () {
    $unaMascota = mascotaConUsuario();
    $otraMascota = mascotaConUsuario();

    $deUna = servicioDietas()->iniciar($unaMascota, [
        'alimento_id' => Alimento::factory()->semilla()->create()->id,
        'fecha_inicio' => '2026-01-01',
    ]);

    servicioDietas()->iniciar($otraMascota, [
        'alimento_id' => Alimento::factory()->semilla()->create()->id,
        'fecha_inicio' => '2026-08-17',
    ]);

    expect($deUna->fresh()->estaVigente())->toBeTrue();
});

it('al reabrir una dieta editándola, cierra las demás', function () {
    $mascota = mascotaConUsuario();

    $vieja = Dieta::factory()->create([
        'mascota_id' => $mascota->id,
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-06-30',
    ]);
    $actual = servicioDietas()->iniciar($mascota, [
        'alimento_id' => Alimento::factory()->semilla()->create()->id,
        'fecha_inicio' => '2026-07-01',
    ]);

    // El usuario se dio cuenta de que la vieja no había terminado.
    servicioDietas()->actualizar($vieja, ['fecha_fin' => null]);

    expect($mascota->dietas()->vigente()->count())->toBe(1)
        ->and($vieja->fresh()->estaVigente())->toBeTrue()
        ->and($actual->fresh()->estaVigente())->toBeFalse();

    servicioDietas()->verificarUnicaVigente($mascota);
});

it('la dieta vigente es la que se lee desde la mascota', function () {
    $mascota = mascotaConUsuario();
    $renal = Alimento::factory()->semilla()->create(['nombre' => 'Renal']);

    Dieta::factory()->cerrada()->create(['mascota_id' => $mascota->id]);
    servicioDietas()->iniciar($mascota, [
        'alimento_id' => $renal->id,
        'fecha_inicio' => now()->toDateString(),
    ]);

    expect($mascota->fresh()->dietaVigente->alimento->nombre)->toBe('Renal');
});

/* --------------------------------------------------------------------- peso */

it('guarda un peso y lo devuelve en orden cronológico', function () {
    $mascota = mascotaConUsuario();

    RegistroPeso::factory()->elDia('2026-03-01', 18.4)->create(['mascota_id' => $mascota->id]);
    RegistroPeso::factory()->elDia('2026-01-01', 17.2)->create(['mascota_id' => $mascota->id]);
    RegistroPeso::factory()->elDia('2026-06-01', 19.0)->create(['mascota_id' => $mascota->id]);

    // La curva se dibuja en orden: la relación ya lo garantiza.
    expect($mascota->pesos->pluck('fecha')->map->toDateString()->all())
        ->toBe(['2026-01-01', '2026-03-01', '2026-06-01'])
        ->and($mascota->fresh()->ultimoPeso->kilos())->toBe(19.0);
});

it('no admite dos pesos del mismo día y del mismo origen', function () {
    $mascota = mascotaConUsuario();

    RegistroPeso::factory()->elDia('2026-08-17', 18.4)->create(['mascota_id' => $mascota->id]);

    // Dos mediciones de la misma balanza el mismo día son una corrección, no
    // dos datos: el índice único lo impide.
    expect(fn () => RegistroPeso::factory()
        ->elDia('2026-08-17', 18.9)
        ->create(['mascota_id' => $mascota->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('sí admite el mismo día en casa y en la veterinaria', function () {
    $mascota = mascotaConUsuario();

    RegistroPeso::factory()->elDia('2026-08-17', 18.4)->create(['mascota_id' => $mascota->id]);
    RegistroPeso::factory()
        ->elDia('2026-08-17', 19.1)
        ->enVeterinaria()
        ->create(['mascota_id' => $mascota->id]);

    // Las dos balanzas no coinciden, y ese es justamente el dato.
    expect($mascota->pesos()->count())->toBe(2);
});
