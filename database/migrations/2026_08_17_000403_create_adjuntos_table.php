<?php

use App\Enums\TipoAdjunto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjuntos', function (Blueprint $table) {
            $table->id();

            // Polimórfico: cuelga de una visita, un tratamiento y, en las fases
            // siguientes, de una aplicación de vacuna o una entrada de diario.
            // AdjuntoPolicy resuelve la propiedad subiendo por esta relación
            // hasta la mascota.
            $table->morphs('adjuntable');

            $table->enum('tipo', TipoAdjunto::valores())->default(TipoAdjunto::Otro->value);
            $table->string('ruta'); // disco privado 'local', servido por controlador
            $table->string('nombre_original')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamanio_bytes')->nullable();
            $table->string('descripcion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjuntos');
    }
};
