<?php

use App\Enums\EstadoTratamiento;
use App\Enums\ViaAdministracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tratamientos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            // Casi siempre nace de una visita, pero no siempre: un antiparasitario
            // de rutina se carga sin haber ido al veterinario.
            $table->foreignId('visita_id')->nullable()
                ->constrained('visitas')->nullOnDelete();

            $table->foreignId('medicamento_id')->nullable()
                ->constrained('medicamentos')->nullOnDelete();
            // Salida cuando el remedio no está en el catálogo y no se quiere
            // interrumpir la carga para darlo de alta.
            $table->string('medicamento_libre', 140)->nullable();

            $table->string('dosis', 80);       // "1 comprimido", "2,5 ml"
            $table->enum('via', ViaAdministracion::valores())
                ->default(ViaAdministracion::Oral->value);

            // Una de las dos alcanza para generar las tomas; frecuencia_horas
            // manda si están las dos.
            $table->unsignedSmallInteger('frecuencia_horas')->nullable();
            $table->unsignedTinyInteger('veces_por_dia')->nullable();

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->unsignedSmallInteger('duracion_dias')->nullable();
            // Hora local del usuario: es a la que suena el recordatorio.
            $table->time('hora_primera_toma')->nullable();

            $table->enum('estado', EstadoTratamiento::valores())
                ->default(EstadoTratamiento::Activo->value);
            $table->text('notas')->nullable(); // "dar con comida"

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tratamientos');
    }
};
