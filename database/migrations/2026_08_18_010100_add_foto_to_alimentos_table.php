<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La foto del paquete de alimento.
 *
 * Sirve para reconocer la bolsa en la góndola: dos balanceados de la misma marca
 * se distinguen por el color del envase mucho antes que por el nombre completo.
 *
 * Una sola columna con la ruta de la imagen grande; la miniatura se deriva
 * cambiando el sufijo, igual que `mascotas.foto_perfil`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alimentos', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('presentacion');
        });
    }

    public function down(): void
    {
        Schema::table('alimentos', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
