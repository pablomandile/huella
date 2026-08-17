<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();

            // Veterinaria habitual. Si se da de baja la veterinaria el
            // profesional sobrevive suelto: sigue siendo quien atendió.
            $table->foreignId('veterinaria_id')->nullable()
                ->constrained('veterinarias')->nullOnDelete();

            $table->string('nombre', 140);
            $table->string('matricula', 60)->nullable();
            $table->string('especialidad', 120)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('foto')->nullable(); // reservado; sin UI todavía
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinarios');
    }
};
