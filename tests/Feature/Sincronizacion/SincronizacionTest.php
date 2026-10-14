<?php

use App\Enums\EstadoToma;
use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use App\Models\User;
use App\Services\SincronizacionService;
use Illuminate\Support\Str;

/*
 * La cola sin conexión: lo que el teléfono guardó sin red y manda al volver.
 *
 * Lo que se cuida acá es que sincronizar no sea una puerta trasera. Cada
 * operación pasa por el FormRequest de su ruta de siempre, así que tiene que
 * rechazar exactamente lo mismo que rechaza el formulario: un lector, una ficha
 * fallecida, una mascota ajena, un peso duplicado. Si alguien un día "optimiza"
 * el servicio para crear directo, estos tests son los que se rompen.
 */

function op(string $entidad, int $id, array $datos, ?string $opId = null): array
{
    return [
        'op_id' => $opId ?? (string) Str::uuid(),
        'entidad' => $entidad,
        'accion' => $entidad === 'toma' ? 'marcar' : 'crear',
        'id' => $id,
        'datos' => $datos,
    ];
}

function sincronizar(User $usuario, array $ops)
{
    return test()->actingAs($usuario)->postJson(route('sincronizar'), ['ops' => $ops]);
}

function pesoDeHoy(array $extra = []): array
{
    return ['fecha' => now()->toDateString(), 'peso_kg' => 18.4, 'origen' => 'casa', ...$extra];
}

it('aplica las cuatro escrituras de todos los días', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $toma = TomaMedicamento::factory()
        ->for(Tratamiento::factory()->create(['mascota_id' => $mascota->id]))
        ->create();

    sincronizar($usuario, [
        op('toma', $toma->id, ['estado' => 'administrada', 'fecha_hora_real' => '2026-10-14T13:05:00.000Z']),
        op('peso', $mascota->id, pesoDeHoy()),
        op('bano', $mascota->id, ['fecha' => now()->toDateString(), 'lugar' => 'casa']),
        op('entrada', $mascota->id, ['fecha' => now()->toDateString(), 'contenido' => 'Comió todo', 'categoria' => 'general']),
    ])
        ->assertOk()
        ->assertJsonPath('resultados.*.estado', ['ok', 'ok', 'ok', 'ok']);

    expect($mascota->pesos()->count())->toBe(1)
        ->and($mascota->banos()->count())->toBe(1)
        ->and($mascota->entradasDiario()->count())->toBe(1);

    $toma->refresh();
    expect($toma->estado)->toBe(EstadoToma::Administrada)
        // La hora del toque, no la de sincronizar: si la cola llega a la noche,
        // la toma igual tiene que figurar a la hora en que se dio.
        ->and($toma->fecha_hora_real->utc()->format('Y-m-d H:i'))->toBe('2026-10-14 13:05');
});

it('el servicio limpia los vacíos aunque no pase por los middleware', function () {
    /*
     * Por HTTP, ConvertEmptyStringsToNull ya limpia el JSON anidado. Pero el
     * FormRequest de la sincronización se arma a mano, sin middleware: llamado
     * desde otro lado, la condición corporal que nadie completa llegaría como
     * '' y en MySQL es "Incorrect integer value". Por eso se llama al servicio
     * directo —por HTTP este test pasaría sin probar nada— y se mira el valor
     * crudo guardado, porque SQLite acepta el '' callado.
     */
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $resultado = app(SincronizacionService::class)->aplicar(
        $usuario,
        op('peso', $mascota->id, pesoDeHoy(['condicion_corporal' => '', 'notas' => '   '])),
    );

    expect($resultado['estado'])->toBe('ok');

    $peso = $mascota->pesos()->sole();
    expect($peso->getRawOriginal('condicion_corporal'))->toBeNull()
        ->and($peso->getRawOriginal('notas'))->toBeNull();
});

it('reenviar lo ya aplicado no lo duplica', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    $mismo = op('peso', $mascota->id, pesoDeHoy());

    sincronizar($usuario, [$mismo])->assertJsonPath('resultados.0.estado', 'ok');

    // La respuesta se perdió en el camino y el teléfono lo manda de nuevo.
    sincronizar($usuario, [$mismo])
        ->assertJsonPath('resultados.0.estado', 'ok')
        ->assertJsonPath('resultados.0.repetida', true);

    expect($mascota->pesos()->count())->toBe(1);
});

