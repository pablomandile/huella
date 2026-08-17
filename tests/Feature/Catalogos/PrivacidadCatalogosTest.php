<?php

use App\Models\Medicamento;
use App\Models\User;
use App\Models\Veterinaria;

/*
 * Mismo requisito que en mascotas: los datos de una cuenta no se ven ni se
 * tocan desde otra. En los catálogos la única excepción es la semilla del
 * sistema, que es de todos y no es de nadie.
 */

it('no muestra en el listado los registros de otra cuenta', function () {
    $usuario = User::factory()->create();
    $otro = User::factory()->create();

    Veterinaria::factory()->create(['usuario_id' => $usuario->id, 'nombre' => 'La mía']);
    Veterinaria::factory()->create(['usuario_id' => $otro->id, 'nombre' => 'La ajena']);

    $this->actingAs($usuario)
        ->get(route('catalogos.veterinarias.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('registros', 1)
            ->where('registros.0.nombre', 'La mía'),
        );
});

it('no deja editar ni borrar el registro de otra cuenta', function () {
    $intruso = User::factory()->create();
    $veterinaria = Veterinaria::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($intruso)
        ->put(route('catalogos.veterinarias.update', $veterinaria), ['nombre' => 'Hackeada'])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('catalogos.veterinarias.destroy', $veterinaria))
        ->assertForbidden();

    expect($veterinaria->refresh()->nombre)->toBe('Ajena');
    $this->assertNotSoftDeleted($veterinaria);
});

it('no deja duplicar el registro de otra cuenta', function () {
    $intruso = User::factory()->create();
    $medicamento = Medicamento::factory()->create(); // de otro usuario

    $this->actingAs($intruso)
        ->post(route('catalogos.medicamentos.duplicar', $medicamento))
        ->assertForbidden();

    expect(Medicamento::count())->toBe(1);
});

it('el combo de veterinarias del alta de veterinario solo ofrece las propias', function () {
    $usuario = User::factory()->create();
    Veterinaria::factory()->create(['usuario_id' => $usuario->id, 'nombre' => 'La mía']);
    Veterinaria::factory()->create(['nombre' => 'La ajena']);

    $this->actingAs($usuario)
        ->get(route('catalogos.veterinarios.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('veterinarias', 1)
            ->where('veterinarias.0.nombre', 'La mía'),
        );
});
