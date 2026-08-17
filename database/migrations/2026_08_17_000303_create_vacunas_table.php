<?php

use App\Enums\Especie;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacunas', function (Blueprint $table) {
            $table->id();

            // El esquema original no tenía esta columna, y sin ella la regla 4
            // (duplicar un semilla para personalizarlo) era imposible en el
            // único catálogo donde más hace falta: los planes vacunales varían
            // entre veterinarias.
            $table->foreignId('usuario_id')->nullable()
                ->constrained('users')->cascadeOnDelete();

            $table->string('nombre', 120);
            $table->enum('especie', Especie::valores())->default(Especie::Perro->value);
            $table->text('descripcion')->nullable();
            // 12 = revacunar al año. NULL = dosis única, sin refuerzo.
            $table->unsignedSmallInteger('meses_refuerzo')->nullable();
            $table->boolean('obligatoria')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'especie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacunas');
    }
};
