<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Galería: fotos con fecha y epígrafe, para ver la evolución en el tiempo.
     * Los archivos viven en el disco privado y se sirven por controlador.
     */
    public function up(): void
    {
        Schema::create('fotos_mascota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('ruta_miniatura')->nullable();
            $table->date('fecha');
            $table->string('epigrafe')->nullable();
            $table->timestamps();

            $table->index(['mascota_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_mascota');
    }
};
