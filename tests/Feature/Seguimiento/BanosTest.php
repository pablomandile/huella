<?php

use App\Enums\LugarBano;
use App\Enums\RolCuidador;
use App\Models\Bano;
use App\Models\Mascota;
use App\Models\User;

it('registra un baño con el lugar donde fue', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->from(route('mascotas.seguimiento.index', $mascota))
        ->post(route('mascotas.banos.store', $mascota), [
            'fecha' => now()->toDateString(),
            'lugar' => 'salon',
            'notas' => 'Con corte de pelo',
        ])
        ->assertRedirect(route('mascotas.seguimiento.index', $mascota));

    $bano = Bano::sole();

    expect($bano->mascota_id)->toBe($mascota->id)
        ->and($bano->lugar)->toBe(LugarBano::Salon)
        ->and($bano->notas)->toBe('Con corte de pelo');
});

it('rechaza un lugar inventado y un baño a futuro', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.banos.store', $mascota), [
            'fecha' => now()->addDays(3)->toDateString(),
            'lugar' => 'lavadero',
        ])
        ->assertSessionHasErrors(['fecha', 'lugar']);

    expect(Bano::count())->toBe(0);
});

it('el seguimiento manda los baños con el último primero', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Bano::factory()->elDia('2026-08-01')->create(['mascota_id' => $mascota->id]);
    Bano::factory()->enSalon()->elDia('2026-09-15')->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('banos', 2)
            ->where('banos.0.fecha', '2026-09-15')
            ->where('banos.0.lugar_etiqueta', 'En el salón')
            ->has('lugaresBano', 3),
        );
});

it('no deja a un lector registrar ni borrar baños', function () {
    // El lector se crea antes que el dueño, como en el resto de los tests de
    // acceso compartido.
    $lector = User::factory()->create();
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);
    $bano = Bano::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($lector)
        ->post(route('mascotas.banos.store', $mascota), [
            'fecha' => now()->toDateString(),
            'lugar' => 'casa',
        ])
        ->assertForbidden();

    $this->actingAs($lector)
        ->delete(route('mascotas.banos.destroy', [$mascota, $bano]))
        ->assertForbidden();

    expect(Bano::count())->toBe(1);
});

it('no deja borrar el baño de otra mascota colgándolo de la propia', function () {
    $usuario = User::factory()->create();
    $propia = Mascota::factory()->for($usuario, 'propietario')->create();
    $ajena = Mascota::factory()->for(User::factory()->create(), 'propietario')->create();
    $banoAjeno = Bano::factory()->create(['mascota_id' => $ajena->id]);

    $this->actingAs($usuario)
        ->delete(route('mascotas.banos.destroy', [$propia, $banoAjeno]))
        ->assertForbidden();

    expect(Bano::count())->toBe(1);
});

it('el dueño borra un baño mal cargado', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $bano = Bano::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->delete(route('mascotas.banos.destroy', [$mascota, $bano]))
        ->assertRedirect();

    expect(Bano::count())->toBe(0);
});
