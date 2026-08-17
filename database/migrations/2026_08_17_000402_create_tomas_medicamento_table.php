<?php

use App\Enums\EstadoToma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomas_medicamento', function (Blueprint $table) {
            $table->id();

            // Se autogeneran desde el tratamiento. Si el tratamiento se borra
            // de verdad, sus tomas no tienen sentido por separado.
            $table->foreignId('tratamiento_id')->constrained('tratamientos')->cascadeOnDelete();

            $table->dateTime('fecha_hora_programada'); // UTC
            $table->dateTime('fecha_hora_real')->nullable();
            $table->enum('estado', EstadoToma::valores())->default(EstadoToma::Pendiente->value);
            $table->string('notas')->nullable();

            $table->timestamps();

            $table->index(['tratamiento_id', 'fecha_hora_programada']);
            // Es el índice de la pantalla "Medicación de hoy".
            $table->index(['estado', 'fecha_hora_programada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomas_medicamento');
    }
};
