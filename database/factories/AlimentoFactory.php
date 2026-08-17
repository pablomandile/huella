<?php

namespace Database\Factories;

use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use App\Models\Alimento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alimento>
 */
class AlimentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'marca' => fake()->optional()->company(),
            'nombre' => fake()->words(2, true),
            'tipo' => fake()->randomElement(TipoAlimento::cases()),
            'gama' => fake()->optional()->randomElement(GamaAlimento::cases()),
            'especie' => fake()->randomElement([Especie::Perro, Especie::Gato]),
            'etapa' => fake()->randomElement(EtapaVida::cases()),
            'presentacion' => fake()->randomElement(['Bolsa 3 kg', 'Bolsa 15 kg', 'Lata 340 g']),
            'medicado' => fake()->boolean(15),
        ];
    }

    /** Registro precargado del sistema: se ve, se duplica, no se edita. */
    public function semilla(): static
    {
        return $this->state(fn () => ['usuario_id' => null]);
    }
}
