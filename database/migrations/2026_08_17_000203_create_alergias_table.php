<?php

use App\Enums\SeveridadAlergia;
use App\Enums\TipoAlergia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alergias de la mascota. No estaban en el esquema original, pero la
     * especificación las exige en el PDF exportado (§4.14) y son el dato que
     * más importa tener a mano en una urgencia.
     */
    public function up(): void
    {
        Schema::create('alergias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->enum('tipo', TipoAlergia::valores())->default(TipoAlergia::Otra->value);
            $table->string('agente', 140); // "pollo", "penicilina", "ácaros"
            $table->enum('severidad', SeveridadAlergia::valores())->nullable();
            $table->date('fecha_deteccion')->nullable();
            $table->text('sintomas')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mascota_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alergias');
    }
};
