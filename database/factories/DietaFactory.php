<?php

namespace Database\Factories;

use App\Models\Alimento;
use App\Models\Dieta;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dieta>
 */
class DietaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'alimento_id' => Alimento::factory()->semilla(),
            'fecha_inicio' => now()->subMonths(2)->toDateString(),
            'racion_diaria_g' => fake()->numberBetween(150, 500),
            'tomas_por_dia' => fake()->numberBetween(1, 3),
        ];
    }

    /** Cerrada: ya no es la que come. */
    public function cerrada(?string $fechaFin = null): static
    {
        return $this->state(fn (array $atributos) => [
            'fecha_fin' => $fechaFin ?? now()->subDays(10)->toDateString(),
        ]);
    }

    public function prescripta(): static
    {
        return $this->state(fn () => [
            'prescripta' => true,
            'motivo' => 'Dieta renal indicada por el veterinario',
        ]);
    }
}
