<?php

use App\Models\Alimento;
use App\Models\CicloCelo;
use App\Models\Dieta;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\User;
use App\Services\DietaService;

it('carga un peso desde el dashboard sin entrar a ninguna pantalla', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->from(route('dashboard'))
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 18.4,
            'fecha' => now()->toDateString(),
            'origen' => 'casa',
        ])
        ->assertRedirect(route('dashboard'));

    expect(RegistroPeso::sole()->kilos())->toBe(18.4);
});

it('el dashboard muestra el último peso y qué come', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $renal = Alimento::factory()->semilla()->create(['marca' => 'Royal Canin', 'nombre' => 'Renal']);

    RegistroPeso::factory()->elDia(now()->subMonth()->toDateString(), 17.0)
        ->create(['mascota_id' => $mascota->id]);
    RegistroPeso::factory()->elDia(now()->toDateString(), 18.4)
        ->create(['mascota_id' => $mascota->id]);

    app(DietaService::class)->iniciar($mascota, [
        'alimento_id' => $renal->id,
        'fecha_inicio' => now()->toDateString(),
        'racion_diaria_g' => 300,
        'tomas_por_dia' => 2,
    ]);

    $this->actingAs($usuario)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->where('ultimoPeso.peso_legible', '18,4 kg')
            ->where('dietaVigente.alimento', 'Royal Canin Renal')
            ->where('dietaVigente.racion_legible', '300 g por día, en 2 tomas'),
        );
});

it('no admite dos pesos del mismo día con la misma balanza', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    RegistroPeso::factory()->elDia('2026-08-17', 18.4)->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 18.9,
            'fecha' => '2026-08-17',
            'origen' => 'casa',
        ])
        ->assertSessionHasErrors('fecha');

    // Sigue habiendo uno: es una corrección, no un dato nuevo.
    expect(RegistroPeso::count())->toBe(1);
});

it('sí admite el mismo día en casa y en la veterinaria', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    RegistroPeso::factory()->elDia('2026-08-17', 18.4)->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 19.1,
            'fecha' => '2026-08-17',
            'origen' => 'veterinaria',
        ])
        ->assertSessionHasNoErrors();

    expect(RegistroPeso::count())->toBe(2);
});

it('no admite un peso a futuro', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 18.4,
            'fecha' => now()->addWeek()->toDateString(),
            'origen' => 'casa',
        ])
        ->assertSessionHasErrors('fecha');
});

it('muestra la curva y la variación desde el último peso', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    RegistroPeso::factory()->elDia('2026-06-01', 17.0)->create(['mascota_id' => $mascota->id]);
    RegistroPeso::factory()->elDia('2026-08-01', 18.4)->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('seguimiento/Index')
            ->has('pesos', 2)
            // En orden cronológico: así se dibuja la curva.
            ->where('pesos.0.fecha', '2026-06-01')
            ->where('pesos.1.fecha', '2026-08-01')
            ->where('variacion.sube', true)
            ->where('variacion.kilos', 1.4)
            ->where('variacion.texto', fn ($t) => str_contains($t, 'Subió 1,40 kg')),
        );
});

it('no calcula variación con un solo peso', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    RegistroPeso::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $mascota))
        ->assertInertia(fn ($pagina) => $pagina->where('variacion', null));
});

it('cambiar la dieta cierra la anterior, desde HTTP', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $viejo = Alimento::factory()->semilla()->create(['nombre' => 'Adulto']);
    $renal = Alimento::factory()->semilla()->create(['nombre' => 'Renal']);

    $this->actingAs($usuario)->post(route('mascotas.dietas.store', $mascota), [
        'alimento_id' => $viejo->id,
        'fecha_inicio' => '2026-01-01',
    ]);

    $this->actingAs($usuario)->post(route('mascotas.dietas.store', $mascota), [
        'alimento_id' => $renal->id,
        'fecha_inicio' => '2026-08-17',
        'prescripta' => 1,
    ])->assertRedirect();

    expect($mascota->dietas()->vigente()->count())->toBe(1)
        ->and(Dieta::where('alimento_id', $viejo->id)->sole()->fecha_fin->toDateString())
        ->toBe('2026-08-16');
});

