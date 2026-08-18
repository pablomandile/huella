<?php

use App\Models\Alimento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * La foto del paquete de alimento: sirve para reconocer la bolsa en la góndola.
 *
 * Se recomprime a WebP —al contrario que un adjunto clínico, donde el original es
 * la prueba— y vive en el disco privado, servida por controlador.
 */

beforeEach(function () {
    Storage::fake('local');
});

function datosDeAlimento(array $extra = []): array
{
    return [
        'nombre' => 'Balanceado adulto',
        'tipo' => 'balanceado_seco',
        'especie' => 'perro',
        'etapa' => 'adulto',
        ...$extra,
    ];
}

it('guarda la foto del paquete al dar de alta', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.alimentos.store'), datosDeAlimento([
            'foto' => UploadedFile::fake()->image('bolsa.jpg', 2000, 2000),
        ]))
        ->assertRedirect();

    $alimento = Alimento::query()->where('usuario_id', $usuario->id)->sole();

    expect($alimento->foto)->not->toBeNull()
        // Se convierte a WebP: la extensión original no sobrevive.
        ->and($alimento->foto)->toEndWith('.webp');

    Storage::assertExists($alimento->foto);
    Storage::assertExists($alimento->ruta_foto_miniatura);
});

it('recibe la foto al editar, que llega por POST con _method', function () {
    /*
     * Es el test que importa de todos: **PHP no parsea el cuerpo multipart de un
     * PUT**. Si el formulario se enviara por PUT, `$request->file()` llegaría
     * vacío y la foto se perdería sin error de validación ni ningún síntoma. El
     * front manda POST + `_method=put` justamente por esto.
     */
    $usuario = User::factory()->create();
    $alimento = Alimento::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.alimentos.update', $alimento), datosDeAlimento([
            '_method' => 'put',
            'foto' => UploadedFile::fake()->image('bolsa.jpg'),
        ]))
        ->assertRedirect();

    expect($alimento->fresh()->foto)->not->toBeNull();
});

it('al reemplazarla borra la anterior', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.alimentos.store'), datosDeAlimento([
        'foto' => UploadedFile::fake()->image('vieja.jpg'),
    ]));

    $alimento = Alimento::query()->where('usuario_id', $usuario->id)->sole();
    $vieja = $alimento->foto;
    $viejaMin = $alimento->ruta_foto_miniatura;

    $this->actingAs($usuario)->post(
        route('catalogos.alimentos.update', $alimento),
        datosDeAlimento(['_method' => 'put', 'foto' => UploadedFile::fake()->image('nueva.jpg')]),
    );

    $nueva = $alimento->fresh()->foto;

    expect($nueva)->not->toBe($vieja);
    Storage::assertMissing($vieja);
    Storage::assertMissing($viejaMin);
    Storage::assertExists($nueva);
});

it('la quita cuando se pide, y borra los archivos', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.alimentos.store'), datosDeAlimento([
        'foto' => UploadedFile::fake()->image('bolsa.jpg'),
    ]));

    $alimento = Alimento::query()->where('usuario_id', $usuario->id)->sole();
    $ruta = $alimento->foto;
    $miniatura = $alimento->ruta_foto_miniatura;

    $this->actingAs($usuario)->post(
        route('catalogos.alimentos.update', $alimento),
        datosDeAlimento(['_method' => 'put', 'quitar_foto' => '1']),
    );

    expect($alimento->fresh()->foto)->toBeNull();
    Storage::assertMissing($ruta);
    Storage::assertMissing($miniatura);
});

it('editar sin tocar la foto la conserva', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.alimentos.store'), datosDeAlimento([
        'foto' => UploadedFile::fake()->image('bolsa.jpg'),
    ]));

    $alimento = Alimento::query()->where('usuario_id', $usuario->id)->sole();
    $ruta = $alimento->foto;

    $this->actingAs($usuario)->post(
        route('catalogos.alimentos.update', $alimento),
        datosDeAlimento(['_method' => 'put', 'nombre' => 'Otro nombre']),
    );

    expect($alimento->fresh())
        ->nombre->toBe('Otro nombre')
        ->foto->toBe($ruta);

    Storage::assertExists($ruta);
});

it('la copia no hereda la foto, para no compartir el archivo', function () {
    /*
     * Dos filas apuntando al mismo archivo se ven bien hasta que alguien
     * reemplaza la imagen de una: el borrado de la vieja deja a la otra sin foto.
     */
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.alimentos.store'), datosDeAlimento([
        'foto' => UploadedFile::fake()->image('bolsa.jpg'),
    ]));

    $original = Alimento::query()->where('usuario_id', $usuario->id)->sole();

    $this->actingAs($usuario)
        ->post(route('catalogos.alimentos.duplicar', $original))
        ->assertRedirect();

    $copia = Alimento::query()
        ->where('usuario_id', $usuario->id)
        ->where('id', '!=', $original->id)
        ->sole();

    expect($copia->nombre)->toContain('(copia)')
        ->and($copia->foto)->toBeNull();

    // Y la del original sigue en su lugar.
    Storage::assertExists($original->fresh()->foto);
});

it('sirve la foto por controlador y no por URL pública', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.alimentos.store'), datosDeAlimento([
        'foto' => UploadedFile::fake()->image('bolsa.jpg'),
    ]));

    $alimento = Alimento::query()->where('usuario_id', $usuario->id)->sole();

    $this->actingAs($usuario)
        ->get(route('catalogos.alimentos.foto', $alimento))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, private');

    $this->actingAs($usuario)
        ->get(route('catalogos.alimentos.foto', ['alimento' => $alimento, 'min' => 1]))
        ->assertOk();
});

it('no deja ver la foto del alimento de otra cuenta', function () {
    $usuario = User::factory()->create();
    $intruso = User::factory()->create();

    $this->actingAs($usuario)->post(route('catalogos.alimentos.store'), datosDeAlimento([
        'foto' => UploadedFile::fake()->image('bolsa.jpg'),
    ]));

    $alimento = Alimento::query()->where('usuario_id', $usuario->id)->sole();

    $this->actingAs($intruso)
        ->get(route('catalogos.alimentos.foto', $alimento))
        ->assertForbidden();
});

it('da 404 si el alimento no tiene foto', function () {
    $usuario = User::factory()->create();
    $alimento = Alimento::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->get(route('catalogos.alimentos.foto', $alimento))
        ->assertNotFound();
});

it('rechaza un archivo que no es imagen', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('catalogos.alimentos.store'), datosDeAlimento([
            'foto' => UploadedFile::fake()->create('etiqueta.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('foto');

    expect(Alimento::query()->where('usuario_id', $usuario->id)->count())->toBe(0);
});

it('el alta al vuelo devuelve la URL de la foto en el JSON', function () {
    // Es lo que permite que el combo muestre el paquete recién creado sin
    // recargar la pantalla donde se estaba cargando una dieta.
    $usuario = User::factory()->create();

    $respuesta = $this->actingAs($usuario)
        ->postJson(route('catalogos.alimentos.store'), datosDeAlimento([
            'foto' => UploadedFile::fake()->image('bolsa.jpg'),
        ]));

    $respuesta->assertCreated();

    expect($respuesta->json('registro.foto_miniatura_url'))->toContain('/foto');
});
