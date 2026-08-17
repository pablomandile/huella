<?php

use App\Enums\TipoVisita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            // Si se da de baja la veterinaria, la visita sobrevive sin ella:
            // es historia clínica y no se puede perder por limpiar un catálogo.
            $table->foreignId('veterinaria_id')->nullable()
                ->constrained('veterinarias')->nullOnDelete();
            $table->foreignId('veterinario_id')->nullable()
                ->constrained('veterinarios')->nullOnDelete();

            // En UTC, como todo. Se convierte a la zona del usuario al mostrar.
            $table->dateTime('fecha_hora');

            $table->enum('tipo', TipoVisita::valores())->default(TipoVisita::Rutina->value);
            $table->string('motivo')->nullable(); // "gastroenteritis"
            $table->text('diagnostico')->nullable();
            $table->text('indicaciones')->nullable();
            $table->decimal('temperatura', 4, 1)->nullable();
            $table->decimal('costo', 12, 2)->nullable();
            $table->char('moneda', 3)->default('ARS');
            $table->date('proximo_control')->nullable(); // dispara recordatorio en la fase 5
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas');
    }
};
