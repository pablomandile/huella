<?php

use App\Enums\TipoAdjunto;
use App\Models\Adjunto;
use App\Models\Alergia;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use App\Services\FichaMascotaService;
use Inertia\Testing\AssertableInertia;

/*
 * El inicio muestra la ficha completa de la mascota activa: alergias,
 * documentación, galería, todo lo que muestra su pantalla. Las dos la reciben de
 * `FichaMascotaService` y la dibujan con el mismo componente.
 *
 * Lo que se cuida acá es que sigan siendo LA MISMA. Si alguien vuelve a armar
 * los datos por separado en alguno de los dos controladores, un dato nuevo
 * aparecería en una pantalla y en la otra no, sin ningún error. Y las
 * escrituras de la ficha —subir una foto, una hoja de la libreta— vuelven con
 * `back()`: desde el inicio solo andan si el inicio recibe todo lo que la ficha
 * necesita para volver a dibujarse.
 */

function propsDe(string $url, User $usuario): array
{
    $props = null;

    test()->actingAs($usuario)
        ->get($url)
        ->assertOk()
        ->assertInertia(function (AssertableInertia $pagina) use (&$props) {
            $props = $pagina->toArray()['props'];
        });

    return $props;
}

it('el inicio recibe la misma ficha que la pantalla de la mascota', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Alergia::factory()->for($mascota)->create(['agente' => 'Pollo']);
    FotoMascota::factory()->for($mascota)->create(['epigrafe' => 'En la playa']);
    Adjunto::factory()->create([
        'adjuntable_type' => $mascota->getMorphClass(),
        'adjuntable_id' => $mascota->id,
        'tipo' => TipoAdjunto::LibretaSanitaria,
    ]);

    $ficha = propsDe(route('mascotas.show', $mascota), $usuario);
    $inicio = propsDe(route('dashboard'), $usuario);

    // Clave por clave, para que un faltante diga cuál falta.
    foreach (array_keys(app(FichaMascotaService::class)->datos($mascota, $usuario)) as $clave) {
        expect($inicio['ficha'])->toHaveKey($clave)
            ->and($inicio['ficha'][$clave])->toEqual($ficha[$clave], "difiere «{$clave}»");
    }

    // Y lo que se pidió puntualmente: la documentación y la galería.
    expect($inicio['ficha']['alergias'][0]['agente'])->toBe('Pollo')
        ->and($inicio['ficha']['documentos']['libreta_sanitaria'])->toHaveCount(1)
        ->and($inicio['ficha']['fotos'][0]['epigrafe'])->toBe('En la playa');
});

it('sin mascotas el inicio no trae ficha', function () {
    expect(propsDe(route('dashboard'), User::factory()->create())['ficha'])->toBeNull();
});

it('la ficha del inicio es la de la mascota activa', function () {
    $usuario = User::factory()->create();
    Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Arturo']);
    $greta = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);

    $this->withSession(['mascota_activa_id' => $greta->id]);

    expect(propsDe(route('dashboard'), $usuario)['ficha']['mascota']['nombre'])->toBe('Greta');
});
