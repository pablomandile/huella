<?php

namespace Database\Factories;

use App\Enums\OrigenPeso;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistroPeso>
 */
class RegistroPesoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'fecha' => now()->toDateString(),
            'peso_kg' => fake()->randomFloat(2, 3, 40),
            'origen' => OrigenPeso::Casa,
        ];
    }

    public function enVeterinaria(): static
    {
        return $this->state(fn () => ['origen' => OrigenPeso::Veterinaria]);
    }

    public function elDia(string $fecha, float $kilos): static
    {
        return $this->state(fn () => ['fecha' => $fecha, 'peso_kg' => $kilos]);
    }
}
