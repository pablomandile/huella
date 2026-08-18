<?php

use App\Enums\TipoAdjunto;
use App\Models\Alimento;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Una imagen con cabecera válida y píxeles corruptos.
 *
 * Es el caso real de una foto que se cortó a mitad de la subida con mala señal,
 * que es exactamente el escenario de esta app: el celular en la veterinaria.
 *
 * `image` y `mimes:` de Laravel solo miran los primeros bytes, así que un archivo
 * así **pasa las dos reglas**. Antes de esto, el decodificador fallaba después y
 * el usuario recibía una pantalla de error 500 sin poder hacer nada.
 */

beforeEach(function () {
    Storage::fake('local');
});

/** PNG con IHDR válido —`getimagesize` lo acepta— y datos de píxel roídos. */
function pngRoto(string $nombre = 'cortada.png'): UploadedFile
{
    $contenido = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
    );

    $ruta = tempnam(sys_get_temp_dir(), 'png').'.png';
    file_put_contents($ruta, $contenido);

    return new UploadedFile($ruta, $nombre, 'image/png', test: true);
}

it('el archivo de prueba pasa por imagen para Laravel', function () {
    // Si esto dejara de ser cierto, los tests de abajo probarían otra cosa.
    $datos = @getimagesize(pngRoto()->getRealPath());

    expect($datos)->not->toBeFalse()
        ->and($datos[2])->toBe(IMAGETYPE_PNG);
});

it('un documento con la imagen dañada se guarda igual, sin miniatura', function () {
    /*
     * Acá el archivo es lo que importa y la miniatura es solo la vista previa de
     * la lista. Perder el documento entero por no poder hacerle la previa sería
     * el peor intercambio posible.
     */
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::LibretaSanitaria->value,
            'archivos' => [pngRoto()],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $documento = $mascota->adjuntos()->sole();

    Storage::assertExists($documento->ruta);
    Storage::assertMissing(preg_replace('/\.[^.]+$/', '', $documento->ruta).'-min.webp');
});

it('el documento sin miniatura se sirve igual, cayendo al original', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)->post(route('mascotas.documentos.store', $mascota), [
        'tipo' => TipoAdjunto::LibretaSanitaria->value,
        'archivos' => [pngRoto()],
    ]);

    $documento = $mascota->adjuntos()->sole();

    // Con `min=1` no hay miniatura, así que devuelve el original en vez de 404.
    $this->actingAs($usuario)
        ->get(route('adjuntos.mostrar', ['adjunto' => $documento->id, 'min' => 1]))
        ->assertOk();
});

it('la foto de un alimento dañada da un error de validación, no un 500', function () {
    /*
     * Al revés que el documento: acá la imagen convertida **es** el producto, no
     * hay nada que guardar si no se puede leer. Entonces se rechaza con un
     * mensaje que el usuario pueda accionar —sacar otra foto—.
     */
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.alimentos.store'), [
            'nombre' => 'Balanceado',
            'tipo' => 'balanceado_seco',
            'especie' => 'perro',
            'etapa' => 'adulto',
            'foto' => pngRoto(),
        ])
        ->assertSessionHasErrors('foto');

    expect(Alimento::query()->where('usuario_id', $usuario->id)->count())->toBe(0);
});

it('la foto de perfil de una mascota dañada también se rechaza', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.store'), [
            'nombre' => 'Greta',
            'especie' => 'perro',
            'sexo' => 'hembra',
            'foto' => pngRoto(),
        ])
        ->assertSessionHasErrors('foto');
});

it('una imagen sana sigue pasando y genera su miniatura', function () {
    // El guard del guard: que la regla nueva no rechace lo que sí se puede leer.
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::CertificadoRabia->value,
            'archivos' => [UploadedFile::fake()->image('sana.jpg', 300, 300)],
        ])
        ->assertSessionHasNoErrors();

    $documento = $mascota->adjuntos()->sole();

    Storage::assertExists(preg_replace('/\.[^.]+$/', '', $documento->ruta).'-min.webp');
});
