<?php

namespace Database\Factories;

use App\Enums\Especie;
use App\Models\User;
use App\Models\Vacuna;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacuna>
 */
class VacunaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'nombre' => fake()->unique()->randomElement([
                'Quíntuple', 'Séxtuple', 'Antirrábica', 'Triple felina', 'Leucemia felina',
            ]),
            'especie' => fake()->randomElement([Especie::Perro, Especie::Gato]),
            'meses_refuerzo' => fake()->randomElement([null, 12, 24]),
            'obligatoria' => fake()->boolean(30),
        ];
    }

    /** Registro precargado del sistema: se ve, se duplica, no se edita. */
    public function semilla(): static
    {
        return $this->state(fn () => ['usuario_id' => null]);
    }
}
