<?php

use App\Enums\IntensidadCelo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciclos_celo', function (Blueprint $table) {
            $table->id();

            // Solo para hembras no castradas: lo controla `celo_visible` en el
            // modelo Mascota, y al marcar la castración el módulo desaparece y
            // sus recordatorios se descartan (regla de negocio 2).
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            // Se calcula al cerrar el ciclo; se guarda para no recalcularlo en
            // cada consulta del historial.
            $table->unsignedSmallInteger('duracion_dias')->nullable();

            $table->enum('intensidad', IntensidadCelo::valores())->nullable();
            $table->text('sintomas')->nullable();
            $table->boolean('hubo_monta')->default(false);

            // Promedio de los intervalos previos, o el fallback de 180 días con
            // confianza baja. Dispara el recordatorio.
            $table->date('proxima_estimada')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha_inicio']);
            $table->index('proxima_estimada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciclos_celo');
    }
};
