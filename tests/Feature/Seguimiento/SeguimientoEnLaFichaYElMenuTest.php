<?php

use App\Enums\Sexo;
use App\Models\AplicacionVacuna;
use App\Models\Bano;
use App\Models\Desparasitacion;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\User;
use App\Services\TimelineService;

/*
 * Preventivo se fundió en Seguimiento, Seguimiento entró al menú, y la ficha
 * pasó de una tarjeta con tres botones a tres tarjetas: vacunación, línea de
 * tiempo (los tres últimos eventos) y seguimiento (el último dato de cada rubro).
 */

/* ----------------------------------------------------------- la pantalla */

it('la pantalla de seguimiento trae también vacunas y desparasitaciones', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    AplicacionVacuna::factory()->create(['mascota_id' => $mascota->id]);
    Desparasitacion::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('seguimiento/Index')
            ->has('aplicaciones', 1)
            ->has('desparasitaciones', 1)
            ->has('estadoVacunacion')
            ->has('vacunas')
            ->has('medicamentos')
            ->has('veterinarias')
            ->has('tiposDesparasitacion'),
        );
});

it('la dirección vieja de preventivo redirige a seguimiento', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->get("/mascotas/{$mascota->id}/preventivo")
        ->assertStatus(301)
        ->assertRedirect("/mascotas/{$mascota->id}/seguimiento");
});

it('la redirección vieja no acepta escrituras', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post("/mascotas/{$mascota->id}/preventivo")
        ->assertStatus(405);
});

/* --------------------------------------------------------------- el menú */

it('desde el menú, con una sola mascota entra derecho a su seguimiento', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->get(route('seguimiento.elegir'))
        ->assertRedirect(route('mascotas.seguimiento.index', $mascota));
});

it('desde el menú, con varias mascotas pregunta de cuál', function () {
    $usuario = User::factory()->create();
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Mora']);
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);

    $this->actingAs($usuario)
        ->get(route('seguimiento.elegir'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('seguimiento/Elegir')
            ->has('mascotas', 2)
            ->where('mascotas.0.nombre', 'Greta')
            ->where('mascotas.0.url', fn ($url) => str_ends_with($url, '/seguimiento')),
        );
});

it('desde el menú, sin mascotas manda a crearla', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('seguimiento.elegir'))
        ->assertRedirect(route('mascotas.create'))
        ->assertSessionHas('warning');
});

/* -------------------------------------------------------------- la ficha */

it('la ficha trae como mucho los tres últimos eventos de la línea de tiempo', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    foreach (['2026-01-10', '2026-02-10', '2026-03-10', '2026-04-10', '2026-05-10'] as $fecha) {
        EntradaDiario::factory()->for($mascota)->elDia($fecha)->create();
    }

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertInertia(fn ($pagina) => $pagina
            ->has('lineaDeTiempo', 3)
            // De lo más nuevo a lo más viejo.
            ->where('lineaDeTiempo.0.fecha', '2026-05-10')
            ->where('lineaDeTiempo.2.fecha', '2026-03-10'),
        );
});

it('un evento de vacuna lleva a la sección de vacunas de seguimiento', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    AplicacionVacuna::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('lineaDeTiempo.0.tipo', 'vacuna')
            ->where('lineaDeTiempo.0.url', route('mascotas.seguimiento.index', $mascota).'#vacunas'),
        );
});

it('la ficha resume cada rubro de seguimiento, y dice «Sin datos» en vez de esconderlo', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['sexo' => Sexo::Macho]);

    RegistroPeso::factory()->for($mascota)->elDia('2026-11-01', 18.4)->create();
    Bano::factory()->for($mascota)->elDia('2026-11-05')->create();

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('seguimiento', fn ($filas) => collect($filas)->pluck('clave')->all()
                === ['peso', 'alimento', 'vacunas', 'desparasitacion', 'bano'])
            ->where('seguimiento.0.valor', '18,4 kg')
            ->where('seguimiento.1.valor', 'Sin datos')
            ->where('seguimiento.2.valor', 'Sin datos')
            ->where('seguimiento.4.valor', fn ($valor) => str_contains($valor, 'noviembre')),
        );
});

it('una hembra entera tiene la fila de celo; un macho no', function () {
    $usuario = User::factory()->create();
    $hembra = Mascota::factory()->for($usuario, 'propietario')->hembra()->entera()->create();

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $hembra))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('seguimiento', fn ($filas) => collect($filas)->pluck('clave')->contains('celo')),
        );
});

it('el inicio trae la línea de tiempo y el seguimiento, igual que la ficha', function () {
    $usuario = User::factory()->create();
    Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->get(route('dashboard'))
        ->assertInertia(fn ($pagina) => $pagina
            ->has('ficha.lineaDeTiempo')
            ->has('ficha.seguimiento'),
        );
});

/* ------------------------------------------------- el corte de la línea */

it('con una página chica, el cursor sigue sin repetir ni saltear eventos', function () {
    $mascota = Mascota::factory()->create();

    foreach (['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01'] as $fecha) {
        EntradaDiario::factory()->for($mascota)->elDia($fecha)->create();
    }

    $timeline = app(TimelineService::class);

    $primera = $timeline->para($mascota, [], null, 2);
    $segunda = $timeline->para($mascota, [], $primera['cursor'], 2);
    $tercera = $timeline->para($mascota, [], $segunda['cursor'], 2);

    expect($primera['hay_mas'])->toBeTrue()
        ->and($tercera['hay_mas'])->toBeFalse();

    $fechas = collect([...$primera['eventos'], ...$segunda['eventos'], ...$tercera['eventos']])
        ->pluck('fecha')
        ->all();

    expect($fechas)->toBe(['2026-05-01', '2026-04-01', '2026-03-01', '2026-02-01', '2026-01-01']);
});
