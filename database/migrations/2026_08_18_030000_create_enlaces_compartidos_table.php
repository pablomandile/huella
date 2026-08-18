<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces para mostrar la ficha de una mascota **sin cuenta**.
 *
 * Es el caso del veterinario nuevo, el viaje o la guardería: gente que necesita
 * ver el historial una vez y a la que no tiene sentido pedirle que se registre.
 *
 * ¿Por qué una tabla y no una URL firmada de Laravel, como la invitación? Porque
 * acá el requisito es **poder revocar**, y una firma solo se invalida rotando
 * `APP_KEY` — que en este proyecto además desencripta `two_factor_secret` y
 * dejaría a todos los usuarios sin 2FA. Una fila se borra y listo.
 *
 * Tampoco van como columnas de `mascotas`: ese modelo se serializa en media
 * docena de caminos (MascotaResource, el share() de Inertia, el exportador, el
 * PDF) y un secreto ahí es una fuga esperando un `...$mascota->toArray()`.
 * Además haría un solo enlace por mascota: revocar el de la guardería mataría el
 * de la veterinaria.
 *
 * **Ninguna columna ENUM**, a propósito: sumar un caso a un enum de PHP pasa los
 * tests —sqlite no valida ENUM— y revienta en producción al primer guardado.
 * Acá lo que se persiste son fechas y booleanos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_compartidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();

            /*
             * El token va **en claro** y no hasheado, al revés que un password
             * reset. Dos razones:
             *
             * - El dueño tiene que poder volver a copiar el enlace dos días
             *   después, que es exactamente lo que va a querer hacer. Con un hash
             *   habría que revocar y crear otro cada vez.
             * - A diferencia de un token de API, el secreto vive en la misma base
             *   que los datos que protege: si esta tabla se filtra, el historial
             *   clínico ya se filtró. Hashearlo no defiende de nada nuevo.
             */
            $table->string('token', 48)->unique();

            // Para distinguir dos enlaces: "para la guardería", "veterinaria".
            $table->string('nombre', 80)->nullable();

            // Los documentos de la mascota van siempre; las radiografías y los
            // análisis de las visitas solo si el dueño lo pide expresamente.
            $table->boolean('incluye_adjuntos')->default(false);

            // Obligatorio: ver App\Enums\VigenciaEnlace.
            $table->timestamp('expira_en');

            // Lo único que le avisa al dueño que un enlace se le escapó.
            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->unsignedInteger('visitas')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_compartidos');
    }
};
