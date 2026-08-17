<?php

namespace Database\Factories;

use App\Enums\Especie;
use App\Enums\Sexo;
use App\Enums\TipoPelaje;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mascota>
 */
class MascotaFactory extends Factory
{
    public function definition(): array
    {
        $especie = fake()->randomElement([Especie::Perro, Especie::Gato]);

        return [
            'usuario_id' => User::factory(),
            'nombre' => fake()->firstName(),
            'especie' => $especie,
            'raza' => $especie === Especie::Perro
                ? fake()->randomElement(['Mestizo', 'Caniche', 'Labrador', 'Galgo', 'Salchicha'])
                : fake()->randomElement(['Mestizo', 'Siamés', 'Persa', 'Común europeo']),
            'sexo' => fake()->randomElement(Sexo::cases()),
            'fecha_nacimiento' => fake()->dateTimeBetween('-12 years', '-3 months')->format('Y-m-d'),
            'fecha_nacimiento_estimada' => fake()->boolean(30),
            'color' => fake()->randomElement(['Negro', 'Blanco', 'Marrón', 'Atigrado', 'Tricolor']),
            'tipo_pelaje' => fake()->randomElement(TipoPelaje::cases()),
            'castrado' => fake()->boolean(40),
        ];
    }

    public function hembra(): static
    {
        return $this->state(['sexo' => Sexo::Hembra]);
    }

    public function castrada(): static
    {
        return $this->state([
            'castrado' => true,
            'fecha_castracion' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
        ]);
    }

    public function entera(): static
    {
        return $this->state(['castrado' => false, 'fecha_castracion' => null]);
    }

    public function fallecida(): static
    {
        return $this->state([
            'fecha_fallecimiento' => fake()->dateTimeBetween('-1 year', 'yesterday')->format('Y-m-d'),
        ]);
    }
}
