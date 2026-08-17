<?php

namespace Database\Factories;

use App\Enums\CategoriaMedicamento;
use App\Models\Medicamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicamento>
 */
class MedicamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'nombre_comercial' => fake()->unique()->word().' '.fake()->numberBetween(50, 500),
            'droga' => fake()->randomElement(['Amoxicilina', 'Meloxicam', 'Praziquantel', 'Fluralaner']),
            'laboratorio' => fake()->optional()->company(),
            'presentacion' => fake()->randomElement(['Comprimidos', 'Suspensión 50 ml', 'Pipeta']),
            'categoria' => fake()->randomElement(CategoriaMedicamento::cases()),
            'requiere_receta' => fake()->boolean(40),
        ];
    }

    /** Registro precargado del sistema: se ve, se duplica, no se edita. */
    public function semilla(): static
    {
        return $this->state(fn () => ['usuario_id' => null]);
    }
}
