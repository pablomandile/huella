<?php

namespace Database\Factories;

use App\Enums\SeveridadAlergia;
use App\Enums\TipoAlergia;
use App\Models\Alergia;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alergia>
 */
class AlergiaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'tipo' => fake()->randomElement(TipoAlergia::cases()),
            'agente' => fake()->randomElement(['Pollo', 'Penicilina', 'Ácaros', 'Pulgas', 'Polen']),
            'severidad' => fake()->optional(0.7)->randomElement(SeveridadAlergia::cases()),
            'fecha_deteccion' => fake()->boolean(60)
                ? fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d')
                : null,
            'sintomas' => fake()->optional()->sentence(8),
        ];
    }
}
