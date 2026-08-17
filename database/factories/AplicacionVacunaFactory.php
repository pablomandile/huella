<?php

namespace Database\Factories;

use App\Models\AplicacionVacuna;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AplicacionVacuna>
 */
class AplicacionVacunaFactory extends Factory
{
    public function definition(): array
    {
        $fecha = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'mascota_id' => Mascota::factory(),
            'vacuna_libre' => fake()->randomElement(['Quíntuple', 'Antirrábica', 'Triple felina']),
            'fecha' => $fecha->format('Y-m-d'),
            'dosis_nro' => fake()->optional()->numberBetween(1, 3),
            'marca' => fake()->optional()->company(),
            'lote' => fake()->optional()->bothify('L###??'),
        ];
    }

    /** Con refuerzo al año: el caso del criterio de la fase. */
    public function conRefuerzoAlAnio(): static
    {
        return $this->state(fn (array $atributos) => [
            'proxima_dosis' => now()->parse($atributos['fecha'])->addYear()->toDateString(),
        ]);
    }

    /** La próxima dosis cae dentro de la ventana de aviso. */
    public function porVencer(int $enDias = 5): static
    {
        return $this->state(fn () => [
            'fecha' => now()->subYear()->toDateString(),
            'proxima_dosis' => now()->addDays($enDias)->toDateString(),
        ]);
    }
}
