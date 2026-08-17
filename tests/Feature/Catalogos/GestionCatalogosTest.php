<?php

use App\Models\Alimento;
use App\Models\Medicamento;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Veterinaria;
use App\Models\Veterinario;

it('lista los cinco catálogos con sus totales', function () {
    $usuario = User::factory()->create();
    Veterinaria::factory()->count(2)->create(['usuario_id' => $usuario->id]);
    Vacuna::factory()->semilla()->create(['nombre' => 'Quíntuple']);

    $this->actingAs($usuario)
        ->get(route('catalogos.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('catalogos/Index')
            ->where('totales.veterinarias', 2)
            ->where('totales.vacunas', 1)
            ->where('totales.medicamentos', 0),
        );
});

it('da de alta una veterinaria a nombre de quien la carga', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.veterinarias.store'), [
            'nombre' => 'Veterinaria del Parque',
            'localidad' => 'Caballito',
            'telefono' => '11-4903-0000',
            'urgencias_24h' => 1,
        ])
        ->assertRedirect();

    $veterinaria = Veterinaria::firstWhere('nombre', 'Veterinaria del Parque');

    expect($veterinaria->usuario_id)->toBe($usuario->id)
        ->and($veterinaria->urgencias_24h)->toBeTrue()
        ->and($veterinaria->esSemilla())->toBeFalse();
});

it('completa el protocolo del sitio web cuando el usuario no lo escribe', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.veterinarias.store'), [
        'nombre' => 'Vet Norte',
        'sitio_web' => 'vetnorte.com.ar',
    ]);

    expect(Veterinaria::firstWhere('nombre', 'Vet Norte')->sitio_web)
        ->toBe('https://vetnorte.com.ar');
});

it('edita y da de baja lo propio', function () {
    $usuario = User::factory()->create();
    $medicamento = Medicamento::factory()->create([
        'usuario_id' => $usuario->id,
        'nombre_comercial' => 'Viejo',
    ]);

    $this->actingAs($usuario)
        ->put(route('catalogos.medicamentos.update', $medicamento), [
            'nombre_comercial' => 'Nuevo',
            'categoria' => 'antibiotico',
        ])
        ->assertRedirect();

    expect($medicamento->refresh()->nombre_comercial)->toBe('Nuevo');

    $this->actingAs($usuario)
        ->delete(route('catalogos.medicamentos.destroy', $medicamento))
        ->assertRedirect();

    // Soft delete: lo que ya se registró con este medicamento no pierde
    // la referencia cuando el usuario lo saca de su lista.
    $this->assertSoftDeleted($medicamento);
});

it('asocia un veterinario a una veterinaria propia', function () {
    $usuario = User::factory()->create();
    $veterinaria = Veterinaria::factory()->create(['usuario_id' => $usuario->id]);

    $this->actingAs($usuario)
        ->post(route('catalogos.veterinarios.store'), [
            'nombre' => 'Laura Giménez',
            'matricula' => 'MP 12345',
            'veterinaria_id' => $veterinaria->id,
        ])
        ->assertRedirect();

    expect(Veterinario::firstWhere('nombre', 'Laura Giménez')->veterinaria_id)
        ->toBe($veterinaria->id);
});

it('no deja asociar un veterinario a la veterinaria de otra cuenta', function () {
    $usuario = User::factory()->create();
    $ajena = Veterinaria::factory()->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.veterinarios.store'), [
            'nombre' => 'Intruso',
            'veterinaria_id' => $ajena->id,
        ])
        ->assertSessionHasErrors('veterinaria_id');

    expect(Veterinario::count())->toBe(0);
});

it('el listado de veterinarios trae la veterinaria con eager loading', function () {
    $usuario = User::factory()->create();
    $veterinaria = Veterinaria::factory()->create([
        'usuario_id' => $usuario->id,
        'nombre' => 'Veterinaria del Parque',
    ]);
    Veterinario::factory()->create([
        'usuario_id' => $usuario->id,
        'veterinaria_id' => $veterinaria->id,
        'nombre' => 'Laura Giménez',
    ]);

    $this->actingAs($usuario)
        ->get(route('catalogos.veterinarios.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('catalogos/Veterinarios')
            ->where('registros.0.veterinaria_nombre', 'Veterinaria del Parque')
            ->has('veterinarias', 1),
        );
});

it('exige nombre en todos los catálogos', function (string $ruta, array $datos) {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route("catalogos.{$ruta}.store"), $datos)
        ->assertSessionHasErrors();
})->with([
    ['veterinarias', []],
    ['veterinarios', []],
    ['medicamentos', ['categoria' => 'otro']],
    ['vacunas', ['especie' => 'perro']],
    ['alimentos', ['tipo' => 'balanceado_seco', 'especie' => 'perro', 'etapa' => 'adulto']],
]);

it('acepta un alimento completo', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.alimentos.store'), [
            'marca' => 'Royal Canin',
            'nombre' => 'Medium Adult',
            'tipo' => 'balanceado_seco',
            'gama' => 'super_premium',
            'especie' => 'perro',
            'etapa' => 'adulto',
            'presentacion' => 'Bolsa 15 kg',
            'medicado' => 0,
        ])
        ->assertRedirect();

    expect(Alimento::firstWhere('nombre', 'Medium Adult')->usuario_id)
        ->toBe($usuario->id);
});

it('pide autenticación en todo el módulo', function () {
    $this->get(route('catalogos.index'))->assertRedirect(route('login'));
    $this->get(route('catalogos.vacunas.index'))->assertRedirect(route('login'));
    $this->post(route('catalogos.vacunas.store'), [])->assertRedirect(route('login'));
});
