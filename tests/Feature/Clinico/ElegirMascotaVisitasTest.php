<?php

use App\Models\Mascota;
use App\Models\User;
use App\Models\Visita;

/*
 * El paso previo de «Visitas» cuando se entra por el menú, que no tiene contexto
 * de mascota. Desde la ficha no se pasa por acá.
 */

it('con una sola mascota entra derecho a sus visitas', function () {
    // Preguntar cuál cuando hay una sola es un click de más en cada uso.
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->get(route('visitas.elegir'))
        ->assertRedirect(route('mascotas.visitas.index', $mascota));
});

it('con varias mascotas pregunta de quién es la visita', function () {
    $usuario = User::factory()->create();
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Mora']);

    $this->actingAs($usuario)
        ->get(route('visitas.elegir'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('visitas/Elegir')
            ->has('mascotas', 2)
            // Ordenadas por nombre, no por id.
            ->where('mascotas.0.nombre', 'Greta')
            ->where('mascotas.1.nombre', 'Mora'),
        );
});

it('sin ninguna mascota manda a crearla, no a una pantalla vacía', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('visitas.elegir'))
        ->assertRedirect(route('mascotas.create'))
        ->assertSessionHas('warning');
});

it('trae la cuenta de visitas y la fecha de la última, para ubicar sin entrar', function () {
    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $greta = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Mora']);

    Visita::factory()->for($greta)->create(['fecha_hora' => '2026-08-10 14:00:00']);
    Visita::factory()->for($greta)->create(['fecha_hora' => '2026-08-17 14:00:00']);

    $this->actingAs($usuario)
        ->get(route('visitas.elegir'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('mascotas.0.visitas_count', 2)
            ->where('mascotas.0.ultima_visita', '17/08/2026')
            ->where('mascotas.1.visitas_count', 0)
            ->where('mascotas.1.ultima_visita', null),
        );
});

it('señala la mascota que se estaba mirando', function () {
    $usuario = User::factory()->create();
    $greta = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Mora']);

    $this->actingAs($usuario)
        ->withSession(['mascota_activa_id' => $greta->id])
        ->get(route('visitas.elegir'))
        ->assertInertia(fn ($pagina) => $pagina->where('mascotaActivaId', $greta->id));
});

it('no lista las mascotas de otra cuenta', function () {
    $usuario = User::factory()->create();
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Propia']);
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Otra propia']);

    // De otro dueño, con un nombre que ordenaría primero si se colara.
    Mascota::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($usuario)
        ->get(route('visitas.elegir'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('mascotas', 2)
            ->where('mascotas.0.nombre', 'Otra propia')
            ->where('mascotas.1.nombre', 'Propia'),
        );
});
