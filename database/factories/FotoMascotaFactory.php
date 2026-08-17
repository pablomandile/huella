<?php

namespace Database\Factories;

use App\Models\FotoMascota;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FotoMascota>
 */
class FotoMascotaFactory extends Factory
{
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();

        return [
            'mascota_id' => Mascota::factory(),
            'ruta' => "mascotas/1/{$uuid}.webp",
            'ruta_miniatura' => "mascotas/1/{$uuid}-min.webp",
            'fecha' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'epigrafe' => fake()->optional()->sentence(4),
        ];
    }
}
