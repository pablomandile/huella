<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `expira_en` pasa a ser nullable: NULL significa "no vence".
 *
 * La tabla nació con el vencimiento obligatorio a propósito (ver
 * `App\Enums\VigenciaEnlace`), y esto lo afloja. Lo que sostiene la decisión es
 * que este enlace **sí se puede revocar** —para eso existe la tabla, en vez de
 * una URL firmada—: la defensa deja de ser el reloj y pasa a ser el dueño.
 *
 * Nada se migra: las filas existentes conservan su fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_compartidos', function (Blueprint $table) {
            $table->timestamp('expira_en')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volver a NOT NULL con filas en NULL falla. A los que no vencen se les
        // pone la fecha de ahora: quedan vencidos, que es el lado seguro para
        // un enlace que da acceso a una historia clínica.
        DB::table('enlaces_compartidos')->whereNull('expira_en')->update(['expira_en' => now()]);

        Schema::table('enlaces_compartidos', function (Blueprint $table) {
            $table->timestamp('expira_en')->nullable(false)->change();
        });
    }
};
