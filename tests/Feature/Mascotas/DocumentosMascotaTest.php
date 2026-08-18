<?php

use App\Enums\EstadoRecordatorio;
use App\Enums\TipoAdjunto;
use App\Enums\TipoRecordatorio;
use App\Models\Adjunto;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\User;
use App\Services\ExportadorDatosService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * La libreta sanitaria y el certificado de rabia: los dos papeles que el dueño
 * carga una vez y muestra afuera de casa.
 *
 * Cuelgan de la mascota y no de una visita, así que la autorización sube por la
 * relación polimórfica hasta ella. Y el vencimiento del certificado es una fecha
 * de `mascotas` que genera su recordatorio por observer, igual que el seguro.
 */

beforeEach(function () {
    Storage::fake('local');
});

function mascotaParaDocumentos(User $usuario): Mascota
{
    return Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
}

it('sube varios archivos de una sola vez', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $this->actingAs($usuario)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::LibretaSanitaria->value,
            'archivos' => [
                UploadedFile::fake()->image('hoja-1.jpg'),
                UploadedFile::fake()->image('hoja-2.jpg'),
                UploadedFile::fake()->create('libreta.pdf', 200, 'application/pdf'),
            ],
            'descripcion' => 'Hojas 1 a 4',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $documentos = $mascota->adjuntos()->get();

    expect($documentos)->toHaveCount(3)
        ->and($documentos->pluck('tipo')->unique()->all())
        ->toBe([TipoAdjunto::LibretaSanitaria])
        ->and($documentos->pluck('descripcion')->unique()->all())->toBe(['Hojas 1 a 4']);

    // El original se conserva tal cual, en el disco privado.
    foreach ($documentos as $documento) {
        Storage::assertExists($documento->ruta);
        expect($documento->ruta)->toStartWith("mascotas/{$mascota->id}/documentos/");
    }
});

it('genera la miniatura de las imágenes y no de los PDF', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $this->actingAs($usuario)->post(route('mascotas.documentos.store', $mascota), [
        'tipo' => TipoAdjunto::CertificadoRabia->value,
        'archivos' => [
            UploadedFile::fake()->image('certificado.jpg'),
            UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'),
        ],
    ]);

    $imagen = $mascota->adjuntos()->where('mime', 'like', 'image/%')->sole();
    $pdf = $mascota->adjuntos()->where('mime', 'application/pdf')->sole();

    $miniatura = fn (string $ruta) => preg_replace('/\.[^.]+$/', '', $ruta).'-min.webp';

    Storage::assertExists($miniatura($imagen->ruta));
    Storage::assertMissing($miniatura($pdf->ruta));
});

it('rechaza un tipo que no es documentación de la mascota', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    // Una radiografía pertenece a una visita, no a la ficha.
    $this->actingAs($usuario)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::Radiografia->value,
            'archivos' => [UploadedFile::fake()->image('rx.jpg')],
        ])
        ->assertSessionHasErrors('tipo');

    expect($mascota->adjuntos()->count())->toBe(0);
});

it('rechaza un archivo que no es imagen ni PDF', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $this->actingAs($usuario)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::LibretaSanitaria->value,
            'archivos' => [UploadedFile::fake()->create('libreta.docx', 100)],
        ])
        ->assertSessionHasErrors('archivos.0');

    expect($mascota->adjuntos()->count())->toBe(0);
});

it('no deja subir documentos a la mascota de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();

    $this->actingAs($intruso)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::LibretaSanitaria->value,
            'archivos' => [UploadedFile::fake()->image('ajena.jpg')],
        ])
        ->assertForbidden();

    expect($mascota->adjuntos()->count())->toBe(0);
});

it('no deja bajar ni borrar el documento de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();

    $documento = Adjunto::factory()->create([
        'adjuntable_type' => $mascota->getMorphClass(),
        'adjuntable_id' => $mascota->id,
        'tipo' => TipoAdjunto::CertificadoRabia,
    ]);

    // Adivinar el id no alcanza: la Policy sube por la relación polimórfica.
    $this->actingAs($intruso)
        ->get(route('adjuntos.mostrar', $documento))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('adjuntos.destroy', $documento))
        ->assertForbidden();

    expect($documento->fresh())->not->toBeNull();
});

it('respeta la regla 3: una mascota fallecida no recibe documentos nuevos', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);
    $mascota->update(['fecha_fallecimiento' => '2026-07-01']);

    $this->actingAs($usuario)
        ->post(route('mascotas.documentos.store', $mascota), [
            'tipo' => TipoAdjunto::LibretaSanitaria->value,
            'archivos' => [UploadedFile::fake()->image('hoja.jpg')],
        ])
        ->assertForbidden();
});

it('el vencimiento del certificado genera su recordatorio', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $this->actingAs($usuario)
        ->patch(route('mascotas.vencimiento-rabia', $mascota), [
            'rabia_vencimiento' => '2027-03-15',
        ])
        ->assertRedirect();

    $recordatorio = Recordatorio::query()
        ->where('mascota_id', $mascota->id)
        ->where('tipo', TipoRecordatorio::CertificadoRabia)
        ->sole();

    expect($recordatorio->fecha_objetivo->toDateString())->toBe('2027-03-15')
        ->and($recordatorio->titulo)->toContain('Greta')
        ->and($recordatorio->origen_id)->toBe($mascota->id);
});

