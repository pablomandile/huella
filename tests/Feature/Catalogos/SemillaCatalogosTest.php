<?php

use App\Models\User;
use App\Models\Vacuna;
use Database\Seeders\CatalogosSeeder;

/*
 * Regla de negocio 4: los catálogos con `usuario_id` NULL son semilla
 * compartida. No se editan: se duplican.
 *
 * Si se pudieran editar, alguien le cambiaría los meses de refuerzo a la
 * antirrábica y se los cambiaría a todos los demás usuarios.
 */

it('muestra la semilla del sistema a cualquier usuario', function () {
    $usuario = User::factory()->create();
    Vacuna::factory()->semilla()->create(['nombre' => 'Antirrábica']);

    $this->actingAs($usuario)
        ->get(route('catalogos.vacunas.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('registros', 1)
            ->where('registros.0.nombre', 'Antirrábica')
            ->where('registros.0.es_semilla', true),
        );
});

it('no deja editar ni borrar un registro semilla', function () {
    $usuario = User::factory()->create();
    $vacuna = Vacuna::factory()->semilla()->create([
        'nombre' => 'Antirrábica',
        'meses_refuerzo' => 12,
    ]);

    $this->actingAs($usuario)
        ->put(route('catalogos.vacunas.update', $vacuna), [
            'nombre' => 'Antirrábica',
            'especie' => 'perro',
            'meses_refuerzo' => 60,
        ])
        ->assertForbidden();

    $this->actingAs($usuario)
        ->delete(route('catalogos.vacunas.destroy', $vacuna))
        ->assertForbidden();

    expect($vacuna->refresh()->meses_refuerzo)->toBe(12);
    $this->assertNotSoftDeleted($vacuna);
});

it('duplica un semilla en una copia propia y editable', function () {
    $usuario = User::factory()->create();
    $original = Vacuna::factory()->semilla()->create([
        'nombre' => 'Quíntuple',
        'especie' => 'perro',
        'meses_refuerzo' => 12,
    ]);

    $this->actingAs($usuario)
        ->post(route('catalogos.vacunas.duplicar', $original))
        ->assertRedirect();

    $copia = Vacuna::where('usuario_id', $usuario->id)->sole();

    expect($copia->nombre)->toBe('Quíntuple (copia)')
        ->and($copia->especie)->toBe($original->especie)
        ->and($copia->meses_refuerzo)->toBe(12)
        ->and($copia->esSemilla())->toBeFalse();

    // Y ahora sí se puede editar, sin tocar el original.
    $this->actingAs($usuario)
        ->put(route('catalogos.vacunas.update', $copia), [
            'nombre' => 'Quíntuple de mi veterinaria',
            'especie' => 'perro',
            'meses_refuerzo' => 24,
        ])
        ->assertRedirect();

    expect($copia->refresh()->meses_refuerzo)->toBe(24)
        ->and($original->refresh()->meses_refuerzo)->toBe(12);
});

it('cada usuario ve su copia y nadie ve la del otro', function () {
    $uno = User::factory()->create();
    $otro = User::factory()->create();
    $semilla = Vacuna::factory()->semilla()->create(['nombre' => 'Quíntuple']);

    $this->actingAs($uno)->post(route('catalogos.vacunas.duplicar', $semilla));

    // El otro ve la semilla, no la copia ajena.
    $this->actingAs($otro)
        ->get(route('catalogos.vacunas.index'))
        ->assertInertia(fn ($pagina) => $pagina->has('registros', 1));

    $this->actingAs($uno)
        ->get(route('catalogos.vacunas.index'))
        ->assertInertia(fn ($pagina) => $pagina->has('registros', 2));
});

it('la semilla argentina se carga y se puede volver a correr sin duplicar', function () {
    $this->seed(CatalogosSeeder::class);

    $antirrabicas = Vacuna::where('nombre', 'Antirrábica')->whereNull('usuario_id')->get();

    // Una por especie: la de perro y la de gato son planes distintos.
    expect($antirrabicas)->toHaveCount(2)
        ->and($antirrabicas->every(fn ($v) => $v->obligatoria))->toBeTrue();

    $totalAntes = Vacuna::count();

    $this->seed(CatalogosSeeder::class);

    expect(Vacuna::count())->toBe($totalAntes);
});
