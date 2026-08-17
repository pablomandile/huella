<?php

use App\Models\Mascota;
use App\Models\User;

/*
 * El requisito de privacidad es explícito en la especificación: los datos de
 * una cuenta jamás pueden ser visibles para otra.
 */

it('no deja ver, editar ni borrar la mascota de otro usuario', function () {
    $duenio = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($intruso)->get(route('mascotas.show', $mascota))->assertForbidden();
    $this->actingAs($intruso)->get(route('mascotas.edit', $mascota))->assertForbidden();

    $this->actingAs($intruso)
        ->put(route('mascotas.update', $mascota), [
            'nombre' => 'Hackeada',
            'especie' => 'perro',
            'sexo' => 'macho',
        ])
        ->assertForbidden();

    $this->actingAs($intruso)->delete(route('mascotas.destroy', $mascota))->assertForbidden();

    expect($mascota->refresh()->nombre)->not->toBe('Hackeada');
});

it('no deja marcar como activa la mascota de otro usuario', function () {
    $duenio = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($intruso)
        ->patch(route('mascota-activa.update', $mascota))
        ->assertForbidden();
});

it('la autorización sale del pivote, no de la columna usuario_id', function () {
    $duenio = User::factory()->create();
    $cuidador = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // Simula el multi-cuidador de v2: una fila más en el pivote alcanza para
    // dar acceso, sin tocar ninguna Policy.
    $mascota->cuidadores()->attach($cuidador->id, ['rol' => 'lector']);

    $this->actingAs($cuidador)->get(route('mascotas.show', $mascota))->assertOk();

    // Lector: puede mirar pero no editar.
    $this->actingAs($cuidador)
        ->put(route('mascotas.update', $mascota), [
            'nombre' => 'Cambiada',
            'especie' => 'perro',
            'sexo' => 'macho',
        ])
        ->assertForbidden();
});
