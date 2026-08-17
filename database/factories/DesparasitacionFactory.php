<?php

namespace Database\Factories;

use App\Enums\TipoDesparasitacion;
use App\Models\Desparasitacion;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Desparasitacion>
 */
class DesparasitacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'medicamento_libre' => fake()->randomElement(['Drontal Plus', 'NexGard', 'Total Full']),
            'tipo' => fake()->randomElement(TipoDesparasitacion::cases()),
            'fecha' => now()->subMonths(3)->toDateString(),
            'dosis' => fake()->randomElement(['1 comprimido', 'media pipeta']),
            'peso_al_momento' => fake()->randomFloat(2, 3, 40),
        ];
    }

    /** Cada tres meses, que es la pauta más habitual. */
    public function conProximaEnTresMeses(): static
    {
        return $this->state(fn (array $atributos) => [
            'proxima_fecha' => now()->parse($atributos['fecha'])->addMonths(3)->toDateString(),
        ]);
    }
}
