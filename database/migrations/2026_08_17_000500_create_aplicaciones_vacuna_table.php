<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_vacuna', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            // Del catálogo, o escrita a mano si el plan de la veterinaria usa
            // un nombre que no está cargado.
            $table->foreignId('vacuna_id')->nullable()
                ->constrained('vacunas')->nullOnDelete();
            $table->string('vacuna_libre', 120)->nullable();

            $table->foreignId('visita_id')->nullable()
                ->constrained('visitas')->nullOnDelete();
            $table->foreignId('veterinaria_id')->nullable()
                ->constrained('veterinarias')->nullOnDelete();
            $table->foreignId('veterinario_id')->nullable()
                ->constrained('veterinarios')->nullOnDelete();

            $table->date('fecha');
            $table->unsignedTinyInteger('dosis_nro')->nullable(); // 1ª, 2ª, refuerzo
            $table->string('marca', 120)->nullable();
            $table->string('lote', 60)->nullable();
            $table->date('vencimiento_lote')->nullable();

            // Precargada con los meses de refuerzo del catálogo, siempre
            // editable (regla de negocio 6). Dispara el recordatorio.
            $table->date('proxima_dosis')->nullable();

            $table->text('reacciones')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha']);
            $table->index('proxima_dosis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_vacuna');
    }
};
