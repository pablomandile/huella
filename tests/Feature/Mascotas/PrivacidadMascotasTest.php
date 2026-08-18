<?php

use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * El requisito de privacidad es explícito en la especificación: los datos de
 * una cuenta jamás pueden ser visibles para otra.
 */

/**
 * Las rutas de escritura que cuelgan de una mascota y **no** llevan ningún otro
 * parámetro.
 *
 * Son las que dependen exclusivamente de la Policy de la mascota: no hay un
 * sub-recurso cuyo route binding pueda cortar antes y tapar la falta de
 * autorización con un 404. Por eso son las que se pueden barrer de una.
 *
 * @return array<string, string> nombre de la ruta => verbo
 */
function rutasDeEscrituraDeUnaMascota(): array
{
    $verbos = ['POST', 'PUT', 'PATCH', 'DELETE'];
    $encontradas = [];

    foreach (Route::getRoutes() as $ruta) {
        $metodo = collect($ruta->methods())->first(fn ($m) => in_array($m, $verbos, true));

        if ($metodo === null || ! in_array('mascota', $ruta->parameterNames(), true)) {
            continue;
        }

        if (count($ruta->parameterNames()) === 1 && $ruta->getName()) {
            $encontradas[$ruta->getName()] = $metodo;
        }
    }

    return $encontradas;
}

it('no deja ver, editar ni borrar la mascota de otro usuario', function () {
    $duenio = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($intruso)->get(route('mascotas.show', $mascota))->assertForbidden();
    $this->actingAs($intruso)->get(route('mascotas.edit', $mascota))->assertForbidden();

    $this->actingAs($intruso)
        ->put(route('mascotas.update', $mascota), [
            'nombre' => 'Hackeada',
            'especie' => 'perro',
            'sexo' => 'macho',
        ])
        ->assertForbidden();

    $this->actingAs($intruso)->delete(route('mascotas.destroy', $mascota))->assertForbidden();

    expect($mascota->refresh()->nombre)->not->toBe('Hackeada');
});

it('no deja marcar como activa la mascota de otro usuario', function () {
    $duenio = User::factory()->create();
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    $this->actingAs($intruso)
        ->patch(route('mascota-activa.update', $mascota))
        ->assertForbidden();
});

it('la autorización sale del pivote, no de la columna usuario_id', function () {
    $duenio = User::factory()->create();
    $cuidador = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();

    // Simula el multi-cuidador de v2: una fila más en el pivote alcanza para
    // dar acceso, sin tocar ninguna Policy.
    $mascota->cuidadores()->attach($cuidador->id, ['rol' => 'lector']);

    $this->actingAs($cuidador)->get(route('mascotas.show', $mascota))->assertOk();

    // Lector: puede mirar pero no editar.
    $this->actingAs($cuidador)
        ->put(route('mascotas.update', $mascota), [
            'nombre' => 'Cambiada',
            'especie' => 'perro',
            'sexo' => 'macho',
        ])
        ->assertForbidden();
});

/*
 * Las dos que siguen son el par que sostiene todo el modo lectura.
 *
 * La primera barre las rutas de escritura de una mascota y exige 403 en todas.
 * La segunda verifica que ese barrido las cubra, para que una ruta nueva sin
 * Policy rompa la suite en vez de pasar desapercibida. Es el mismo patrón
 * autoverificable de `CaseDeLasPaginasTest`.
 */

/**
 * Las que se saltean, y por qué. La lista va a mano a propósito: agregar algo
 * acá tiene que ser una decisión, no un descuido.
 */
const EXCEPCIONES_DE_ESCRITURA = [
    // Solo escribe `mascota_activa_id` en la sesión del propio lector, y cada
    // pantalla que la consume vuelve a autorizar. Un lector legítimamente quiere
    // poder tener seleccionada la ficha que le compartieron.
    'mascota-activa.update',

    // Vive fuera de `auth`: la protege la firma de la URL, que además lleva el
    // email adentro. Su lugar es InvitacionesTest.
    'invitaciones.aceptar',
];

it('un lector no puede escribir nada de la ficha que le compartieron', function () {
    $duenio = User::factory()->create();
    $lector = User::factory()->create();
    $mascota = Mascota::factory()->for($duenio, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);

    $rutas = collect(rutasDeEscrituraDeUnaMascota())
        ->reject(fn ($metodo, $nombre) => in_array($nombre, EXCEPCIONES_DE_ESCRITURA, true));

    expect($rutas)->not->toBeEmpty();

    foreach ($rutas as $nombre => $metodo) {
        // 403 y no 404: el lector **ve** la mascota, así que lo que tiene que
        // cortar es la autorización de escritura y no el route binding.
        $this->actingAs($lector)
            ->call($metodo, route($nombre, $mascota))
            ->assertForbidden("La ruta [{$nombre}] dejó escribir a un lector.");
    }
});

it('no hay ninguna ruta de escritura de una mascota sin cubrir', function () {
    $verbos = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // Las que llevan un sub-recurso —una visita, un peso, una foto— se cubren en
    // los tests de su propio módulo, donde se puede crear el registro real: acá
    // un id inventado daría 404 por el binding y taparía la falta de Policy.
    $conSubRecurso = [];
    $sueltas = array_keys(rutasDeEscrituraDeUnaMascota());

    foreach (Route::getRoutes() as $ruta) {
        $escribe = collect($ruta->methods())->contains(fn ($m) => in_array($m, $verbos, true));

        if (! $escribe || ! in_array('mascota', $ruta->parameterNames(), true)) {
            continue;
        }

        if (count($ruta->parameterNames()) > 1) {
            $conSubRecurso[] = $ruta->getName();
        }
    }

    $sinCubrir = collect(Route::getRoutes())
        ->filter(fn ($ruta) => collect($ruta->methods())
            ->contains(fn ($m) => in_array($m, $verbos, true)))
        ->filter(fn ($ruta) => in_array('mascota', $ruta->parameterNames(), true))
        ->map(fn ($ruta) => $ruta->getName())
        ->reject(fn ($nombre) => in_array($nombre, $sueltas, true)
            || in_array($nombre, $conSubRecurso, true)
            || in_array($nombre, EXCEPCIONES_DE_ESCRITURA, true))
        ->values();

    expect($sinCubrir->all())->toBe([]);
});
