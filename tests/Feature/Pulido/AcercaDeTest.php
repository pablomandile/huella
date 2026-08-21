<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * "Acerca de" es la única pantalla que se abre igual con sesión y sin ella: el
 * enlace del pie de la portada lo toca gente que todavía no tiene cuenta.
 *
 * Sin usuario no puede ir en AppLayout —NavUser recibiría un `auth.user` nulo y
 * la página rompería antes de mostrarse—, así que `app.ts` la deja sin layout en
 * ese caso. Eso no se ve desde PHP; lo que sí se puede cuidar acá es que la ruta
 * siga siendo pública, que es la mitad de la que depende.
 */

it('se abre sin cuenta', function () {
    $this->get(route('acerca'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('AcercaDe')
            ->where('auth.user', null)
        );
});

it('se abre con sesión', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('acerca'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('AcercaDe'));
});
