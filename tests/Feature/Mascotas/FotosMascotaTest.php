<?php

use App\Models\Mascota;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('convierte la foto de perfil a WebP con su miniatura y la suma a la galería', function () {
    $duenio = User::factory()->create();

    $this->actingAs($duenio)->post(route('mascotas.store'), [
        'nombre' => 'Greta',
        'especie' => 'perro',
        'sexo' => 'hembra',
        'foto' => UploadedFile::fake()->image('greta.jpg', 2400, 1800),
    ]);

    $mascota = Mascota::firstWhere('nombre', 'Greta');

    expect($mascota->foto_perfil)->toEndWith('.webp');
    Storage::assertExists($mascota->foto_perfil);
    Storage::assertExists(preg_replace('/\.webp$/', '-min.webp', $mascota->foto_perfil));

    // Es realmente WebP, no un jpg renombrado.
    expect(Storage::mimeType($mascota->foto_perfil))->toBe('image/webp');

    // La primera foto también entra a la galería.
    expect($mascota->fotos()->count())->toBe(1);
});

it('sirve la foto solo a quien tiene acceso a la mascota', function () {
    $duenio = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($duenio)->post(route('mascotas.fotos.store', $mascota), [
        'foto' => UploadedFile::fake()->image('paseo.jpg', 1200, 900),
        'fecha' => '2026-08-01',
        'epigrafe' => 'Primer paseo',
    ]);

    $foto = $mascota->fotos()->firstOrFail();

    $this->actingAs($duenio)
        ->get(route('mascotas.fotos.mostrar', [$mascota, $foto]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    // Nunca por URL pública: otra cuenta recibe 403 aunque adivine la URL.
    $this->actingAs($intruso)
        ->get(route('mascotas.fotos.mostrar', [$mascota, $foto]))
        ->assertForbidden();
});

it('no acepta fotos nuevas para una mascota fallecida', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->fallecida()->create();

    // Modo lectura: el historial se conserva, pero no se registran eventos.
    $this->actingAs($duenio)
        ->post(route('mascotas.fotos.store', $mascota), [
            'foto' => UploadedFile::fake()->image('tarde.jpg'),
            'fecha' => '2026-08-01',
        ])
        ->assertForbidden();
});

it('rechaza archivos que no son imagen y fotos de más de 10 MB', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($duenio)
        ->post(route('mascotas.fotos.store', $mascota), [
            'foto' => UploadedFile::fake()->create('receta.pdf', 500, 'application/pdf'),
            'fecha' => '2026-08-01',
        ])
        ->assertSessionHasErrors('foto');

    $this->actingAs($duenio)
        ->post(route('mascotas.fotos.store', $mascota), [
            'foto' => UploadedFile::fake()->image('enorme.jpg')->size(11_000),
            'fecha' => '2026-08-01',
        ])
        ->assertSessionHasErrors('foto');
});
