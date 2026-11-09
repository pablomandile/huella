<?php

use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * `huella:demo-reset` corre `migrate:fresh`, que en SQLite no puede ir dentro de
 * la transacción de RefreshDatabase. Por eso vive en Unit, que no la usa: la
 * base en memoria arranca vacía, se migra a mano y se descarta al terminar.
 */

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    Storage::fake('local');
});

it('sin la demo activa no borra nada', function () {
    config(['demo.enabled' => false]);
    $visitante = User::factory()->create();

    $this->artisan('huella:demo-reset')->assertFailed();

    expect(User::whereKey($visitante->id)->exists())->toBeTrue();
});

it('vacía la base y los archivos de los visitantes, y resiembra', function () {
    config(['demo.enabled' => true]);

    User::factory()->create(['email' => 'visitante@example.com']);
    Storage::put('mascotas/999/foto.webp', 'x');
    Storage::put('catalogos/alimentos/999/paquete.webp', 'x');

    $this->artisan('huella:demo-reset')->assertSuccessful();

    expect(User::pluck('email')->all())->toBe([config('demo.email')])
        ->and(Mascota::pluck('nombre')->sort()->values()->all())->toBe(['Greta', 'Simón']);

    Storage::assertMissing(['mascotas/999/foto.webp', 'catalogos/alimentos/999/paquete.webp']);

    // Y lo que sembró la demo sí está: el reset no se lleva sus propias fotos.
    $greta = Mascota::where('nombre', 'Greta')->sole();
    Storage::assertExists($greta->foto_perfil);
});

it('corre en la demo aunque APP_ENV sea production', function () {
    /*
     * La demo corre con APP_ENV=production, y el AppServiceProvider prohíbe ahí
     * los comandos destructivos. `migrate:fresh` prohibido no falla: sale con un
     * aviso y deja la base como estaba. En MiSalud eso hizo que se sembrara la
     * demo encima de los datos reales. El comando levanta la prohibición solo
     * después de confirmar el flag.
     */
    config(['demo.enabled' => true]);
    app()->detectEnvironment(fn () => 'production');
    DB::prohibitDestructiveCommands(true);

    User::factory()->create(['email' => 'visitante@example.com']);

    $this->artisan('huella:demo-reset')->assertSuccessful();

    expect(User::pluck('email')->all())->toBe([config('demo.email')]);
});
