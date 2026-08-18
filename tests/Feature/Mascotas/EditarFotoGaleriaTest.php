<?php

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;

/*
 * Editar el epígrafe o la fecha de una foto ya cargada.
 *
 * La imagen no se toca: puede ser la foto de perfil vigente o estar compartida
 * con otra entrada de la galería, así que reemplazarla desde acá sería pisar algo
 * que el usuario no está mirando.
 */

function fotoDeGaleria(Mascota $mascota, array $extra = []): FotoMascota
{
    return $mascota->fotos()->create([
        'ruta' => "mascotas/{$mascota->id}/uno.webp",
        'ruta_miniatura' => "mascotas/{$mascota->id}/uno-min.webp",
        'fecha' => '2026-08-01',
        'epigrafe' => 'Antes',
        ...$extra,
    ]);
}

it('cambia el epígrafe y la fecha', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $foto = fotoDeGaleria($mascota);

    $this->actingAs($usuario)
        ->patch(route('mascotas.fotos.update', [$mascota, $foto]), [
            'fecha' => '2026-08-05',
            'epigrafe' => 'Primer día en casa',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($foto->fresh())
        ->epigrafe->toBe('Primer día en casa')
        ->and($foto->fresh()->fecha->toDateString())->toBe('2026-08-05');
});

it('permite vaciar el epígrafe', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $foto = fotoDeGaleria($mascota);

    $this->actingAs($usuario)->patch(route('mascotas.fotos.update', [$mascota, $foto]), [
        'fecha' => '2026-08-01',
        'epigrafe' => null,
    ]);

    expect($foto->fresh()->epigrafe)->toBeNull();
});

it('no cambia la imagen aunque se mande una', function () {
    // `foto` no está en las reglas, así que `validated()` no la trae y el
    // `update()` no la puede escribir.
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $foto = fotoDeGaleria($mascota);
    $ruta = $foto->ruta;

    $this->actingAs($usuario)->patch(route('mascotas.fotos.update', [$mascota, $foto]), [
        'fecha' => '2026-08-01',
        'epigrafe' => 'Otro',
        'ruta' => 'mascotas/999/inyectada.webp',
    ]);

    expect($foto->fresh()->ruta)->toBe($ruta);
});

it('rechaza una fecha futura', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $foto = fotoDeGaleria($mascota);

    $this->actingAs($usuario)
        ->patch(route('mascotas.fotos.update', [$mascota, $foto]), [
            'fecha' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors('fecha');
});

it('no deja editar la foto de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();
    $foto = fotoDeGaleria($mascota);

    $this->actingAs($intruso)
        ->patch(route('mascotas.fotos.update', [$mascota, $foto]), [
            'fecha' => '2026-08-02',
            'epigrafe' => 'Hackeado',
        ])
        ->assertForbidden();

    expect($foto->fresh()->epigrafe)->toBe('Antes');
});

it('no deja editar la foto de una mascota fallecida', function () {
    // Regla 3: pasa a modo lectura, conserva todo pero no se toca.
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $foto = fotoDeGaleria($mascota);
    $mascota->update(['fecha_fallecimiento' => '2026-07-01']);

    $this->actingAs($usuario)
        ->patch(route('mascotas.fotos.update', [$mascota, $foto]), [
            'fecha' => '2026-08-02',
            'epigrafe' => 'Nuevo',
        ])
        ->assertForbidden();
});

it('no deja editar una foto que es de otra mascota', function () {
    // Las dos son del mismo usuario, así que la Policy pasa: lo que corta es la
    // comprobación de que la foto pertenezca a la mascota de la ruta.
    $usuario = User::factory()->create();
    $unaMascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $otraMascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $foto = fotoDeGaleria($otraMascota);

    $this->actingAs($usuario)
        ->patch(route('mascotas.fotos.update', [$unaMascota, $foto]), [
            'fecha' => '2026-08-02',
            'epigrafe' => 'Cruzado',
        ])
        ->assertNotFound();

    expect($foto->fresh()->epigrafe)->toBe('Antes');
});

it('la galería llega a la pantalla con la URL grande y la miniatura', function () {
    // El visor muestra la grande; la grilla, la miniatura.
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    fotoDeGaleria($mascota);

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('fotos', 1)
            ->has('fotos.0.url')
            ->has('fotos.0.miniatura_url')
            ->where('fotos.0.epigrafe', 'Antes'),
        );
});
