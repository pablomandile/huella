<?php

use App\Enums\RolCuidador;
use App\Models\Adjunto;
use App\Models\Mascota;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * La historia clínica de una mascota no se ve ni se toca desde otra cuenta.
 *
 * El caso más delicado es el adjunto: no cuelga de la mascota sino de la visita,
 * así que la autorización tiene que subir por la relación polimórfica. Adivinar
 * el id no puede alcanzar.
 */

it('no deja ver ni editar la visita de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();
    $visita = Visita::factory()->for($mascota)->create(['motivo' => 'Privado']);

    $this->actingAs($intruso)
        ->get(route('mascotas.visitas.show', [$mascota, $visita]))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->get(route('mascotas.visitas.index', $mascota))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->put(route('mascotas.visitas.update', [$mascota, $visita]), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
            'motivo' => 'Hackeado',
        ])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('mascotas.visitas.destroy', [$mascota, $visita]))
        ->assertForbidden();

    expect($visita->refresh()->motivo)->toBe('Privado');
});

it('no sirve el adjunto de otra cuenta aunque se adivine el id', function () {
    Storage::fake('local');

    $duenio = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $visita = Visita::factory()->for($mascota)->create();

    $this->actingAs($duenio)->post(
        route('mascotas.visitas.adjuntos.store', [$mascota, $visita]),
        [
            'archivo' => UploadedFile::fake()->create('analisis.pdf', 50, 'application/pdf'),
            'tipo' => 'analisis',
        ],
    );

    $adjunto = Adjunto::sole();

    // El dueño sí.
    $this->actingAs($duenio)->get(route('adjuntos.mostrar', $adjunto))->assertOk();

    // Cualquier otro, no: la Policy sube adjunto → visita → mascota → pivote.
    $this->actingAs($intruso)->get(route('adjuntos.mostrar', $adjunto))->assertForbidden();
    $this->actingAs($intruso)->delete(route('adjuntos.destroy', $adjunto))->assertForbidden();

    // Y sin sesión tampoco hay URL pública que valga.
    auth()->logout();
    $this->get(route('adjuntos.mostrar', $adjunto))->assertRedirect(route('login'));

    Storage::assertExists($adjunto->ruta);
});

it('no deja marcar la toma de otra cuenta', function () {
    $intruso = User::factory()->create();
    $tratamiento = Tratamiento::factory()->create();
    $toma = TomaMedicamento::factory()->for($tratamiento)->create();

    $this->actingAs($intruso)
        ->patch(route('medicacion.update', $toma), ['estado' => 'administrada'])
        ->assertForbidden();

    expect($toma->refresh()->estaPendiente())->toBeTrue();
});

it('la medicación de hoy solo trae las mascotas del usuario', function () {
    $usuario = User::factory()->create();
    $mia = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    $ajena = Mascota::factory()->create(['nombre' => 'Ajena']);

    foreach ([$mia, $ajena] as $mascota) {
        $tratamiento = Tratamiento::factory()->create(['mascota_id' => $mascota->id]);
        TomaMedicamento::factory()->for($tratamiento)->create([
            'fecha_hora_programada' => now(),
        ]);
    }

    $this->actingAs($usuario)
        ->get(route('medicacion.index'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('medicacion/Index')
            ->has('tomas', 1)
            ->where('tomas.0.mascota_nombre', 'Greta'),
        );
});

it('un cuidador lector ve el historial pero no lo modifica', function () {
    $duenio = User::factory()->create();
    $lector = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $visita = Visita::factory()->for($mascota)->create();

    // Multi-cuidador de v2: una fila más en el pivote, ninguna Policy reescrita.
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    $this->actingAs($lector)
        ->get(route('mascotas.visitas.show', [$mascota, $visita]))
        ->assertOk();

    $this->actingAs($lector)
        ->put(route('mascotas.visitas.update', [$mascota, $visita]), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
        ])
        ->assertForbidden();
});

it('no mezcla la visita de una mascota con la ruta de otra', function () {
    $usuario = User::factory()->create();
    $unaMascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $otraMascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $visita = Visita::factory()->for($otraMascota)->create();

    // Las dos son del mismo usuario, así que la Policy pasa: lo que corta es
    // la comprobación de que la visita pertenece a la mascota de la URL.
    $this->actingAs($usuario)
        ->get(route('mascotas.visitas.show', [$unaMascota, $visita]))
        ->assertNotFound();
});
