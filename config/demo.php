<?php

/*
 * Modo demo: la copia de huella.pablomandile.com.ar es la demo del portfolio.
 *
 * Con APP_DEMO=true hay un solo usuario, con dos mascotas y su historia, que se
 * resiembra todas las noches (`huella:demo-reset`). Nadie se registra, ni entra
 * con Google, ni cambia la cuenta, ni manda nada hacia afuera (invitaciones,
 * enlaces públicos).
 *
 * En la productiva queda apagado y nada de esto actúa: es el mismo código.
 */
return [
    'enabled' => (bool) env('APP_DEMO', false),
    'email' => 'demo@pablomandile.com.ar',
    'password' => 'demo1234',
];
