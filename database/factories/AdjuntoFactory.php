<?php

namespace Database\Factories;

use App\Enums\TipoAdjunto;
use App\Models\Adjunto;
use App\Models\Visita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adjunto>
 */
class AdjuntoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'adjuntable_type' => Visita::class,
            'adjuntable_id' => Visita::factory(),
            'tipo' => fake()->randomElement(TipoAdjunto::cases()),
            'ruta' => 'adjuntos/'.fake()->uuid().'.pdf',
            'nombre_original' => 'receta.pdf',
            'mime' => 'application/pdf',
            'tamanio_bytes' => fake()->numberBetween(20_000, 2_000_000),
        ];
    }

    public function receta(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoAdjunto::Receta,
            'nombre_original' => 'receta.pdf',
            'mime' => 'application/pdf',
        ]);
    }
}
