<?php

use App\Enums\EstadoRecordatorio;
use App\Enums\TipoRecordatorio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            $table->enum('tipo', TipoRecordatorio::valores());
            $table->string('titulo', 160);
            $table->text('descripcion')->nullable();

            $table->date('fecha_objetivo');
            $table->unsignedSmallInteger('dias_anticipacion')->default(7);
            // Hora local del usuario: es a la que sale el mail.
            $table->time('hora_notificacion')->default('09:00:00');

            $table->boolean('recurrente')->default(false);
            $table->unsignedSmallInteger('intervalo_dias')->nullable();

            $table->enum('estado', EstadoRecordatorio::valores())
                ->default(EstadoRecordatorio::Pendiente->value);
            $table->dateTime('fecha_completado')->nullable();

            // Registro que lo originó. Junto con `tipo` es la clave de
            // idempotencia: volver a guardar la vacuna mueve su recordatorio en
            // vez de crear uno nuevo.
            $table->nullableMorphs('origen');

            $table->timestamps();

            $table->index(['mascota_id', 'estado', 'fecha_objetivo']);
            // El índice del job diario.
            $table->index(['estado', 'fecha_objetivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
