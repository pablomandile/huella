<?php

use App\Enums\OrigenPeso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_peso', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->foreignId('visita_id')->nullable()
                ->constrained('visitas')->nullOnDelete();

            $table->date('fecha');
            $table->decimal('peso_kg', 6, 2);
            // Escala de condición corporal 1-9, la que usan los veterinarios.
            $table->unsignedTinyInteger('condicion_corporal')->nullable();
            $table->enum('origen', OrigenPeso::valores())->default(OrigenPeso::Casa->value);
            $table->string('notas')->nullable();

            $table->timestamps();

            // Sin soft deletes: un peso mal cargado deforma la curva y lo que
            // se quiere es que desaparezca, no que quede archivado.
            $table->index(['mascota_id', 'fecha']);
            // Un solo peso por día y por origen: dos mediciones de la misma
            // balanza el mismo día son una corrección, no dos datos.
            $table->unique(['mascota_id', 'fecha', 'origen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_peso');
    }
};
