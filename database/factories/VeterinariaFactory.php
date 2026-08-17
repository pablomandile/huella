<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veterinaria>
 */
class VeterinariaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'nombre' => 'Veterinaria '.fake()->lastName(),
            'direccion' => fake()->streetAddress(),
            'localidad' => fake()->randomElement([
                'Caballito', 'Villa Crespo', 'Vicente López', 'La Plata', 'Rosario',
            ]),
            'telefono' => fake()->numerify('11-####-####'),
            'whatsapp' => fake()->optional()->numerify('11-####-####'),
            'email' => fake()->optional()->companyEmail(),
            'horarios' => fake()->optional()->randomElement([
                'Lunes a viernes de 9 a 20, sábados de 9 a 13',
                'Todos los días de 8 a 22',
            ]),
            'urgencias_24h' => fake()->boolean(25),
            'activa' => true,
        ];
    }

    public function urgencias24h(): static
    {
        return $this->state(fn () => ['urgencias_24h' => true]);
    }
}
