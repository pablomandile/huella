<?php

use App\Models\User;
use App\Models\Veterinaria;

/*
 * El criterio de aceptación de la fase: desde un formulario a medio cargar se
 * crea una veterinaria nueva sin perder lo ya escrito.
 *
 * Del lado del servidor eso se traduce en una sola cosa: el mismo `store` del
 * catálogo tiene que contestar JSON con el registro serializado cuando quien
 * pregunta es un `fetch`, en vez de la redirección que espera Inertia. Si
 * respondiera con la redirección, el front tendría que navegar y el formulario
 * de atrás se vaciaría.
 */

it('devuelve el registro en JSON cuando lo pide un fetch', function () {
    $usuario = User::factory()->create();

    $respuesta = $this->actingAs($usuario)
        ->postJson(route('catalogos.veterinarias.store'), [
            'nombre' => 'Veterinaria del Sur',
        ]);

    $respuesta->assertCreated()
        ->assertJsonPath('registro.nombre', 'Veterinaria del Sur')
        ->assertJsonPath('registro.es_semilla', false)
        ->assertJsonStructure(['registro' => ['id', 'etiqueta', 'detalle'], 'mensaje']);

    expect(Veterinaria::firstWhere('nombre', 'Veterinaria del Sur')->usuario_id)
        ->toBe($usuario->id);
});

it('devuelve los errores de validación en JSON, sin redirigir', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->postJson(route('catalogos.veterinarias.store'), ['nombre' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nombre');

    expect(Veterinaria::count())->toBe(0);
});

it('sirve para todos los catálogos, no solo veterinarias', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->postJson(route('catalogos.medicamentos.store'), [
            'nombre_comercial' => 'Drontal Plus',
            'categoria' => 'antiparasitario_interno',
        ])
        ->assertCreated()
        ->assertJsonPath('registro.etiqueta', 'Drontal Plus');
});

it('el alta al vuelo también exige estar autenticado', function () {
    $this->postJson(route('catalogos.veterinarias.store'), ['nombre' => 'Cualquiera'])
        ->assertUnauthorized();

    expect(Veterinaria::count())->toBe(0);
});
