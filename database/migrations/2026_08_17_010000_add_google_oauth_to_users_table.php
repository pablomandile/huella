<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingreso con Google.
     *
     * Lo importante acá es que `password` pasa a ser nullable: quien entra con
     * Google nunca eligió una contraseña, y guardarle una al azar sería peor que
     * no tener ninguna —figuraría como que puede entrar con email y clave cuando
     * no puede—.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // El `sub` de Google: su identificador estable. No es el email, que
            // el usuario puede cambiar en su cuenta de Google.
            $table->string('google_id', 64)->nullable()->unique()->after('email');

            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');

            // Volver atrás con usuarios sin contraseña dejaría la tabla en un
            // estado que MySQL no acepta, así que se les pone una imposible de
            // usar: no coincide con ningún hash, y el usuario puede recuperarla
            // por email.
            DB::table('users')
                ->whereNull('password')
                ->update(['password' => '']);

            $table->string('password')->nullable(false)->change();
        });
    }
};
