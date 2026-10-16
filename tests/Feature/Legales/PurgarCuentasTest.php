<?php

use App\Models\Adjunto;
use App\Models\Mascota;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Support\Facades\Storage;

/*
 * «Eliminar cuenta» marca la fila (SoftDeletes); a los 30 días
 * `huella:purgar-cuentas` la borra de verdad, con sus archivos. Es lo que
 * promete la política de privacidad.
 */

function cuentaConDatos(int $diasDesdeLaBaja): array
{
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->create(['usuario_id' => $usuario->id]);
    $visita = Visita::factory()->create(['mascota_id' => $mascota->id]);
    $adjunto = Adjunto::factory()->create([
        'adjuntable_type' => Visita::class,
        'adjuntable_id' => $visita->id,
        'ruta' => "mascotas/{$mascota->id}/adjuntos/receta.pdf",
    ]);

    Storage::put($adjunto->ruta, 'pdf');
    Storage::put("mascotas/{$mascota->id}/foto.webp", 'webp');

    $usuario->delete();
    $usuario->forceFill(['deleted_at' => now()->subDays($diasDesdeLaBaja)])->save();

    return [$usuario, $mascota, $adjunto];
}

it('borra de verdad la cuenta, sus mascotas, los adjuntos y los archivos pasados 30 días', function () {
    Storage::fake();
    [$usuario, $mascota, $adjunto] = cuentaConDatos(31);

    $this->artisan('huella:purgar-cuentas')->assertSuccessful();

    expect(User::withTrashed()->find($usuario->id))->toBeNull()
        ->and(Mascota::withTrashed()->find($mascota->id))->toBeNull()
        ->and(Adjunto::find($adjunto->id))->toBeNull();

    Storage::assertMissing($adjunto->ruta);
    Storage::assertMissing("mascotas/{$mascota->id}/foto.webp");
});

it('no toca una cuenta eliminada hace menos de 30 días', function () {
    Storage::fake();
    [$usuario, $mascota, $adjunto] = cuentaConDatos(29);

    $this->artisan('huella:purgar-cuentas')->assertSuccessful();

    expect(User::withTrashed()->find($usuario->id))->not->toBeNull()
        ->and(Adjunto::find($adjunto->id))->not->toBeNull();

    Storage::assertExists($adjunto->ruta);
});

it('no toca una cuenta activa', function () {
    Storage::fake();
    $usuario = User::factory()->create(['created_at' => now()->subYear()]);

    $this->artisan('huella:purgar-cuentas')->assertSuccessful();

    expect(User::find($usuario->id))->not->toBeNull();
});
