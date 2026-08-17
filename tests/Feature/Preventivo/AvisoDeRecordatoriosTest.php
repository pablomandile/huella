<?php

use App\Enums\EstadoRecordatorio;
use App\Mail\RecordatoriosDelDia;
use App\Models\AplicacionVacuna;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * El criterio de la fase: al cargar una vacuna con refuerzo a los 12 meses
 * aparece el recordatorio y **llega el mail en la fecha correcta**.
 *
 * "La fecha correcta" es la del reloj del usuario. Con el servidor en UTC y el
 * usuario en Buenos Aires hay tres horas de diferencia, y un mail a las 9 de la
 * mañana del servidor le llegaría a las 6.
 */

it('avisa cuando la ventana de anticipación ya se abrió', function () {
    Mail::fake();

    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);

    // Refuerzo en 10 días: con 15 de anticipación, ya hay que avisar.
    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'vacuna_libre' => 'Quíntuple',
        'proxima_dosis' => now()->addDays(10)->toDateString(),
    ]);

    // Mediodía en Buenos Aires: pasó la hora de notificación (09:00).
    Carbon::setTestNow(now()->setTime(15, 0));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    Mail::assertSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($usuario->email));

    // Avisado, no completado: que llegue el mail no significa que se haya dado
    // la vacuna.
    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Notificado);

    Carbon::setTestNow();
});

it('no avisa antes de que llegue la hora en el reloj del usuario', function () {
    Mail::fake();

    $usuario = User::factory()->create(['zona_horaria' => 'America/Argentina/Buenos_Aires']);
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Recordatorio::factory()->for($mascota)->porAvisar()->create([
        'hora_notificacion' => '09:00',
    ]);

    // 11:00 UTC son las 8 de la mañana en Buenos Aires: todavía no.
    Carbon::setTestNow(Carbon::parse('2026-08-17 11:00:00'));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Pendiente);

    // Una hora después sí: son las 9 allá.
    Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    Mail::assertSent(RecordatoriosDelDia::class);
    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Notificado);

    Carbon::setTestNow();
});

it('todavía no avisa si falta para la ventana', function () {
    Mail::fake();

    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    // La vacuna es en seis meses: no hay nada que avisar hoy.
    AplicacionVacuna::factory()->create([
        'mascota_id' => $mascota->id,
        'proxima_dosis' => now()->addMonths(6)->toDateString(),
    ]);

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Recordatorio::sole()->estado)->toBe(EstadoRecordatorio::Pendiente);
});

it('manda un solo mail con todo lo del usuario', function () {
    Mail::fake();

    $usuario = User::factory()->create();
    $greta = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);
    $simon = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Simón']);

    Recordatorio::factory()->for($greta)->porAvisar()->create(['titulo' => 'Antirrábica de Greta']);
    Recordatorio::factory()->for($simon)->porAvisar()->create(['titulo' => 'Desparasitar a Simón']);

    Carbon::setTestNow(now()->setTime(15, 0));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    // Uno, no dos: nadie quiere cinco mails un lunes a la mañana.
    Mail::assertSentCount(1);
    Mail::assertSent(
        RecordatoriosDelDia::class,
        fn (RecordatoriosDelDia $mail) => $mail->recordatorios->count() === 2,
    );

    Carbon::setTestNow();
});

it('no vuelve a avisar lo que ya avisó', function () {
    Mail::fake();

    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();
    Recordatorio::factory()->for($mascota)->porAvisar()->create();

    Carbon::setTestNow(now()->setTime(15, 0));

    $this->artisan('huella:procesar-recordatorios');
    $this->artisan('huella:procesar-recordatorios');
    $this->artisan('huella:procesar-recordatorios');

    Mail::assertSentCount(1);

    Carbon::setTestNow();
});

it('no avisa lo que el usuario ya resolvió', function () {
    Mail::fake();

    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    Recordatorio::factory()->for($mascota)->porAvisar()->completado()->create();
    Recordatorio::factory()->for($mascota)->porAvisar()->create([
        'estado' => EstadoRecordatorio::Descartado,
    ]);

    Carbon::setTestNow(now()->setTime(15, 0));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    Mail::assertNothingSent();

    Carbon::setTestNow();
});

it('no le manda a un usuario los recordatorios de otro', function () {
    Mail::fake();

    $unUsuario = User::factory()->create();
    $otroUsuario = User::factory()->create();

    $mascota = Mascota::factory()->for($otroUsuario, 'propietario')->create();
    Recordatorio::factory()->for($mascota)->porAvisar()->create();

    Carbon::setTestNow(now()->setTime(15, 0));

    $this->artisan('huella:procesar-recordatorios')->assertSuccessful();

    Mail::assertSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($otroUsuario->email));
    Mail::assertNotSent(RecordatoriosDelDia::class, fn ($mail) => $mail->hasTo($unUsuario->email));

    Carbon::setTestNow();
});

it('el mail se arma en español y con el detalle de cada recordatorio', function () {
    $usuario = User::factory()->create(['name' => 'Pablo']);
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create(['nombre' => 'Greta']);

    $recordatorio = Recordatorio::factory()->for($mascota)->create([
        'titulo' => 'Refuerzo de Quíntuple para Greta',
        'descripcion' => 'La última dosis fue el 17/08/2026.',
        'fecha_objetivo' => now()->addDay()->toDateString(),
    ]);

    $mail = new RecordatoriosDelDia($usuario, collect([$recordatorio->load('mascota')]));
    $renderizado = $mail->render();

    expect($renderizado)->toContain('Hola, Pablo')
        ->toContain('Refuerzo de Quíntuple para Greta')
        ->toContain('es mañana')
        // Regla de negocio 7: el sistema registra, no aconseja.
        ->toContain('no da consejos clínicos');

    // Con uno solo, el asunto es el propio recordatorio.
    expect($mail->envelope()->subject)->toBe('Refuerzo de Quíntuple para Greta');
});

it('el asunto resume cuando hay varios', function () {
    $usuario = User::factory()->create();
    $mascota = Mascota::factory()->for($usuario, 'propietario')->create();

    $mail = new RecordatoriosDelDia(
        $usuario,
        Recordatorio::factory()->for($mascota)->count(3)->create(),
    );

    expect($mail->envelope()->subject)->toBe('Tenés 3 cosas para agendar');
});