it('no deja usar el alimento de otra cuenta', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $ajeno = Alimento::factory()->create(); // de otro usuario

    $this->actingAs($usuario)
        ->post(route('mascotas.dietas.store', $mascota), [
            'alimento_id' => $ajeno->id,
            'fecha_inicio' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('alimento_id');
});

/* ------------------------------------------------------------------- celo */

it('registra un ciclo de celo en una hembra entera', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->hembra()->entera()->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.celos.store', $mascota), [
            'fecha_inicio' => now()->subDays(20)->toDateString(),
            'fecha_fin' => now()->subDays(3)->toDateString(),
            'intensidad' => 'normal',
            'sintomas' => 'Sangrado leve, más inquieta',
        ])
        ->assertRedirect();

    $ciclo = CicloCelo::sole();

    expect($ciclo->duracion_dias)->toBe(18)
        ->and($ciclo->proxima_estimada)->not->toBeNull();
});

it('no deja registrar celo en una mascota castrada', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->hembra()->castrada()->create();

    // Regla de negocio 2: castrada, el módulo de celo no existe.
    $this->actingAs($usuario)
        ->post(route('mascotas.celos.store', $mascota), [
            'fecha_inicio' => now()->toDateString(),
        ])
        ->assertForbidden();

    expect(CicloCelo::count())->toBe(0);
});

it('no deja registrar celo en un macho', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['sexo' => 'macho']);

    $this->actingAs($usuario)
        ->post(route('mascotas.celos.store', $mascota), [
            'fecha_inicio' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('la pantalla oculta el módulo de celo cuando no corresponde', function () {
    $usuario = User::factory()->create();
    $macho = Mascota::factory()->for($usuario, 'propietario')->create(['sexo' => 'macho']);
    $hembra = Mascota::factory()->for($usuario, 'propietario')->hembra()->entera()->create();

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $macho))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('celoVisible', false)
            ->where('estimacionCelo', null)
            ->has('ciclos', 0),
        );

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $hembra))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('celoVisible', true)
            ->where('estimacionCelo.confianza', 'muy_baja'),
        );
});

it('con tres ciclos la pantalla muestra el promedio real y su confianza', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->hembra()->entera()->create();

    CicloCelo::factory()->empezoEl('2025-01-10')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2025-07-29')->create(['mascota_id' => $mascota->id]);
    CicloCelo::factory()->empezoEl('2026-03-06')->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.seguimiento.index', $mascota))
        ->assertInertia(fn ($pagina) => $pagina
            ->where('estimacionCelo.dias_promedio', 210)
            ->where('estimacionCelo.usa_promedio_real', true)
            ->where('estimacionCelo.confianza', 'media')
            ->where('estimacionCelo.fecha', '2026-10-02')
            // El detalle explica de dónde sale el número.
            ->where('estimacionCelo.detalle', fn ($d) => str_contains($d, '200, 220')),
        );
});

it('no admite un celo a futuro', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->hembra()->entera()->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.celos.store', $mascota), [
            'fecha_inicio' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('fecha_inicio');
});

/* -------------------------------------------------------------- privacidad */

it('no deja ver ni tocar el seguimiento de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->hembra()->entera()->create();
    $peso = RegistroPeso::factory()->create(['mascota_id' => $mascota->id]);
    $dieta = Dieta::factory()->create(['mascota_id' => $mascota->id]);
    $ciclo = CicloCelo::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($intruso)
        ->get(route('mascotas.seguimiento.index', $mascota))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 1,
            'fecha' => now()->toDateString(),
            'origen' => 'casa',
        ])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('mascotas.pesos.destroy', [$mascota, $peso]))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('mascotas.dietas.destroy', [$mascota, $dieta]))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('mascotas.celos.destroy', [$mascota, $ciclo]))
        ->assertForbidden();

    expect(RegistroPeso::count())->toBe(1);
});

it('una mascota fallecida no recibe pesos nuevos', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create([
        'fecha_fallecimiento' => now()->subMonth()->toDateString(),
    ]);

    // Modo lectura: conserva su historial pero no se le carga nada más.
    $this->actingAs($usuario)
        ->post(route('mascotas.pesos.store', $mascota), [
            'peso_kg' => 18.4,
            'fecha' => now()->toDateString(),
            'origen' => 'casa',
        ])
        ->assertForbidden();
});

it('elimina un peso mal cargado de verdad, sin archivarlo', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $peso = RegistroPeso::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->delete(route('mascotas.pesos.destroy', [$mascota, $peso]))
        ->assertRedirect();

    // Un peso mal cargado deforma la curva: se va, no se archiva.
    expect(RegistroPeso::count())->toBe(0);
});
