<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Los tratamientos que ya terminaron pasan a "terminado" solos, así la ficha
 * muestra únicamente lo que la mascota está tomando hoy.
 *
 * Corre a las 3 de la mañana UTC —medianoche en Buenos Aires— porque a esa hora
 * ya pasaron todas las tomas del día para el huso donde está el usuario.
 */
Schedule::command('huella:cerrar-tratamientos')
    ->dailyAt('03:00')
    ->withoutOverlapping();

/*
 * Los avisos de recordatorios corren **cada hora**, no una vez al día: la hora
 * de notificación es local de cada usuario, y un job diario a una hora fija del
 * servidor le llegaría a la mitad de la gente a la hora equivocada. Cada corrida
 * busca a quiénes ya les pasó su hora y todavía no recibieron el mail.
 */
Schedule::command('huella:procesar-recordatorios')
    ->hourly()
    ->withoutOverlapping();
