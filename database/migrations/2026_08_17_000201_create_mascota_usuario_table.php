<?php

use App\Enums\RolCuidador;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivote que prepara el multi-cuidador (v2) sin pagar su costo hoy.
     *
     * Al crear una mascota, MascotaObserver inserta acá la fila 'propietario'.
     * MascotaPolicy autoriza consultando esta tabla —nunca comparando
     * mascotas.usuario_id— así que la v2 solo agrega la UI de invitación.
     */
    public function up(): void
    {
        Schema::create('mascota_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->enum('rol', RolCuidador::valores())->default(RolCuidador::Propietario->value);
            $table->timestamps();

            $table->unique(['mascota_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mascota_usuario');
    }
};
