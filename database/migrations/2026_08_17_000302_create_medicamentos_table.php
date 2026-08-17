<?php

use App\Enums\CategoriaMedicamento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicamentos', function (Blueprint $table) {
            $table->id();

            // NULL = semilla del sistema, visible para todos y no editable.
            // Regla de negocio 4: los semilla se duplican, no se editan.
            $table->foreignId('usuario_id')->nullable()
                ->constrained('users')->cascadeOnDelete();

            $table->string('nombre_comercial', 140);
            $table->string('droga', 140)->nullable();
            $table->string('laboratorio', 120)->nullable();
            $table->string('presentacion', 120)->nullable();
            $table->enum('categoria', CategoriaMedicamento::valores())
                ->default(CategoriaMedicamento::Otro->value);
            $table->boolean('requiere_receta')->default(false);
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'nombre_comercial']);
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicamentos');
    }
};
