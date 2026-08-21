<?php

namespace Database\Factories;

use App\Models\EnlaceCompartido;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnlaceCompartido>
 */
class EnlaceCompartidoFactory extends Factory
{
    protected $model = EnlaceCompartido::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'creado_por' => User::factory(),
            'token' => EnlaceCompartido::nuevoToken(),
            'nombre' => null,
            'incluye_adjuntos' => false,
            'expira_en' => now()->addDays(30),
        ];
    }

    public function vencido(): static
    {
        return $this->state(fn () => ['expira_en' => now()->subDay()]);
    }

    /** NULL en `expira_en` es "no vence": solo lo apaga una revocación. */
    public function sinVencimiento(): static
    {
        return $this->state(fn () => ['expira_en' => null]);
    }

    public function conAdjuntos(): static
    {
        return $this->state(fn () => ['incluye_adjuntos' => true]);
    }
}
