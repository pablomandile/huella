<?php

namespace Database\Factories;

use App\Enums\IntensidadCelo;
use App\Models\CicloCelo;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CicloCelo>
 */
class CicloCeloFactory extends Factory
{
    public function definition(): array
    {
        $inicio = now()->subMonths(6);

        return [
            'mascota_id' => Mascota::factory()->hembra()->entera(),
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addDays(18)->toDateString(),
            'intensidad' => fake()->randomElement(IntensidadCelo::cases()),
            'hubo_monta' => false,
        ];
    }

    /** Empezó y no terminó todavía. */
    public function enCurso(): static
    {
        return $this->state(fn () => ['fecha_fin' => null]);
    }

    public function empezoEl(string $fecha, int $duracionDias = 18): static
    {
        return $this->state(fn () => [
            'fecha_inicio' => $fecha,
            'fecha_fin' => now()->parse($fecha)->addDays($duracionDias - 1)->toDateString(),
        ]);
    }
}
