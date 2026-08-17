<?php

namespace Database\Factories;

use App\Enums\EstadoToma;
use App\Models\TomaMedicamento;
use App\Models\Tratamiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TomaMedicamento>
 */
class TomaMedicamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tratamiento_id' => Tratamiento::factory(),
            'fecha_hora_programada' => now(),
            'estado' => EstadoToma::Pendiente,
        ];
    }

    public function administrada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoToma::Administrada,
            'fecha_hora_real' => now(),
        ]);
    }

    public function omitida(): static
    {
        return $this->state(fn () => ['estado' => EstadoToma::Omitida]);
    }
}
