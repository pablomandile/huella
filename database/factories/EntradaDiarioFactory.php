<?php

namespace Database\Factories;

use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntradaDiario>
 */
class EntradaDiarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'fecha' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'titulo' => fake()->optional(0.7)->sentence(4),
            'contenido' => fake()->paragraph(2),
            'categoria' => fake()->randomElement(CategoriaEntrada::cases()),
            'animo' => fake()->optional(0.5)->randomElement(Animo::cases()),
        ];
    }

    public function elDia(string $fecha): static
    {
        return $this->state(fn () => ['fecha' => $fecha]);
    }
}
