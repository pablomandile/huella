<?php

use App\Enums\EstadoToma;
use App\Models\Mascota;
use App\Models\Medicamento;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Veterinaria;
use App\Models\Visita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * El criterio de la fase: una visita por gastroenteritis con dos medicamentos y
 * una receta adjunta, todo en un solo envío.
 */

it('carga una visita con dos medicamentos y una receta en un solo envío', function () {
    Storage::fake('local');

    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    $veterinaria = Veterinaria::factory()->create(['usuario_id' => $usuario->id]);
    $cefalexina = Medicamento::factory()->semilla()->create(['nombre_comercial' => 'Cefalexina']);

    $respuesta = $this->actingAs($usuario)->post(
        route('mascotas.visitas.store', $mascota),
        [
            'fecha_hora' => '2026-08-17T15:30',
            'tipo' => 'urgencia',
            'motivo' => 'Gastroenteritis',
            'veterinaria_id' => $veterinaria->id,
            'diagnostico' => 'Cuadro gastroentérico agudo',
            'indicaciones' => 'Dieta blanda 3 días',
            'temperatura' => 39.2,
            'tratamientos' => [
                [
                    'medicamento_id' => $cefalexina->id,
                    'dosis' => '1 comprimido',
                    'via' => 'oral',
                    'frecuencia_horas' => 8,
                    'duracion_dias' => 7,
                    'fecha_inicio' => '2026-08-17',
                    'hora_primera_toma' => '08:00',
                    'notas' => 'Dar con comida',
                ],
                [
                    'medicamento_libre' => 'Metronidazol',
                    'dosis' => '2,5 ml',
                    'via' => 'oral',
                    'frecuencia_horas' => 12,
                    'duracion_dias' => 5,
                    'fecha_inicio' => '2026-08-17',
                    'hora_primera_toma' => '09:00',
                ],
            ],
            'tipo_adjunto' => 'receta',
            'adjuntos' => [UploadedFile::fake()->create('receta.pdf', 120, 'application/pdf')],
        ],
    );

    $respuesta->assertSessionHasNoErrors();

    $visita = Visita::sole();
    $respuesta->assertRedirect(route('mascotas.visitas.show', [$mascota, $visita]));

    // La visita, con la hora convertida a UTC desde el reloj del usuario.
    expect($visita->motivo)->toBe('Gastroenteritis')
        ->and($visita->tipo->value)->toBe('urgencia')
        ->and($visita->fecha_hora->format('Y-m-d H:i'))->toBe('2026-08-17 18:30')
        ->and($usuario->enSuZona($visita->fecha_hora)->format('H:i'))->toBe('15:30');

    // Los dos medicamentos, uno del catálogo y uno escrito a mano.
    // load explícito: el proyecto corre con preventLazyLoading fuera de
    // producción, así que leer nombre_medicamento sin la relación es un error.
    $visita->load('tratamientos.medicamento');

    expect($visita->tratamientos)->toHaveCount(2)
        ->and($visita->tratamientos->pluck('nombre_medicamento')->all())
        ->toContain('Cefalexina', 'Metronidazol');

    // Y sus tomas ya programadas: 21 y 10.
    $porNombre = $visita->tratamientos->keyBy('nombre_medicamento');
    expect($porNombre['Cefalexina']->tomas()->count())->toBe(21)
        ->and($porNombre['Metronidazol']->tomas()->count())->toBe(10);

    // La receta, en el disco privado.
    expect($visita->adjuntos)->toHaveCount(1);
    $adjunto = $visita->adjuntos->first();
    expect($adjunto->tipo->value)->toBe('receta')
        ->and($adjunto->nombre_original)->toBe('receta.pdf');
    Storage::assertExists($adjunto->ruta);
});

it('acepta una visita con lo mínimo: solo cuándo fue', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    // Parado en el mostrador, un registro incompleto sirve; uno que no se pudo
    // guardar, no.
    $this->actingAs($usuario)
        ->post(route('mascotas.visitas.store', $mascota), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
        ])
        ->assertRedirect();

    expect(Visita::count())->toBe(1);
});

