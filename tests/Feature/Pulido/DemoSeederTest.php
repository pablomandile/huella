<?php

use App\Enums\EstadoToma;
use App\Models\CicloCelo;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Visita;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * El seeder de demo es lo primero que corre alguien que clona el repo: si se
 * rompe, la app se ve vacía y parece que no funciona.
 */

it('carga una demo navegable', function () {
    Storage::fake('local');

    $this->seed(CatalogosSeeder::class);
    $this->seed(DemoSeeder::class);

    $usuario = User::where('email', config('demo.email'))->sole();
    $greta = Mascota::where('nombre', 'Greta')->sole();

    // Dos mascotas: una entera y una castrada, para ver el módulo de celo en sus
    // dos estados sin tener que editar nada.
    expect(Mascota::count())->toBe(2)
        ->and($greta->propietario->is($usuario))->toBeTrue()
        ->and(Mascota::where('nombre', 'Simón')->sole()->castrado)->toBeTrue();

    // El pivote lo escribe el observer: si el seeder corriera con
    // WithoutModelEvents, la Policy no dejaría ver ninguna mascota.
    expect($greta->cuidadores()->where('users.id', $usuario->id)->exists())->toBeTrue();

    // Contenido suficiente para que cada pantalla tenga algo que mostrar.
    expect(Visita::count())->toBeGreaterThanOrEqual(1)
        ->and(Tratamiento::count())->toBe(2)
        ->and(RegistroPeso::count())->toBeGreaterThanOrEqual(3)
        ->and(CicloCelo::count())->toBe(3)
        ->and($greta->entradasDiario()->count())->toBeGreaterThanOrEqual(3)
        ->and($greta->vacunasAplicadas()->count())->toBe(2)
        ->and($greta->desparasitaciones()->count())->toBe(2)
        ->and($greta->alergias()->count())->toBe(1);

    // Las tomas de los dos tratamientos, ya generadas.
    expect(Tratamiento::has('tomas')->count())->toBe(2);

    // Las de ayer, dadas; las que vienen, pendientes. Una demo con todo atrasado
    // se lee como un descuido.
    $tomas = TomaMedicamento::query();
    expect((clone $tomas)->where('fecha_hora_programada', '<', now())->where('estado', EstadoToma::Pendiente)->count())->toBe(0)
        ->and((clone $tomas)->where('estado', EstadoToma::Administrada)->count())->toBeGreaterThan(0)
        ->and((clone $tomas)->where('fecha_hora_programada', '>', now())->where('estado', EstadoToma::Pendiente)->count())->toBeGreaterThan(0);

    // Una sola dieta vigente: es la regla de negocio 1, y el seeder la ejercita
    // iniciando dos.
    expect($greta->dietas()->count())->toBe(2)
        ->and($greta->dietas()->whereNull('fecha_fin')->count())->toBe(1);

    // Y recordatorios, que es lo que hace que el dashboard no arranque vacío.
    expect($greta->recordatorios()->count())->toBeGreaterThan(0);

    // Las imágenes y los papeles, pasados por los servicios de una subida real:
    // cada archivo existe en el disco, con su miniatura.
    $fotos = $greta->fotos()->get();
    expect($fotos)->toHaveCount(4)
        ->and($greta->foto_perfil)->toBe($fotos->sortBy('fecha')->last()->ruta);

    foreach ($fotos as $foto) {
        Storage::disk('local')->assertExists([$foto->ruta, $foto->ruta_miniatura]);
    }

    $papeles = $greta->adjuntos()->get();
    expect($papeles->pluck('tipo.value')->sort()->values()->all())
        ->toBe(['certificado_rabia', 'libreta_sanitaria', 'libreta_sanitaria']);

    foreach ($papeles as $papel) {
        Storage::disk('local')->assertExists($papel->ruta);
    }

    expect(Visita::has('adjuntos')->count())->toBe(1);
});

it('la cuenta demo puede entrar sin pasos previos', function () {
    Storage::fake('local');

    $this->seed(CatalogosSeeder::class);
    $this->seed(DemoSeeder::class);

    // Verificada y con los términos vigentes aceptados: si no, el visitante de
    // la demo choca con la verificación del email o con «aceptar y continuar».
    $usuario = User::where('email', config('demo.email'))->sole();

    expect($usuario->hasVerifiedEmail())->toBeTrue()
        ->and($usuario->aceptoTerminosVigentes())->toBeTrue();

    $this->post('/login', ['email' => config('demo.email'), 'password' => config('demo.password')])
        ->assertRedirect();

    $this->assertAuthenticatedAs($usuario);
});

it('con la demo activa corre aunque sea producción', function () {
    Storage::fake('local');
    $this->seed(CatalogosSeeder::class);

    app()->detectEnvironment(fn () => 'production');
    config(['demo.enabled' => true]);

    (new DemoSeeder)->setContainer($this->app)->run();

    expect(User::where('email', config('demo.email'))->exists())->toBeTrue();
});

it('no crea el usuario de demo en producción', function () {
    // Una cuenta que nadie creó, con un email que no existe, es un problema de
    // seguridad y de datos a la vez.
    app()->detectEnvironment(fn () => 'production');

    // Se instancia a mano y no con $this->seed(): en producción el comando pide
    // confirmación por consola, y lo que se está probando es la guarda propia.
    expect(fn () => (new DemoSeeder)->run())
        ->toThrow(RuntimeException::class, 'no corre en producción');

    expect(User::count())->toBe(0)
        ->and(Mascota::count())->toBe(0);
});

it('el seeder por defecto no arrastra el demo a producción', function () {
    app()->detectEnvironment(fn () => 'production');

    // DatabaseSeeder es el que corre con un `db:seed` pelado: en producción tiene
    // que sembrar los catálogos y nada más, sin explotar.
    (new DatabaseSeeder)->setContainer($this->app)->run();

    expect(Vacuna::whereNull('usuario_id')->count())->toBeGreaterThan(0)
        ->and(User::count())->toBe(0)
        ->and(Mascota::count())->toBe(0);
});
