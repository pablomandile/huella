<?php

use App\Enums\LugarBano;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mascota_id')->constrained('mascotas')->cascadeOnDelete();

            $table->date('fecha');
            $table->enum('lugar', LugarBano::valores())->default(LugarBano::Casa->value);
            $table->string('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mascota_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banos');
    }
};
