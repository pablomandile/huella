<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinarias', function (Blueprint $table) {
            $table->id();

            // Agenda personal, no catálogo compartido: acá no hay semilla.
            // Cada usuario carga las suyas y no ve las de nadie más.
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();

            $table->string('nombre', 140);
            $table->string('direccion')->nullable();
            $table->string('localidad', 120)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('sitio_web')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('foto')->nullable(); // reservado; sin UI todavía
            $table->string('horarios')->nullable();
            $table->boolean('urgencias_24h')->default(false);
            $table->text('notas')->nullable();
            $table->boolean('activa')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinarias');
    }
};
