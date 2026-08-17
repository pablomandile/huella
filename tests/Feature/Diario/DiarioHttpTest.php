<?php

use App\Models\Alergia;
use App\Models\AplicacionVacuna;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\RegistroPeso;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Visita;

it('muestra el diario con la primera página y los contadores', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Visita::factory()->for($mascota)->count(2)->create();
    EntradaDiario::factory()->count(3)->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.diario.index', $mascota))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('diario/Index')
            ->has('eventos', 5)
            ->where('totales.visita', 2)
            ->where('totales.entrada', 3)
            ->where('hay_mas', false)
            ->has('tipos', 8),
        );
});

it('la página siguiente viene por JSON para no perder el scroll', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    EntradaDiario::factory()->count(25)->create(['mascota_id' => $mascota->id]);

    $primera = $this->actingAs($usuario)
        ->get(route('mascotas.diario.index', $mascota))
        ->assertInertia(fn ($pagina) => $pagina->has('eventos', 20)->where('hay_mas', true));

    $cursor = $primera->viewData('page')['props']['cursor'];

    expect($cursor)->not->toBeNull();

    // El scroll suma a la lista que ya está en pantalla: si esto devolviera una
    // navegación Inertia, se reemplazaría la página y se perdería la posición.
    $this->actingAs($usuario)
        ->getJson(route('mascotas.diario.mas', ['mascota' => $mascota, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(5, 'eventos')
        ->assertJsonPath('hay_mas', false);
});

it('filtra por tipo desde la URL', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Visita::factory()->for($mascota)->count(2)->create();
    EntradaDiario::factory()->count(3)->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('mascotas.diario.index', ['mascota' => $mascota, 'tipos' => ['entrada']]))
        ->assertInertia(fn ($pagina) => $pagina
            ->has('eventos', 3)
            ->where('filtros.tipos', ['entrada'])
            // Los contadores siguen mostrando todo: dicen cuántos hay, no
            // cuántos se están viendo.
            ->where('totales.visita', 2),
        );
});

it('busca por texto desde la URL', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Visita::factory()->for($mascota)->create(['motivo' => 'Gastroenteritis']);
    Visita::factory()->for($mascota)->create(['motivo' => 'Control anual']);

    $this->actingAs($usuario)
        ->get(route('mascotas.diario.index', ['mascota' => $mascota, 'busqueda' => 'gastro']))
        ->assertInertia(fn ($pagina) => $pagina
            ->has('eventos', 1)
            ->where('eventos.0.titulo', 'Gastroenteritis'),
        );
});

it('rechaza un tipo inventado y un rango invertido', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->get(route('mascotas.diario.index', ['mascota' => $mascota, 'tipos' => ['inventado']]))
        ->assertSessionHasErrors('tipos.0');

    $this->actingAs($usuario)
        ->get(route('mascotas.diario.index', [
            'mascota' => $mascota,
            'desde' => '2026-08-01',
            'hasta' => '2026-07-01',
        ]))
        ->assertSessionHasErrors('hasta');
});

it('agrega una nota al diario', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->from(route('mascotas.diario.index', $mascota))
        ->post(route('mascotas.entradas.store', $mascota), [
            'fecha' => now()->toDateString(),
            'contenido' => 'Hoy vomitó dos veces, después comió normal.',
            'categoria' => 'sintoma',
            'animo' => 'bajo',
        ])
        ->assertRedirect(route('mascotas.diario.index', $mascota));

    $entrada = EntradaDiario::sole();

    expect($entrada->categoria->value)->toBe('sintoma')
        // Sin título, el encabezado sale del contenido.
        ->and($entrada->encabezado())->toStartWith('Hoy vomitó dos veces');
});

