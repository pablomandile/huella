<?php

namespace Database\Factories;

use App\Enums\LugarBano;
use App\Models\Bano;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bano>
 */
class BanoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'fecha' => now()->toDateString(),
            'lugar' => LugarBano::Casa,
        ];
    }

    public function enSalon(): static
    {
        return $this->state(fn () => ['lugar' => LugarBano::Salon]);
    }

    public function elDia(string $fecha): static
    {
        return $this->state(fn () => ['fecha' => $fecha]);
    }
}
