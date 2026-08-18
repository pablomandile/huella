<?php

use App\Enums\TipoAdjunto;
use App\Enums\TipoRecordatorio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La libreta sanitaria y el certificado de rabia: los dos papeles que el dueño
 * carga una vez y necesita tener a mano.
 *
 * No hay tabla nueva. `adjuntos` ya es polimórfico y `Adjunto::mascotaAsociada()`
 * ya contempla colgar de una mascota, así que alcanza con sumar los dos tipos.
 * Lo que sí hace falta es **ensanchar los ENUM de MySQL**: los casos de PHP solos
 * pasarían los tests —sqlite no valida ENUM— y reventarían en producción con un
 * 500 al primer guardado.
 *
 * El vencimiento del certificado va como columna de `mascotas`, calcando
 * `seguro_vencimiento`: es una fecha de la mascota que genera su recordatorio por
 * observer, y la idempotencia por `origen_type` + `origen_id` + `tipo` deja que
 * convivan los dos sobre el mismo origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjuntos', function (Blueprint $table) {
            // El `default` va de nuevo: `change()` reemplaza la definición
            // entera, y omitirlo lo borraría.
            $table->enum('tipo', TipoAdjunto::valores())
                ->default(TipoAdjunto::Otro->value)
                ->change();
        });

        Schema::table('recordatorios', function (Blueprint $table) {
            $table->enum('tipo', TipoRecordatorio::valores())->change();
        });

        Schema::table('mascotas', function (Blueprint $table) {
            $table->date('rabia_vencimiento')->nullable()->after('seguro_vencimiento');
        });
    }

    public function down(): void
    {
        /*
         * Volver atrás **borra** los documentos cargados y descarta sus
         * recordatorios: un ENUM más angosto no puede guardar esos valores, y
         * dejarlos haría fallar el propio rollback. Los archivos del disco
         * quedan huérfanos a propósito, para no perderlos sin pedirlo.
         */
        DB::table('adjuntos')
            ->whereIn('tipo', [
                TipoAdjunto::LibretaSanitaria->value,
                TipoAdjunto::CertificadoRabia->value,
            ])
            ->delete();

        DB::table('recordatorios')
            ->where('tipo', TipoRecordatorio::CertificadoRabia->value)
            ->delete();

        Schema::table('mascotas', function (Blueprint $table) {
            $table->dropColumn('rabia_vencimiento');
        });

        Schema::table('adjuntos', function (Blueprint $table) {
            $table->enum('tipo', array_values(array_diff(
                TipoAdjunto::valores(),
                [TipoAdjunto::LibretaSanitaria->value, TipoAdjunto::CertificadoRabia->value],
            )))
                ->default(TipoAdjunto::Otro->value)
                ->change();
        });

        Schema::table('recordatorios', function (Blueprint $table) {
            $table->enum('tipo', array_values(array_diff(
                TipoRecordatorio::valores(),
                [TipoRecordatorio::CertificadoRabia->value],
            )))->change();
        });
    }
};
