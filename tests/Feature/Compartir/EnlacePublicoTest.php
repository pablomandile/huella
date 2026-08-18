<?php

use App\Enums\RolCuidador;
use App\Enums\TipoAdjunto;
use App\Models\Adjunto;
use App\Models\EnlaceCompartido;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Support\Facades\Storage;

/*
 * El enlace que muestra la ficha **sin cuenta**.
 *
 * Es la única puerta de Huella que no pasa por `auth`, así que contradice el
 * requisito de privacidad de la especificación y hay que acotarla con cuidado.
 * Lo que se prueba acá no es que funcione: es que no muestre de más.
 */

function enlaceDe(Mascota $mascota, array $estado = []): EnlaceCompartido
{
    return EnlaceCompartido::factory()->create([
        'mascota_id' => $mascota->id,
        'creado_por' => $mascota->usuario_id,
        ...$estado,
    ]);
}

beforeEach(fn () => Storage::fake('local'));

function adjuntoDe(mixed $duenio, TipoAdjunto $tipo): Adjunto
{
    $adjunto = Adjunto::factory()->create([
        'adjuntable_type' => $duenio->getMorphClass(),
        'adjuntable_id' => $duenio->id,
        'tipo' => $tipo,
        'ruta' => "adjuntos/{$tipo->value}.pdf",
    ]);

    Storage::put($adjunto->ruta, 'contenido');

    return $adjunto;
}

