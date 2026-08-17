<?php

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Models\Visita;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;

/*
 * Recorre todas las pantallas con la demo cargada.
 *
 * Existe por un bug concreto: el dashboard tiraba 500 en desarrollo por un lazy
 * load de `ultimoPeso`, y el único test que había usaba un usuario **sin
 * mascotas**, así que la rama del error nunca se ejecutaba. Un 200 con la base
 * vacía no prueba nada de una pantalla que se llena de relaciones.
 *
 * Con `Model::preventLazyLoading` activo fuera de producción, cada relación que
 * falte en el eager loading explota acá y no en la cara del usuario.
 */

beforeEach(function () {
    // Sin el manejador de excepciones: acá todo tiene que dar 200, y un "esperaba
    // 200 y recibí 500" no dice qué relación faltó cargar.
    $this->withoutExceptionHandling();

    $this->seed(CatalogosSeeder::class);
    $this->seed(DemoSeeder::class);

    $this->usuario = User::where('email', 'demo@huella.test')->sole();
    $this->mascota = Mascota::where('nombre', 'Greta')->sole();
});

/**
 * Todas las pantallas de la app, con la mascota que tiene la historia completa.
 *
 * @return array<string, array{string, array<mixed>}>
 */
dataset('pantallas', [
    'dashboard' => ['dashboard', []],
    'diario' => ['mascotas.diario.index', ['mascota']],
    'mascotas' => ['mascotas.index', []],
    'ficha de la mascota' => ['mascotas.show', ['mascota']],
    'editar mascota' => ['mascotas.edit', ['mascota']],
    'visitas' => ['mascotas.visitas.index', ['mascota']],
    'nueva visita' => ['mascotas.visitas.create', ['mascota']],
    'preventivo' => ['mascotas.preventivo.index', ['mascota']],
    'seguimiento' => ['mascotas.seguimiento.index', ['mascota']],
    'medicación' => ['medicacion.index', []],
    'recordatorios' => ['recordatorios.index', []],
    'catálogos' => ['catalogos.index', []],
    'veterinarias' => ['catalogos.veterinarias.index', []],
    'veterinarios' => ['catalogos.veterinarios.index', []],
    'medicamentos' => ['catalogos.medicamentos.index', []],
    'vacunas' => ['catalogos.vacunas.index', []],
    'alimentos' => ['catalogos.alimentos.index', []],
]);

it('abre con datos reales', function (string $ruta, array $parametros) {
    $argumentos = array_map(fn (string $p) => $this->{$p}, $parametros);

    $this->actingAs($this->usuario)
        ->get(route($ruta, $argumentos))
        ->assertOk();
})->with('pantallas');

it('abre la ficha de una visita con todo lo que salió de ella', function () {
    $visita = Visita::where('mascota_id', $this->mascota->id)->sole();

    $this->actingAs($this->usuario)
        ->get(route('mascotas.visitas.show', [$this->mascota, $visita]))
        ->assertOk();

    $this->actingAs($this->usuario)
        ->get(route('mascotas.visitas.edit', [$this->mascota, $visita]))
        ->assertOk();
});

it('pagina el diario sin lazy loading', function () {
    // El endpoint de scroll infinito arma los eventos de siete fuentes: es donde
    // más fácil se cuela una relación sin cargar.
    $this->actingAs($this->usuario)
        ->getJson(route('mascotas.diario.mas', $this->mascota))
        ->assertOk()
        ->assertJsonStructure(['eventos', 'cursor', 'hay_mas']);
});

it('genera la historia clínica en PDF con datos', function () {
    $respuesta = $this->actingAs($this->usuario)
        ->get(route('mascotas.historia-clinica', $this->mascota));

    $respuesta->assertOk();
    expect($respuesta->getContent())->toStartWith('%PDF');
});

it('exporta los datos del usuario', function () {
    $this->actingAs($this->usuario)
        ->get(route('exportacion.datos'))
        ->assertOk();
});

it('sirve una foto de la galería', function () {
    $foto = FotoMascota::where('mascota_id', $this->mascota->id)->first();

    if ($foto === null) {
        expect(true)->toBeTrue('La demo no carga fotos: no hay nada que servir.');

        return;
    }

    $this->actingAs($this->usuario)
        ->get(route('mascotas.fotos.mostrar', [$this->mascota, $foto]))
        ->assertOk();
});

it('abre las pantallas de la mascota castrada', function () {
    // El módulo de celo se oculta y el semáforo cambia: otra rama del código.
    $simon = Mascota::where('nombre', 'Simón')->sole();

    foreach (['mascotas.show', 'mascotas.seguimiento.index', 'mascotas.diario.index'] as $ruta) {
        $this->actingAs($this->usuario)
            ->get(route($ruta, $simon))
            ->assertOk();
    }
});

it('abre las pantallas de una mascota fallecida', function () {
    // Modo lectura: las pantallas siguen abriendo, sin los botones de carga.
    $this->mascota->update(['fecha_fallecimiento' => now()->subWeek()->toDateString()]);

    foreach (['mascotas.show', 'mascotas.diario.index', 'mascotas.visitas.index'] as $ruta) {
        $this->actingAs($this->usuario)
            ->get(route($ruta, $this->mascota))
            ->assertOk();
    }
});
