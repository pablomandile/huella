<?php

namespace Database\Factories;

use App\Enums\TipoVisita;
use App\Models\Mascota;
use App\Models\Visita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visita>
 */
class VisitaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'fecha_hora' => fake()->dateTimeBetween('-2 years', 'now'),
            'tipo' => fake()->randomElement(TipoVisita::cases()),
            'motivo' => fake()->randomElement([
                'Control anual', 'Vómitos y diarrea', 'Cojera de la pata trasera',
                'Otitis', 'Chequeo pre quirúrgico',
            ]),
            'diagnostico' => fake()->optional(0.7)->sentence(10),
            'indicaciones' => fake()->optional(0.6)->sentence(12),
            'temperatura' => fake()->optional(0.5)->randomFloat(1, 37.5, 39.5),
            'costo' => fake()->optional(0.6)->randomFloat(2, 8000, 120000),
            'moneda' => 'ARS',
        ];
    }

    public function urgencia(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoVisita::Urgencia,
            'motivo' => 'Vómitos y diarrea',
        ]);
    }

    public function conProximoControl(int $enDias = 15): static
    {
        return $this->state(fn () => [
            'proximo_control' => now()->addDays($enDias)->toDateString(),
        ]);
    }
}
