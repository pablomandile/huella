<?php

declare(strict_types=1);

use App\Enums\TamanoLetra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tamaño de letra vive también en la cuenta, no solo en una cookie: la
 * cookie evita el salto de tamaño al cargar, la columna hace que la preferencia
 * siga a la persona cuando entra desde otro teléfono.
 *
 * `string` y no ENUM de MySQL a propósito: sumar un tamaño sería un cambio de
 * tipo, y el enum de PHP ya valida en la entrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->string('tamano_letra', 20)
                ->default(TamanoLetra::PORDEFECTO)
                ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropColumn('tamano_letra');
        });
    }
};