it('muestra la ficha sin sesión, con las alergias primero', function () {
    $duenio = User::factory()->create(['name' => 'Pablo']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create(['nombre' => 'Greta']);
    Visita::factory()->for($mascota)->create(['diagnostico' => 'Otitis externa']);

    $this->get(route('compartido.ficha', enlaceDe($mascota)->token))
        ->assertOk()
        ->assertSee('Greta')
        ->assertSee('Alergias')
        ->assertSee('Otitis externa')
        ->assertSee('Pablo');
});

it('no filtra los datos privados del dueño ni los financieros', function () {
    $duenio = User::factory()->create([
        'name' => 'Pablo',
        'email' => 'privado@ejemplo.test',
        'telefono' => '1155667788',
    ]);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create([
        'seguro_compania' => 'AseguradoraSecreta',
        'seguro_poliza' => 'POL-99887',
    ]);
    Visita::factory()->for($mascota)->create(['costo' => 45000]);

    // La vista arma la ficha con un listado explícito de campos, no volcando el
    // modelo: lo que no está en esa lista no puede aparecer por accidente.
    $this->get(route('compartido.ficha', enlaceDe($mascota)->token))
        ->assertOk()
        ->assertDontSee('privado@ejemplo.test')
        ->assertDontSee('1155667788')
        ->assertDontSee('AseguradoraSecreta')
        ->assertDontSee('POL-99887')
        ->assertDontSee('45000');
});

it('manda los headers que impiden que la ficha se indexe o se cachee', function () {
    $mascota = Mascota::factory()->create(['foto_perfil' => 'mascotas/greta.webp']);
    Storage::put('mascotas/greta.webp', 'imagen');

    $token = enlaceDe($mascota)->token;

    // En el HTML y también en la imagen: el X-Robots-Tag va en header y no solo
    // en un meta justamente para cubrir lo que no es HTML.
    foreach ([route('compartido.ficha', $token), route('compartido.foto', $token)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        // `no-store` y no el `private, max-age=86400` de las rutas autenticadas:
        // copiar aquella línea dejaría una radiografía un día entero en el disco
        // del navegador de la veterinaria.
        expect($this->get($url)->headers->get('X-Robots-Tag'))->toContain('noindex');
    }
});

it('distingue un enlace inexistente de uno vencido, y ninguno muestra datos', function () {
    $mascota = Mascota::factory()->create(['nombre' => 'Greta']);

    // Inexistente o revocado: 404. Para llegar acá hay que tener el token, así
    // que decir "venció" no le revela nada a nadie que no lo tuviera ya.
    $this->get(route('compartido.ficha', 'token-que-no-existe'))
        ->assertNotFound()
        ->assertDontSee('Greta');

    $this->get(route('compartido.ficha', enlaceDe($mascota, ['expira_en' => now()->subDay()])->token))
        ->assertStatus(410)
        ->assertSee('ya venció')
        ->assertDontSee('Greta');
});

it('deja de funcionar apenas se revoca', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $enlace = enlaceDe($mascota);

    $this->get(route('compartido.ficha', $enlace->token))->assertOk();

    $this->actingAs($duenio)
        ->delete(route('mascotas.enlaces.destroy', [$mascota, $enlace]))
        ->assertRedirect();

    $this->get(route('compartido.ficha', $enlace->token))->assertNotFound();
});

it('muere cuando la mascota se da de baja', function () {
    $duenio = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $enlace = enlaceDe($mascota);

    $mascota->delete();

    // El 404 saldría igual porque el soft delete no se encuentra, pero además la
    // fila se borra: restaurar la mascota no puede resucitar sus enlaces.
    $this->get(route('compartido.ficha', $enlace->token))->assertNotFound();
    $this->assertDatabaseMissing('enlaces_compartidos', ['id' => $enlace->id]);
});

it('sirve la libreta sanitaria pero nunca una factura', function () {
    $mascota = Mascota::factory()->create();
    $token = enlaceDe($mascota)->token;

    $libreta = adjuntoDe($mascota, TipoAdjunto::LibretaSanitaria);
    $visita = Visita::factory()->for($mascota)->create();
    $factura = adjuntoDe($visita, TipoAdjunto::Factura);

    // La libreta y el certificado de rabia son el motivo del enlace.
    $this->get(route('compartido.adjunto', [$token, $libreta]))->assertOk();

    // La factura es dato financiero: no entra ni con los adjuntos activados.
    $this->get(route('compartido.adjunto', [$token, $factura]))->assertNotFound();
});

it('sirve los estudios solo si el dueño los incluyó', function () {
    $mascota = Mascota::factory()->create();
    $visita = Visita::factory()->for($mascota)->create();
    $radiografia = adjuntoDe($visita, TipoAdjunto::Radiografia);

    $sinAdjuntos = enlaceDe($mascota);
    $conAdjuntos = enlaceDe($mascota, ['incluye_adjuntos' => true]);

    $this->get(route('compartido.adjunto', [$sinAdjuntos->token, $radiografia]))->assertNotFound();
    $this->get(route('compartido.adjunto', [$conAdjuntos->token, $radiografia]))->assertOk();

    // Y la ficha tampoco lo nombra cuando no corresponde.
    $this->get(route('compartido.ficha', $sinAdjuntos->token))
        ->assertOk()
        ->assertDontSee(route('compartido.adjunto', [$sinAdjuntos->token, $radiografia]));
});

it('no sirve el adjunto de otra mascota aunque se adivine el id', function () {
    $mascota = Mascota::factory()->create();
    $ajena = Mascota::factory()->create();

    $visitaAjena = Visita::factory()->for($ajena)->create();
    $radiografiaAjena = adjuntoDe($visitaAjena, TipoAdjunto::Radiografia);
    $libretaAjena = adjuntoDe($ajena, TipoAdjunto::LibretaSanitaria);

    // El token de una mascota no es una llave general al disco: `alcanza()` sube
    // por la relación polimórfica igual que la Policy.
    $token = enlaceDe($mascota, ['incluye_adjuntos' => true])->token;

    $this->get(route('compartido.adjunto', [$token, $radiografiaAjena]))->assertNotFound();
    $this->get(route('compartido.adjunto', [$token, $libretaAjena]))->assertNotFound();
});

it('no muestra la galería de fotos', function () {
    $mascota = Mascota::factory()->create();
    $foto = FotoMascota::factory()->for($mascota)->create(['epigrafe' => 'En la playa']);

    // La galería es vida familiar —la casa, los chicos, el barrio de fondo— y no
    // información clínica. No tiene nada que hacer en un enlace que circula.
    $this->get(route('compartido.ficha', enlaceDe($mascota)->token))
        ->assertOk()
        ->assertDontSee('En la playa')
        ->assertDontSee($foto->ruta);
});

it('el token no abre ninguna ruta autenticada', function () {
    $mascota = Mascota::factory()->create();
    $enlace = enlaceDe($mascota);

    // Tener el enlace no es tener sesión.
    $this->get(route('mascotas.show', $mascota))->assertRedirect(route('login'));
    $this->get(route('mascotas.diario.index', $mascota))->assertRedirect(route('login'));
    $this->get(route('mascotas.foto-perfil', $mascota))->assertRedirect(route('login'));
    $this->get(route('mascotas.historia-clinica', $mascota))->assertRedirect(route('login'));

    expect($enlace->fresh()->visitas)->toBe(0);
});

it('cuenta las aperturas de la ficha y no las del PDF', function () {
    $mascota = Mascota::factory()->create();
    $enlace = enlaceDe($mascota);

    $this->get(route('compartido.ficha', $enlace->token))->assertOk();
    $this->get(route('compartido.ficha', $enlace->token))->assertOk();
    $this->get(route('compartido.pdf', $enlace->token))->assertOk();

    // Es el único dato con el que el dueño se entera de que un enlace se le
    // escapó: si contara cada imagen y cada PDF, dejaría de significar algo.
    expect($enlace->fresh()->visitas)->toBe(2)
        ->and($enlace->fresh()->ultimo_acceso_en)->not->toBeNull();
});

it('corta al que prueba tokens al voleo', function () {
    // Un visitante legítimo nunca falla; el que falla diez veces está probando.
    for ($i = 0; $i < 10; $i++) {
        $this->get(route('compartido.ficha', "invento-{$i}"))->assertNotFound();
    }

    $this->get(route('compartido.ficha', 'invento-11'))->assertStatus(429);
});

it('solo el propietario crea y revoca enlaces', function () {
    $duenio = User::factory()->create();
    $lector = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    $enlace = enlaceDe($mascota);

    foreach ([$lector, $intruso] as $quien) {
        $this->actingAs($quien)
            ->post(route('mascotas.enlaces.store', $mascota), ['vigencia' => '30'])
            ->assertForbidden();

        $this->actingAs($quien)
            ->delete(route('mascotas.enlaces.destroy', [$mascota, $enlace]))
            ->assertForbidden();
    }

    expect($mascota->enlaces()->count())->toBe(1);
});

it('no acepta un vencimiento inventado por el cliente', function () {
    $duenio = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // Una vigencia fuera del enum no pasa...
    $this->actingAs($duenio)
        ->post(route('mascotas.enlaces.store', $mascota), ['vigencia' => '3650'])
        ->assertSessionHasErrors('vigencia');

    // ...y una fecha mandada a mano se ignora: la calcula el servidor.
    $this->actingAs($duenio)
        ->post(route('mascotas.enlaces.store', $mascota), [
            'vigencia' => '7',
            'expira_en' => '3000-01-01 00:00:00',
        ])
        ->assertRedirect();

    expect($mascota->enlaces()->sole()->expira_en->year)->toBe(now()->year);
});
