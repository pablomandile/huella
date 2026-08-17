<?php

use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_diario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            $table->date('fecha');
            $table->string('titulo', 160)->nullable();
            // Lo único obligatorio: es una bitácora, no un formulario.
            $table->text('contenido');
            $table->enum('categoria', CategoriaEntrada::valores())
                ->default(CategoriaEntrada::General->value);
            $table->enum('animo', Animo::valores())->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas_diario');
    }
};