it('mover la fecha mueve el recordatorio en vez de crear otro', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $this->actingAs($usuario)->patch(route('mascotas.vencimiento-rabia', $mascota), [
        'rabia_vencimiento' => '2027-03-15',
    ]);
    $this->actingAs($usuario)->patch(route('mascotas.vencimiento-rabia', $mascota), [
        'rabia_vencimiento' => '2027-04-20',
    ]);

    $recordatorios = Recordatorio::query()
        ->where('mascota_id', $mascota->id)
        ->where('tipo', TipoRecordatorio::CertificadoRabia)
        ->get();

    // Idempotente por origen_type + origen_id + tipo.
    expect($recordatorios)->toHaveCount(1)
        ->and($recordatorios->first()->fecha_objetivo->toDateString())->toBe('2027-04-20');
});

it('convive con el recordatorio del seguro sobre la misma mascota', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    // Los dos salen de la misma fila, y la clave de idempotencia incluye el tipo.
    $mascota->update([
        'seguro_vencimiento' => '2027-01-10',
        'rabia_vencimiento' => '2027-03-15',
    ]);

    $tipos = Recordatorio::query()
        ->where('mascota_id', $mascota->id)
        ->pluck('tipo')
        ->map(fn ($tipo) => $tipo->value)
        ->sort()
        ->values()
        ->all();

    expect($tipos)->toBe(['certificado_rabia', 'seguro']);
});

it('vaciar la fecha descarta el aviso', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $this->actingAs($usuario)->patch(route('mascotas.vencimiento-rabia', $mascota), [
        'rabia_vencimiento' => '2027-03-15',
    ]);
    $this->actingAs($usuario)->patch(route('mascotas.vencimiento-rabia', $mascota), [
        'rabia_vencimiento' => null,
    ]);

    expect($mascota->fresh()->rabia_vencimiento)->toBeNull();

    $recordatorio = Recordatorio::query()
        ->where('mascota_id', $mascota->id)
        ->where('tipo', TipoRecordatorio::CertificadoRabia)
        ->first();

    expect($recordatorio?->estado)->toBe(EstadoRecordatorio::Descartado);
});

it('acepta un certificado ya vencido, porque es un dato real', function () {
    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = mascotaParaDocumentos($usuario);

    $ayer = $usuario->hoyCalendario()->subDay()->toDateString();

    $this->actingAs($usuario)
        ->patch(route('mascotas.vencimiento-rabia', $mascota), ['rabia_vencimiento' => $ayer])
        ->assertSessionHasNoErrors();

    expect($mascota->fresh()->estado_rabia)
        ->toMatchArray(['estado' => 'vencido', 'dias' => -1, 'texto' => 'Venció ayer']);
});

it('mide el estado contra el día del propietario, no contra el del servidor', function () {
    /*
     * `rabia_vencimiento` es una columna `date`, que Carbon lee a medianoche UTC.
     * A las 22:00 de Buenos Aires el servidor ya está en el día siguiente, así que
     * medir contra `now()` diría "vence hoy" cuando para el usuario vence mañana.
     * Se congela el reloj en ese hueco de tres horas, que es donde se rompe.
     */
    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = mascotaParaDocumentos($usuario);

    // 01:30 UTC del 19 = 22:30 del 18 en Buenos Aires.
    $this->travelTo('2026-08-19 01:30:00');

    $mascota->update(['rabia_vencimiento' => '2026-08-19']);

    expect($mascota->fresh()->estado_rabia)
        ->toMatchArray(['estado' => 'por_vencer', 'dias' => 1, 'texto' => 'Vence mañana']);
});

it('la ficha muestra los documentos agrupados por tipo', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    Adjunto::factory()->count(2)->create([
        'adjuntable_type' => $mascota->getMorphClass(),
        'adjuntable_id' => $mascota->id,
        'tipo' => TipoAdjunto::LibretaSanitaria,
    ]);

    $this->actingAs($usuario)
        ->get(route('mascotas.show', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('documentos.libreta_sanitaria', 2)
            // La clave viene igual aunque esté vacía, para que la tarjeta se
            // dibuje y pueda ofrecer el botón de subir.
            ->has('documentos.certificado_rabia', 0),
        );
});

it('la exportación de datos incluye los documentos, con su enlace y sin el archivo', function () {
    $usuario = User::factory()->create();
    $mascota = mascotaParaDocumentos($usuario);

    $documento = Adjunto::factory()->create([
        'adjuntable_type' => $mascota->getMorphClass(),
        'adjuntable_id' => $mascota->id,
        'tipo' => TipoAdjunto::CertificadoRabia,
        'nombre_original' => 'antirrabica-2026.pdf',
    ]);

    $datos = app(ExportadorDatosService::class)->para($usuario);
    $documentos = $datos['mascotas'][0]['documentos'];

    expect($documentos)->toHaveCount(1)
        ->and($documentos[0]['nombre'])->toBe('antirrabica-2026.pdf')
        ->and($documentos[0]['tipo'])->toBe('Certificado de rabia')
        ->and($documentos[0]['descargar_en'])->toContain((string) $documento->id)
        // El archivo no viaja adentro del JSON: solo de dónde bajarlo.
        ->and($documentos[0])->not->toHaveKey('contenido');
});
