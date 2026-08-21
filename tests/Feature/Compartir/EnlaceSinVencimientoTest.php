<?php

use App\Models\EnlaceCompartido;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * El enlace que no vence.
 *
 * La tabla nació con el vencimiento obligatorio y esto lo afloja, así que lo que
 * se cuida acá es que aflojarlo no haya abierto nada de al lado: que el que sí
 * tiene fecha siga venciendo, y que "no vence" no se cuele por un POST a mano.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->duenio = User::factory()->create();
    $this->mascota = Mascota::factory()->for($this->duenio, 'propietario')->create();
});

it('guarda el enlace sin fecha cuando el dueño elige que no venza', function () {
    $this->actingAs($this->duenio)
        ->post(route('mascotas.enlaces.store', $this->mascota), ['vigencia' => 'siempre'])
        ->assertRedirect();

    expect($this->mascota->enlaces()->sole()->expira_en)->toBeNull();
});

it('lo abre sin cuenta y no lo da por vencido', function () {
    $enlace = EnlaceCompartido::factory()->sinVencimiento()->create([
        'mascota_id' => $this->mascota->id,
        'creado_por' => $this->duenio->id,
    ]);

    expect($enlace->vencido)->toBeFalse();

    $this->get(route('compartido.ficha', $enlace->token))->assertOk();
});

it('sigue venciendo el que tiene fecha', function () {
    // Que exista "no vence" no puede haber roto el 410 del que sí vencía: es el
    // mismo `abort_if` sobre el mismo atributo, ahora con un null en el medio.
    $vencido = EnlaceCompartido::factory()->create([
        'mascota_id' => $this->mascota->id,
        'creado_por' => $this->duenio->id,
        'expira_en' => now()->subDay(),
    ]);

    $this->get(route('compartido.ficha', $vencido->token))->assertStatus(410);
});

it('lista el que no vence junto al vigente, y no el vencido', function () {
    $sinFecha = EnlaceCompartido::factory()->sinVencimiento()->create([
        'mascota_id' => $this->mascota->id,
        'creado_por' => $this->duenio->id,
    ]);
    $vigente = EnlaceCompartido::factory()->create([
        'mascota_id' => $this->mascota->id,
        'creado_por' => $this->duenio->id,
    ]);
    EnlaceCompartido::factory()->vencido()->create([
        'mascota_id' => $this->mascota->id,
        'creado_por' => $this->duenio->id,
    ]);

    $ids = $this->mascota->enlaces()->vigentes()->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$sinFecha->id, $vigente->id]);
});

it('no se lleva puestos los enlaces de otra mascota', function () {
    /*
     * El `orWhereNull` sin agrupar se suma al final de la consulta entera y anula
     * el `mascota_id` de la relación: el listado de una mascota mostraría los
     * enlaces sin vencimiento de todas. No da error, solo muestra de más.
     */
    $otra = Mascota::factory()->for(User::factory(), 'propietario')->create();
    EnlaceCompartido::factory()->sinVencimiento()->create([
        'mascota_id' => $otra->id,
        'creado_por' => $otra->usuario_id,
    ]);

    expect($this->mascota->enlaces()->vigentes()->count())->toBe(0);
});

it('no acepta un enlace sin fecha por un POST a mano', function () {
    // La vigencia sigue saliendo del enum y la fecha la sigue calculando el
    // servidor: mandar `expira_en` en null no puede fabricar uno eterno.
    $this->actingAs($this->duenio)
        ->post(route('mascotas.enlaces.store', $this->mascota), [
            'vigencia' => '7',
            'expira_en' => null,
        ])
        ->assertRedirect();

    expect($this->mascota->enlaces()->sole()->expira_en)->not->toBeNull();
});
