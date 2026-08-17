<?php

namespace Database\Factories;

use App\Enums\EstadoTratamiento;
use App\Enums\ViaAdministracion;
use App\Models\Mascota;
use App\Models\Tratamiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tratamiento>
 */
class TratamientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'medicamento_libre' => fake()->randomElement(['Cefalexina', 'Meloxicam', 'Metronidazol']),
            'dosis' => fake()->randomElement(['1 comprimido', 'medio comprimido', '2,5 ml']),
            'via' => ViaAdministracion::Oral,
            'frecuencia_horas' => fake()->randomElement([8, 12, 24]),
            'fecha_inicio' => now()->toDateString(),
            'duracion_dias' => fake()->randomElement([5, 7, 10]),
            'hora_primera_toma' => '08:00',
            'estado' => EstadoTratamiento::Activo,
        ];
    }

    /** Cada 8 horas por 7 días: 21 tomas, el caso típico de un antibiótico. */
    public function cada8Horas(): static
    {
        return $this->state(fn () => [
            'frecuencia_horas' => 8,
            'duracion_dias' => 7,
            'veces_por_dia' => null,
        ]);
    }

    /** Sin frecuencia: "dárselo si le duele". No genera cronograma. */
    public function aDemanda(): static
    {
        return $this->state(fn () => [
            'frecuencia_horas' => null,
            'veces_por_dia' => null,
            'duracion_dias' => null,
        ]);
    }

    /** Crónico sin fecha de fin: el que hace trabajar al tope de 90 días. */
    public function cronico(): static
    {
        return $this->state(fn () => [
            'frecuencia_horas' => 24,
            'duracion_dias' => null,
            'fecha_fin' => null,
        ]);
    }

    public function finalizado(): static
    {
        return $this->state(fn () => ['estado' => EstadoTratamiento::Finalizado]);
    }
}