it('rechaza un tratamiento sin nombre de medicamento', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.visitas.store', $mascota), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
            'tratamientos' => [[
                'dosis' => '1 comprimido',
                'via' => 'oral',
                'fecha_inicio' => '2026-08-17',
            ]],
        ])
        ->assertSessionHasErrors('tratamientos.0.medicamento_libre');

    expect(Visita::count())->toBe(0)
        ->and(Tratamiento::count())->toBe(0);
});

it('no deja referenciar la veterinaria de otra cuenta', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $ajena = Veterinaria::factory()->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.visitas.store', $mascota), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
            'veterinaria_id' => $ajena->id,
        ])
        ->assertSessionHasErrors('veterinaria_id');
});

it('no guarda nada si algo de la carga falla', function () {
    Storage::fake('local');

    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    // El medicamento no existe: la validación corta antes de escribir.
    $this->actingAs($usuario)
        ->post(route('mascotas.visitas.store', $mascota), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
            'tratamientos' => [[
                'medicamento_id' => 99999,
                'dosis' => '1 comprimido',
                'via' => 'oral',
                'fecha_inicio' => '2026-08-17',
            ]],
        ])
        ->assertSessionHasErrors();

    // Media visita cargada es peor que ninguna.
    expect(Visita::count())->toBe(0)
        ->and(Tratamiento::count())->toBe(0);
});

it('muestra la ficha de la visita con sus tratamientos y adjuntos', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $visita = Visita::factory()->for($mascota)->create(['motivo' => 'Otitis']);
    Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'visita_id' => $visita->id,
        'medicamento_libre' => 'Pervinal',
    ]);

    $this->actingAs($usuario)
        ->get(route('mascotas.visitas.show', [$mascota, $visita]))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('visitas/Show')
            ->where('visita.motivo', 'Otitis')
            ->has('visita.tratamientos', 1)
            ->where('visita.tratamientos.0.nombre_medicamento', 'Pervinal'),
        );
});

it('agrega un tratamiento a una visita ya cargada y le programa las tomas', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $visita = Visita::factory()->for($mascota)->create();

    $this->actingAs($usuario)
        ->from(route('mascotas.visitas.show', [$mascota, $visita]))
        ->post(route('mascotas.tratamientos.store', $mascota), [
            'visita_id' => $visita->id,
            'medicamento_libre' => 'Meloxicam',
            'dosis' => 'medio comprimido',
            'via' => 'oral',
            'frecuencia_horas' => 24,
            'duracion_dias' => 3,
            'fecha_inicio' => now()->toDateString(),
            'hora_primera_toma' => '09:00',
        ])
        ->assertRedirect();

    $tratamiento = Tratamiento::sole();

    expect($tratamiento->visita_id)->toBe($visita->id)
        ->and($tratamiento->tomas()->count())->toBe(3)
        ->and($tratamiento->tomas()->where('estado', EstadoToma::Pendiente)->count())->toBe(3);
});

it('una mascota fallecida no recibe visitas nuevas', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create([
        'fecha_fallecimiento' => now()->subMonth()->toDateString(),
    ]);

    // Modo lectura: conserva todo su historial pero no se le carga nada más.
    $this->actingAs($usuario)
        ->get(route('mascotas.visitas.create', $mascota))
        ->assertForbidden();

    $this->actingAs($usuario)
        ->post(route('mascotas.visitas.store', $mascota), [
            'fecha_hora' => '2026-08-17T10:00',
            'tipo' => 'rutina',
        ])
        ->assertForbidden();
});

it('elimina la visita sin perder el registro', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $visita = Visita::factory()->for($mascota)->create();

    $this->actingAs($usuario)
        ->delete(route('mascotas.visitas.destroy', [$mascota, $visita]))
        ->assertRedirect(route('mascotas.visitas.index', $mascota));

    // Soft delete: es historia clínica.
    $this->assertSoftDeleted($visita);
});
