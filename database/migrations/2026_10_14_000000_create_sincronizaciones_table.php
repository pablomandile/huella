<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Registro de las operaciones que llegaron de la cola sin conexión del
 * teléfono. Existe para una sola cosa: que reenviar no duplique.
 *
 * La app manda la cola, el servidor la aplica, y la respuesta se pierde en el
 * camino —el ascensor, el subte, la señal que va y viene en la veterinaria—.
 * La app no sabe que entró y la manda de nuevo. Sin esta tabla, el peso quedaría
 * cargado dos veces; con ella, el `op_id` ya está y se contesta "ok" sin tocar
 * nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('op_id');
            $table->string('entidad', 30);
            $table->string('accion', 20);
            // Sobre qué se aplicó: la toma, o la mascota a la que se le cargó
            // el peso, el baño o la nota. Para rastrear, no para resolver nada.
            $table->unsignedBigInteger('destino_id')->nullable();
            $table->timestamps();

            // Por usuario: un op_id lo genera el teléfono, no el servidor.
            $table->unique(['usuario_id', 'op_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sincronizaciones');
    }
};
