<?php

use App\Models\Mascota;
use App\Models\User;

/*
 * Las páginas de error se muestran con la interfaz de la app, no con la pantalla
 * blanca de Laravel.
 *
 * Los tests corren con APP_ENV=testing: en `local` el handler deja pasar todo a
 * propósito, porque ahí el stack trace vale más que una pantalla linda.
 */

it('muestra la página de la app en un 404', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get('/una-ruta-que-no-existe')
        ->assertNotFound()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Error')
            ->where('status', 404)
            ->where('autenticado', true),
        );
});

it('muestra la página de la app en un 403', function () {
    $intruso = User::factory()->create();
    $mascota = Mascota::factory()->create();

    $this->actingAs($intruso)
        ->get(route('mascotas.show', $mascota))
        ->assertForbidden()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Error')
            ->where('status', 403),
        );
});

it('sabe si el visitante tiene sesión, para ofrecerle la salida correcta', function () {
    // Sin sesión el 404 igual se muestra, pero el botón lleva al login.
    $this->get('/otra-ruta-inexistente')
        ->assertNotFound()
        ->assertInertia(fn ($pagina) => $pagina->where('autenticado', false));
});

it('no filtra el mensaje interno de una excepción', function () {
    $usuario = User::factory()->create();

    // Un 404 de route binding trae "No query results for model
    // [App\Models\Mascota] 99999": expone la clase y confirma qué ids existen.
    // La página no manda el mensaje de la excepción a ningún lado.
    $respuesta = $this->actingAs($usuario)->get('/mascotas/99999');

    $respuesta->assertNotFound()
        ->assertInertia(fn ($pagina) => $pagina->component('Error')->missing('mensaje'));

    expect($respuesta->getContent())
        ->not->toContain('No query results')
        ->not->toContain('App\Models\Mascota');
});

it('el 419 vuelve atrás con un aviso en vez de una pantalla de error', function () {
    $usuario = User::factory()->create();

    // Sesión vencida: se reintenta y suele resolverse solo, así que una pantalla
    // de error sería peor que volver con un cartel.
    $this->actingAs($usuario)
        ->from(route('dashboard'))
        ->post(route('mascotas.store'), [], ['X-CSRF-TOKEN' => 'invalido'])
        ->assertRedirect();
})->skip('El TestCase de Laravel desactiva el middleware de CSRF.');

it('la página de error se ve sin depender del layout de la app', function () {
    // No usa AppLayout: un 500 puede venir de un fallo al armar las props
    // compartidas, y el layout las necesita.
    $vista = file_get_contents(resource_path('js/pages/Error.vue'));

    expect($vista)->not->toContain('defineOptions')
        ->and($vista)->not->toContain('layout');
});
