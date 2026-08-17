<?php

namespace Database\Factories;

use App\Enums\EstadoRecordatorio;
use App\Enums\TipoRecordatorio;
use App\Models\Mascota;
use App\Models\Recordatorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recordatorio>
 */
class RecordatorioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'tipo' => TipoRecordatorio::Personalizado,
            'titulo' => 'Cortarle las uñas',
            'fecha_objetivo' => now()->addDays(10)->toDateString(),
            'dias_anticipacion' => 7,
            'hora_notificacion' => '09:00',
            'estado' => EstadoRecordatorio::Pendiente,
        ];
    }

    /** Ya entró en la ventana de aviso: es lo que el job tiene que levantar. */
    public function porAvisar(): static
    {
        return $this->state(fn () => [
            'fecha_objetivo' => now()->addDays(3)->toDateString(),
            'dias_anticipacion' => 7,
            'estado' => EstadoRecordatorio::Pendiente,
        ]);
    }

    public function notificado(): static
    {
        return $this->state(fn () => ['estado' => EstadoRecordatorio::Notificado]);
    }

    public function completado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoRecordatorio::Completado,
            'fecha_completado' => now(),
        ]);
    }
}
