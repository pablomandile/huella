<?php

use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\User;

it('crea la ficha y deja a su dueño como propietario en el pivote', function () {
    $usuario = User::factory()->create();

    $respuesta = $this->actingAs($usuario)->post(route('mascotas.store'), [
        'nombre' => 'Greta',
        'especie' => 'perro',
        'sexo' => 'hembra',
        'fecha_nacimiento' => '2022-03-10',
        'fecha_nacimiento_estimada' => true,
    ]);

    $mascota = Mascota::firstWhere('nombre', 'Greta');

    $respuesta->assertRedirect(route('mascotas.show', $mascota));

    expect($mascota->usuario_id)->toBe($usuario->id);

    // El observer tiene que haber insertado la fila del propietario: es lo
    // que hace funcionar toda la autorización.
    $this->assertDatabaseHas('mascota_usuario', [
        'mascota_id' => $mascota->id,
        'usuario_id' => $usuario->id,
        'rol' => RolCuidador::Propietario->value,
    ]);

    // La recién creada queda como mascota activa de la sesión.
    expect(session('mascota_activa_id'))->toBe($mascota->id);
});

it('lista solo las mascotas del usuario', function () {
    $duenio = User::factory()->create();
    $otro = User::factory()->create();

    Mascota::factory()->for($duenio, 'propietario')->create(['nombre' => 'Mía']);
    Mascota::factory()->for($otro, 'propietario')->create(['nombre' => 'Ajena']);

    $this->actingAs($duenio)
        ->get(route('mascotas.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('mascotas/Index')
                ->has('mascotas', 1)
                ->where('mascotas.0.nombre', 'Mía'),
        );
});

it('actualiza la ficha', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create(['castrado' => false]);

    $this->actingAs($duenio)
        ->put(route('mascotas.update', $mascota), [
            'nombre' => $mascota->nombre,
            'especie' => $mascota->especie->value,
            'sexo' => $mascota->sexo->value,
            'castrado' => true,
            'fecha_castracion' => '2025-06-01',
        ])
        ->assertRedirect(route('mascotas.show', $mascota));

    expect($mascota->refresh()->castrado)->toBeTrue();
});

it('da de baja con soft delete y conserva el registro', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($duenio)
        ->delete(route('mascotas.destroy', $mascota))
        ->assertRedirect(route('mascotas.index'));

    // Nada se borra de verdad: el historial clínico se conserva.
    $this->assertSoftDeleted('mascotas', ['id' => $mascota->id]);
});

it('rechaza un microchip repetido del mismo usuario pero lo permite en cuentas distintas', function () {
    $duenio = User::factory()->create();
    $otro = User::factory()->create();

    Mascota::factory()->for($duenio, 'propietario')->create(['microchip' => '900215001111111']);

    $datos = [
        'nombre' => 'Otro',
        'especie' => 'perro',
        'sexo' => 'macho',
        'microchip' => '900215001111111',
    ];

    // Mismo usuario: choca.
    $this->actingAs($duenio)
        ->post(route('mascotas.store'), $datos)
        ->assertSessionHasErrors('microchip');

    // Cuenta distinta: el índice es por usuario, no global.
    $this->actingAs($otro)
        ->post(route('mascotas.store'), $datos)
        ->assertSessionHasNoErrors();
});