it('exige el contenido de la nota, no el título', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $this->actingAs($usuario)
        ->post(route('mascotas.entradas.store', $mascota), [
            'fecha' => now()->toDateString(),
            'titulo' => 'Solo el título',
            'categoria' => 'general',
        ])
        ->assertSessionHasErrors('contenido');

    // Con contenido y sin título, entra.
    $this->actingAs($usuario)
        ->post(route('mascotas.entradas.store', $mascota), [
            'fecha' => now()->toDateString(),
            'contenido' => 'Una línea alcanza.',
            'categoria' => 'general',
        ])
        ->assertSessionHasNoErrors();
});

it('edita y elimina una nota', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $entrada = EntradaDiario::factory()->create([
        'mascota_id' => $mascota->id,
        'contenido' => 'Original',
    ]);

    $this->actingAs($usuario)
        ->put(route('mascotas.entradas.update', [$mascota, $entrada]), [
            'fecha' => $entrada->fecha->toDateString(),
            'contenido' => 'Corregido',
            'categoria' => 'general',
        ])
        ->assertRedirect();

    expect($entrada->fresh()->contenido)->toBe('Corregido');

    $this->actingAs($usuario)
        ->delete(route('mascotas.entradas.destroy', [$mascota, $entrada]))
        ->assertRedirect();

    $this->assertSoftDeleted($entrada);
});

it('no deja ver ni escribir en el diario de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();
    $entrada = EntradaDiario::factory()->create(['mascota_id' => $mascota->id]);

    $this->actingAs($intruso)
        ->get(route('mascotas.diario.index', $mascota))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->getJson(route('mascotas.diario.mas', $mascota))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->post(route('mascotas.entradas.store', $mascota), [
            'fecha' => now()->toDateString(),
            'contenido' => 'Intrusa',
            'categoria' => 'general',
        ])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->delete(route('mascotas.entradas.destroy', [$mascota, $entrada]))
        ->assertForbidden();
});

/* ------------------------------------------------------------ exportación */

it('genera el PDF de la historia clínica con las alergias', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);

    Alergia::factory()->for($mascota)->create([
        'agente' => 'Penicilina',
        'tipo' => 'medicamentosa',
        'severidad' => 'severa',
    ]);
    Visita::factory()->for($mascota)->create(['motivo' => 'Gastroenteritis']);
    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'vacuna_libre' => 'Antirrábica',
    ]);
    RegistroPeso::factory()->create(['mascota_id' => $mascota->id]);

    $respuesta = $this->actingAs($usuario)
        ->get(route('mascotas.historia-clinica', $mascota));

    $respuesta->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('historia-clinica-greta-'.now()->toDateString().'.pdf');

    // Un PDF válido arranca con %PDF y tiene cuerpo.
    $contenido = $respuesta->getContent();

    expect($contenido)->toStartWith('%PDF')
        ->and(strlen($contenido))->toBeGreaterThan(2000);
});

it('el PDF acota por rango de fechas', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Visita::factory()->for($mascota)->create(['fecha_hora' => '2026-01-15 10:00:00']);
    Visita::factory()->for($mascota)->create(['fecha_hora' => '2026-08-15 10:00:00']);

    $this->actingAs($usuario)
        ->get(route('mascotas.historia-clinica', [
            'mascota' => $mascota,
            'desde' => '2026-06-01',
        ]))
        ->assertOk();

    // Rango invertido: no se genera nada.
    $this->actingAs($usuario)
        ->get(route('mascotas.historia-clinica', [
            'mascota' => $mascota,
            'desde' => '2026-08-01',
            'hasta' => '2026-07-01',
        ]))
        ->assertSessionHasErrors('hasta');
});

it('no genera el PDF de la mascota de otra cuenta', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();

    $this->actingAs($intruso)
        ->get(route('mascotas.historia-clinica', $mascota))
        ->assertForbidden();
});

