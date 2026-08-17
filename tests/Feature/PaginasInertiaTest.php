<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Las páginas viven en `resources/js/pages` (minúscula). Inertia verifica que el
 * archivo exista en disco cuando se usa assertInertia, así que este test falla si
 * alguien renombra la carpeta a `Pages` o si `pages.paths` deja de coincidir con
 * el case real. En Windows el error no se nota; en Linux rompe el build.
 */
it('resuelve los componentes de página con el case real de la carpeta', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});

it('tiene la verificación de existencia de páginas activada en testing', function () {
    expect(config('inertia.testing.ensure_pages_exist'))->toBeTrue();
    expect(config('inertia.pages.paths'))->toContain(resource_path('js/pages'));
});
