<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dietas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            // restrictOnDelete: un alimento que alguna dieta usó no se puede
            // borrar del catálogo sin perder el historial de qué comió.
            $table->foreignId('alimento_id')->constrained('alimentos')->restrictOnDelete();

            $table->foreignId('veterinario_id')->nullable()
                ->constrained('veterinarios')->nullOnDelete();

            $table->date('fecha_inicio');
            // NULL = dieta vigente. Regla de negocio 1: solo una por mascota.
            $table->date('fecha_fin')->nullable();

            $table->unsignedSmallInteger('racion_diaria_g')->nullable();
            $table->unsignedTinyInteger('tomas_por_dia')->nullable();
            $table->string('motivo')->nullable(); // "dieta renal post gastroenteritis"
            $table->boolean('prescripta')->default(false);
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha_inicio']);
            // La regla de "una sola vigente" se valida en la aplicación, dentro
            // de una transacción: MySQL admite múltiples NULL en un índice
            // único, así que acá no se puede forzar.
            $table->index(['mascota_id', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dietas');
    }
};
