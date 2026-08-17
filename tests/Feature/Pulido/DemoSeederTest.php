<?php

use App\Models\CicloCelo;
use App\Models\Dieta;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Visita;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

/*
 * El seeder de demo es lo primero que corre alguien que clona el repo: si se
 * rompe, la app se ve vacía y parece que no funciona.
 */

it('carga una demo navegable', function () {
    $this->seed(CatalogosSeeder::class);
    $this->seed(DemoSeeder::class);

    $usuario = User::where('email', 'demo@huella.test')->sole();
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
        ->and($greta->desparasitaciones()->count())->toBe(1)
        ->and($greta->alergias()->count())->toBe(1);

    // Las tomas de los dos tratamientos, ya generadas.
    expect(Tratamiento::has('tomas')->count())->toBe(2);

    // Una sola dieta vigente: es la regla de negocio 1, y el seeder la ejercita
    // iniciando dos.
    expect(Dieta::count())->toBe(2)
        ->and(Dieta::whereNull('fecha_fin')->count())->toBe(1);

    // Y recordatorios, que es lo que hace que el dashboard no arranque vacío.
    expect($greta->recordatorios()->count())->toBeGreaterThan(0);
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
