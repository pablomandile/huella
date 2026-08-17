<?php

use App\Models\AplicacionVacuna;
use App\Models\CicloCelo;
use App\Models\Desparasitacion;
use App\Models\Dieta;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Visita;
use App\Services\TimelineService;

/*
 * El criterio de la fase: el timeline de una mascota con 200 eventos carga en
 * menos de un segundo y pagina sin saltos.
 *
 * "Sin saltos" es lo que hace difícil el paginado por cursor sobre ocho fuentes
 * distintas: si el orden no es determinístico, la página siguiente repite o se
 * saltea eventos. Eso es lo que más se prueba acá.
 */

function timeline(): TimelineService
{
    return app(TimelineService::class);
}

function mascotaDeTimeline(): Mascota
{
    return Mascota::factory()
        ->for(User::factory()->create(), 'propietario')
        ->hembra()
        ->entera()
        ->create(['nombre' => 'Greta']);
}

it('mezcla las ocho fuentes en una sola lista ordenada', function () {
    $mascota = mascotaDeTimeline();

    Visita::factory()->for($mascota)->create([
        'fecha_hora' => '2026-08-10 10:00:00',
        'motivo' => 'Gastroenteritis',
    ]);
    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'vacuna_libre' => 'Antirrábica',
        'fecha' => '2026-08-12',
    ]);
    Desparasitacion::factory()->create([
        'mascota_id' => $mascota->id,
        'fecha' => '2026-08-08',
    ]);
    Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'visita_id' => null,
        'fecha_inicio' => '2026-08-06',
    ]);
    RegistroPeso::factory()->elDia('2026-08-14', 18.4)->create(['mascota_id' => $mascota->id]);
    Dieta::factory()->create(['mascota_id' => $mascota->id, 'fecha_inicio' => '2026-08-04']);
    CicloCelo::factory()->empezoEl('2026-08-02')->create(['mascota_id' => $mascota->id]);
    EntradaDiario::factory()->elDia('2026-08-16')->create([
        'mascota_id' => $mascota->id,
        'titulo' => 'Vomitó dos veces',
    ]);

    $resultado = timeline()->para($mascota);
    $fechas = array_column($resultado['eventos'], 'fecha');
    $tipos = array_column($resultado['eventos'], 'tipo');

    expect($resultado['eventos'])->toHaveCount(8)
        // De lo más nuevo a lo más viejo.
        ->and($fechas)->toBe([
            '2026-08-16', '2026-08-14', '2026-08-12', '2026-08-10',
            '2026-08-08', '2026-08-06', '2026-08-04', '2026-08-02',
        ])
        ->and($tipos)->toBe([
            'entrada', 'peso', 'vacuna', 'visita',
            'desparasitacion', 'tratamiento', 'dieta', 'celo',
        ])
        ->and($resultado['hay_mas'])->toBeFalse()
        ->and($resultado['cursor'])->toBeNull();
});

it('no duplica los tratamientos que ya se ven en su visita', function () {
    $mascota = mascotaDeTimeline();
    $visita = Visita::factory()->for($mascota)->create(['fecha_hora' => '2026-08-10 10:00:00']);

    // Uno de la visita y uno suelto: solo el suelto es un evento propio.
    Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'visita_id' => $visita->id,
    ]);
    Tratamiento::factory()->create([
        'mascota_id' => $mascota->id,
        'visita_id' => null,
        'medicamento_libre' => 'Antiparasitario de rutina',
    ]);

    $resultado = timeline()->para($mascota);
    $tipos = array_column($resultado['eventos'], 'tipo');

    expect(array_count_values($tipos)['tratamiento'] ?? 0)->toBe(1)
        ->and(array_count_values($tipos)['visita'] ?? 0)->toBe(1);
});

it('pagina 200 eventos sin repetir ni saltear ninguno', function () {
    $mascota = mascotaDeTimeline();

    // 200 eventos repartidos entre las fuentes, con fechas que se repiten para
    // forzar los empates que rompen un paginado mal hecho.
    for ($i = 0; $i < 50; $i++) {
        $fecha = now()->subDays($i % 25)->toDateString();

        EntradaDiario::factory()->elDia($fecha)->create(['mascota_id' => $mascota->id]);
        RegistroPeso::factory()->create([
            'mascota_id' => $mascota->id,
            'fecha' => now()->subDays($i)->toDateString(),
        ]);
        Visita::factory()->for($mascota)->create([
            'fecha_hora' => now()->subDays($i % 25)->setTime(10, 0),
        ]);
        Desparasitacion::factory()->create([
            'mascota_id' => $mascota->id,
            'fecha' => $fecha,
        ]);
    }

    $vistos = [];
    $cursor = null;
    $paginas = 0;

    do {
        $resultado = timeline()->para($mascota, [], $cursor);

        foreach ($resultado['eventos'] as $evento) {
            $vistos[] = $evento['clave'];
        }

        $cursor = $resultado['cursor'];
        $paginas++;
    } while ($cursor !== null && $paginas < 30);

    expect(count($vistos))->toBe(200)
        // Ninguno repetido: es lo que falla cuando el orden no es determinístico.
        ->and(count(array_unique($vistos)))->toBe(200)
        ->and($paginas)->toBe(10);
});

