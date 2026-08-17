<?php

use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alimentos', function (Blueprint $table) {
            $table->id();

            // NULL = semilla del sistema (ver medicamentos).
            $table->foreignId('usuario_id')->nullable()
                ->constrained('users')->cascadeOnDelete();

            $table->string('marca', 120)->nullable();
            $table->string('nombre', 140);
            $table->enum('tipo', TipoAlimento::valores())
                ->default(TipoAlimento::BalanceadoSeco->value);
            $table->enum('gama', GamaAlimento::valores())->nullable();
            $table->enum('especie', Especie::valores())->default(Especie::Perro->value);
            $table->enum('etapa', EtapaVida::valores())->default(EtapaVida::Adulto->value);
            $table->string('presentacion', 80)->nullable();
            $table->boolean('medicado')->default(false); // renal, hepático, gastrointestinal
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'especie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alimentos');
    }
};
