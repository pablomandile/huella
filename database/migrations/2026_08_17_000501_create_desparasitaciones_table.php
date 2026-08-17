<?php

use App\Enums\TipoDesparasitacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desparasitaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            $table->foreignId('medicamento_id')->nullable()
                ->constrained('medicamentos')->nullOnDelete();
            $table->string('medicamento_libre', 140)->nullable();

            $table->foreignId('visita_id')->nullable()
                ->constrained('visitas')->nullOnDelete();

            $table->enum('tipo', TipoDesparasitacion::valores())
                ->default(TipoDesparasitacion::Interna->value);
            $table->date('fecha');
            $table->string('dosis', 80)->nullable();
            // La dosis de un antiparasitario depende del peso, así que el peso
            // del día queda registrado con la aplicación.
            $table->decimal('peso_al_momento', 6, 2)->nullable();

            $table->date('proxima_fecha')->nullable(); // dispara el recordatorio
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha']);
            $table->index('proxima_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desparasitaciones');
    }
};