it('carga la primera página de 200 eventos en menos de un segundo', function () {
    $mascota = mascotaDeTimeline();

    for ($i = 0; $i < 50; $i++) {
        EntradaDiario::factory()->create(['mascota_id' => $mascota->id]);
        RegistroPeso::factory()->create([
            'mascota_id' => $mascota->id,
            'fecha' => now()->subDays($i)->toDateString(),
        ]);
        Visita::factory()->for($mascota)->create();
        Desparasitacion::factory()->create([
            'mascota_id' => $mascota->id,
            'fecha' => now()->subDays($i)->toDateString(),
        ]);
    }

    $arranque = microtime(true);
    $resultado = timeline()->para($mascota);
    $tardo = microtime(true) - $arranque;

    expect($resultado['eventos'])->toHaveCount(20)
        ->and($tardo)->toBeLessThan(1.0);
});

it('filtra por tipo de evento', function () {
    $mascota = mascotaDeTimeline();

    Visita::factory()->for($mascota)->count(3)->create();
    RegistroPeso::factory()->count(3)->sequence(
        ['fecha' => '2026-08-01'],
        ['fecha' => '2026-08-02'],
        ['fecha' => '2026-08-03'],
    )->create(['mascota_id' => $mascota->id]);
    EntradaDiario::factory()->count(2)->create(['mascota_id' => $mascota->id]);

    $soloPesos = timeline()->para($mascota, ['tipos' => ['peso']]);

    expect($soloPesos['eventos'])->toHaveCount(3)
        ->and(array_unique(array_column($soloPesos['eventos'], 'tipo')))->toBe(['peso']);

    $dosTipos = timeline()->para($mascota, ['tipos' => ['peso', 'entrada']]);

    expect($dosTipos['eventos'])->toHaveCount(5);
});

it('filtra por rango de fechas', function () {
    $mascota = mascotaDeTimeline();

    EntradaDiario::factory()->elDia('2026-01-15')->create(['mascota_id' => $mascota->id]);
    EntradaDiario::factory()->elDia('2026-06-15')->create(['mascota_id' => $mascota->id]);
    EntradaDiario::factory()->elDia('2026-08-15')->create(['mascota_id' => $mascota->id]);

    $resultado = timeline()->para($mascota, [
        'desde' => '2026-05-01',
        'hasta' => '2026-07-31',
    ]);

    expect($resultado['eventos'])->toHaveCount(1)
        ->and($resultado['eventos'][0]['fecha'])->toBe('2026-06-15');
});

it('busca en motivos, diagnósticos y notas', function () {
    $mascota = mascotaDeTimeline();

    Visita::factory()->for($mascota)->create([
        'motivo' => 'Gastroenteritis',
        'diagnostico' => 'Cuadro agudo',
    ]);
    Visita::factory()->for($mascota)->create([
        'motivo' => 'Control anual',
        'diagnostico' => 'Todo bien',
    ]);
    EntradaDiario::factory()->create([
        'mascota_id' => $mascota->id,
        'titulo' => 'Otra vez con la panza',
        'contenido' => 'Parece una gastroenteritis leve',
    ]);

    $resultado = timeline()->para($mascota, ['busqueda' => 'gastroenter']);

    // Encuentra la visita por el motivo y la entrada por el contenido.
    expect($resultado['eventos'])->toHaveCount(2)
        ->and(array_column($resultado['eventos'], 'tipo'))
        ->toContain('visita', 'entrada');
});

it('la búsqueda no se rompe con comodines', function () {
    $mascota = mascotaDeTimeline();

    EntradaDiario::factory()->create([
        'mascota_id' => $mascota->id,
        'titulo' => 'Nota normal',
        'contenido' => 'Sin nada raro',
    ]);

    // Un "%" suelto traería todo si no se escapara.
    $resultado = timeline()->para($mascota, ['busqueda' => '%']);

    expect($resultado['eventos'])->toHaveCount(0);
});

it('cuenta cuántos eventos hay de cada tipo', function () {
    $mascota = mascotaDeTimeline();

    Visita::factory()->for($mascota)->count(2)->create();
    EntradaDiario::factory()->count(3)->create(['mascota_id' => $mascota->id]);

    $totales = timeline()->totalesPorTipo($mascota);

    expect($totales['visita'])->toBe(2)
        ->and($totales['entrada'])->toBe(3)
        ->and($totales['celo'])->toBe(0)
        // Están todos los tipos, incluso los que no tienen eventos.
        ->and(array_keys($totales))->toBe(TimelineService::TIPOS);
});

it('ignora un cursor manoseado y arranca de cero', function () {
    $mascota = mascotaDeTimeline();
    EntradaDiario::factory()->count(3)->create(['mascota_id' => $mascota->id]);

    foreach (['basura', base64_encode('no|existe'), base64_encode('x|entrada|1')] as $cursor) {
        $resultado = timeline()->para($mascota, [], $cursor);

        expect($resultado['eventos'])->toHaveCount(3);
    }
});

it('no mezcla eventos de otra mascota', function () {
    $mascota = mascotaDeTimeline();
    $otra = mascotaDeTimeline();

    EntradaDiario::factory()->create([
        'mascota_id' => $mascota->id,
        'titulo' => 'De Greta',
    ]);
    EntradaDiario::factory()->create([
        'mascota_id' => $otra->id,
        'titulo' => 'De la otra',
    ]);

    $resultado = timeline()->para($mascota);

    expect($resultado['eventos'])->toHaveCount(1)
        ->and($resultado['eventos'][0]['titulo'])->toBe('De Greta');
});