it('exporta todos los datos del usuario en JSON', function () {
    $usuario = User::factory()->create(['name' => 'Pablo']);
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create([
        'nombre' => 'Greta',
        'especie' => 'perro',
    ]);

    Alergia::factory()->for($mascota)->create(['agente' => 'Pollo']);
    Visita::factory()->for($mascota)->create(['motivo' => 'Gastroenteritis']);
    EntradaDiario::factory()->create([
        'mascota_id' => $mascota->id,
        'contenido' => 'Nota de prueba',
    ]);

    // Y la mascota de otra cuenta, que no puede aparecer.
    Mascota::factory()->create(['nombre' => 'Ajena']);

    $respuesta = $this->actingAs($usuario)->get(route('exportacion.datos'));

    $respuesta->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="huella-'.now()->toDateString().'.json"');

    $datos = $respuesta->json();

    expect($datos['usuario']['nombre'])->toBe('Pablo')
        ->and($datos['mascotas'])->toHaveCount(1)
        ->and($datos['mascotas'][0]['nombre'])->toBe('Greta')
        ->and($datos['mascotas'][0]['alergias'][0]['agente'])->toBe('Pollo')
        ->and($datos['mascotas'][0]['visitas'][0]['motivo'])->toBe('Gastroenteritis')
        ->and($datos['mascotas'][0]['diario'][0]['contenido'])->toBe('Nota de prueba')
        // Los enums salen con su etiqueta legible, no con el valor interno.
        ->and($datos['mascotas'][0]['especie'])->toBe('Perro')
        // Y el aviso de la regla 7 viaja con los datos.
        ->and($datos['aviso'])->toContain('No es un diagnóstico');
});

it('la exportación pide sesión', function () {
    $this->get(route('exportacion.datos'))->assertRedirect(route('login'));
});

/* ------------------------------------------------------------- dashboard */

it('el dashboard muestra lo de hoy, la agenda y el estado', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    // Una toma pendiente de hoy.
    $tratamiento = Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'medicamento_libre' => 'Cefalexina',
    ]);
    TomaMedicamento::factory()->for($tratamiento)->create([
        'fecha_hora_programada' => now(),
    ]);

    // Un recordatorio dentro de los 30 días y otro fuera.
    Recordatorio::factory()->for($mascota)->create([
        'titulo' => 'Cerca',
        'fecha_objetivo' => now()->addDays(10)->toDateString(),
    ]);
    Recordatorio::factory()->for($mascota)->create([
        'titulo' => 'Lejos',
        'fecha_objetivo' => now()->addMonths(6)->toDateString(),
    ]);

    Visita::factory()->for($mascota)->create(['motivo' => 'Gastroenteritis']);
    RegistroPeso::factory()->elDia(now()->subMonth()->toDateString(), 17.0)
        ->create(['mascota_id' => $mascota->id]);
    RegistroPeso::factory()->elDia(now()->toDateString(), 18.4)
        ->create(['mascota_id' => $mascota->id]);

    $this->actingAs($usuario)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('tomasDeHoy', 1)
            ->where('tomasDeHoy.0.medicamento', 'Cefalexina')
            // Solo los próximos 30 días.
            ->has('recordatorios', 1)
            ->where('recordatorios.0.titulo', 'Cerca')
            ->where('ultimoPeso.peso_legible', '18,4 kg')
            ->where('variacionPeso.texto', '+ 1,40 kg')
            ->where('ultimaVisita.motivo', 'Gastroenteritis')
            ->where('estadoVacunacion.estado', 'sin_datos'),
        );
});

it('el dashboard no mezcla las mascotas de otra cuenta', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $ajena = Mascota::factory()->create();

    Recordatorio::factory()->for($mascota)->create(['titulo' => 'Mío']);
    Recordatorio::factory()->for($ajena)->create(['titulo' => 'Ajeno']);

    $this->actingAs($usuario)
        ->get(route('dashboard'))
        ->assertInertia(fn ($pagina) => $pagina
            ->has('recordatorios', 1)
            ->where('recordatorios.0.titulo', 'Mío'),
        );
});
