<?php

use App\Enums\Especie;
use App\Enums\Sexo;
use App\Enums\TipoPelaje;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mascotas', function (Blueprint $table) {
            $table->id();

            // Dueño original. La autorización NO compara esta columna: pasa por
            // el pivote mascota_usuario, que es lo que habilita multi-cuidador.
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();

            $table->string('nombre', 80);
            $table->enum('especie', Especie::valores())->default(Especie::Perro->value);
            $table->string('raza', 120)->nullable();
            $table->enum('sexo', Sexo::valores())->default(Sexo::Desconocido->value);
            $table->date('fecha_nacimiento')->nullable();
            // 1 = edad aproximada (típico en adopciones)
            $table->boolean('fecha_nacimiento_estimada')->default(false);
            $table->date('fecha_adopcion')->nullable();
            $table->string('color', 80)->nullable();
            $table->enum('tipo_pelaje', TipoPelaje::valores())->nullable();
            $table->text('senias_particulares')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('foto_perfil')->nullable(); // ruta en el disco privado

            // Identificación
            $table->string('microchip', 40)->nullable();
            $table->date('fecha_microchip')->nullable();
            $table->string('libreta_sanitaria', 60)->nullable();
            $table->string('pedigree', 60)->nullable();

            // Reproductivo. Si castrado = 1 el módulo de celo se oculta.
            $table->boolean('castrado')->default(false);
            $table->date('fecha_castracion')->nullable();

            // Seguro / cobertura (el vencimiento genera recordatorio en fase 5)
            $table->string('seguro_compania', 120)->nullable();
            $table->string('seguro_poliza', 80)->nullable();
            $table->date('seguro_vencimiento')->nullable();

            // Estado. Una mascota fallecida pasa a modo lectura.
            $table->boolean('activo')->default(true);
            $table->date('fecha_fallecimiento')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Único por usuario, no global: dos usuarios distintos no deben
            // chocar entre sí. MySQL admite múltiples NULL en índices únicos.
            $table->unique(['usuario_id', 'microchip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mascotas');
    }
};
