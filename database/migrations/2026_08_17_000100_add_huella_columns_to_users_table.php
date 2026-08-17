<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos propios de Huella sobre la tabla `users` del starter kit.
     *
     * La tabla y sus columnas base (name, email, password) se dejan como las
     * genera el kit; el resto del dominio usa nombres en español.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono', 40)->nullable()->after('email');

            // Se persiste en UTC y se convierte a esta zona al mostrar
            // y al evaluar los recordatorios.
            $table->string('zona_horaria', 64)
                ->default('America/Argentina/Buenos_Aires')
                ->after('telefono');

            // Anticipación por defecto del recordatorio de celo (especificación §4.9).
            $table->unsignedSmallInteger('dias_anticipacion_celo')
                ->default(14)
                ->after('zona_horaria');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telefono',
                'zona_horaria',
                'dias_anticipacion_celo',
                'deleted_at',
            ]);
        });
    }
};
