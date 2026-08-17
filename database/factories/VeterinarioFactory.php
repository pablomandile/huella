<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Veterinario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veterinario>
 */
class VeterinarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'veterinaria_id' => null,
            'nombre' => fake()->name(),
            'matricula' => fake()->optional()->numerify('MP #####'),
            'especialidad' => fake()->optional()->randomElement([
                'Clínica general', 'Traumatología', 'Etología', 'Dermatología', 'Oftalmología',
            ]),
            'telefono' => fake()->optional()->numerify('11-####-####'),
            'email' => fake()->optional()->safeEmail(),
            'activo' => true,
        ];
    }
}