it('valida con las mismas reglas que el formulario, y un error no frena al resto', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    RegistroPeso::factory()->elDia(now()->toDateString(), 17.0)
        ->create(['mascota_id' => $mascota->id, 'origen' => 'casa']);

    sincronizar($usuario, [
        // Mismo día y misma balanza: la regla de `GuardarPesoRequest`.
        op('peso', $mascota->id, pesoDeHoy()),
        op('bano', $mascota->id, ['fecha' => now()->addDay()->toDateString(), 'lugar' => 'casa']),
        op('bano', $mascota->id, ['fecha' => now()->toDateString(), 'lugar' => 'casa']),
    ])
        ->assertOk()
        ->assertJsonPath('resultados.0.estado', 'error')
        ->assertJsonPath('resultados.0.errores.fecha.0', fn ($m) => str_contains($m, 'misma balanza'))
        ->assertJsonPath('resultados.1.estado', 'error')
        ->assertJsonPath('resultados.1.errores.fecha.0', 'No se puede cargar un baño a futuro.')
        ->assertJsonPath('resultados.2.estado', 'ok');

    expect($mascota->pesos()->count())->toBe(1)
        ->and($mascota->banos()->count())->toBe(1);
});

it('un lector no puede registrar nada por la cola', function () {
    $dueno = User::factory()->create();
    $lector = User::factory()->create();
    $mascota = Mascota::factory()->for($dueno, 'propietario')->create();
    $mascota->cuidadores()->attach($lector->id, ['rol' => RolCuidador::Lector->value]);
    $toma = TomaMedicamento::factory()
        ->for(Tratamiento::factory()->create(['mascota_id' => $mascota->id]))
        ->create();

    sincronizar($lector, [
        op('peso', $mascota->id, pesoDeHoy()),
        op('toma', $toma->id, ['estado' => 'administrada']),
    ])->assertJsonPath('resultados.*.estado', ['error', 'error']);

    expect($mascota->pesos()->count())->toBe(0)
        ->and($toma->refresh()->estado)->not->toBe(EstadoToma::Administrada);
});

it('una mascota ajena es igual de inalcanzable por la cola', function () {
    $intruso = User::factory()->create();
    $ajena = Mascota::factory()->create();

    sincronizar($intruso, [op('peso', $ajena->id, pesoDeHoy())])
        ->assertJsonPath('resultados.0.estado', 'error');

    expect($ajena->pesos()->count())->toBe(0);
});

it('una ficha fallecida está en modo lectura también para la cola', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->fallecida()->create();

    sincronizar($usuario, [op('peso', $mascota->id, pesoDeHoy())])
        ->assertJsonPath('resultados.0.estado', 'error')
        ->assertJsonPath('resultados.0.errores.op.0', fn ($m) => str_contains($m, 'modo lectura'));
});

it('lo que se borró desde otro dispositivo vuelve como error, no como 500', function () {
    $usuario = User::factory()->create();

    sincronizar($usuario, [op('peso', 999999, pesoDeHoy())])
        ->assertOk()
        ->assertJsonPath('resultados.0.errores.op.0', 'Ya no existe: se borró desde otro dispositivo.');
});

it('rechaza operaciones que no son de las cuatro de todos los días', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    sincronizar($usuario, [
        // Una visita pide conexión: sus efectos los calcula el servidor.
        ['op_id' => (string) Str::uuid(), 'entidad' => 'visita', 'accion' => 'crear', 'id' => $mascota->id, 'datos' => []],
        // Acción que no corresponde a la entidad.
        ['op_id' => (string) Str::uuid(), 'entidad' => 'peso', 'accion' => 'borrar', 'id' => $mascota->id, 'datos' => []],
        // Sin op_id no hay forma de hacerla idempotente.
        ['entidad' => 'peso', 'accion' => 'crear', 'id' => $mascota->id, 'datos' => pesoDeHoy()],
    ])->assertJsonPath('resultados.*.estado', ['error', 'error', 'error']);

    expect($mascota->pesos()->count())->toBe(0);
});

it('exige sesión y un lote con forma de lote', function () {
    $this->postJson(route('sincronizar'), ['ops' => []])->assertUnauthorized();

    $usuario = User::factory()->create();
    $this->actingAs($usuario)->postJson(route('sincronizar'), ['ops' => 'nada'])->assertUnprocessable();
    $this->actingAs($usuario)
        ->postJson(route('sincronizar'), ['ops' => array_fill(0, 101, [])])
        ->assertUnprocessable();
});
