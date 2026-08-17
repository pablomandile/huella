<?php

namespace App\Console\Commands;

use App\Enums\EstadoToma;
use App\Enums\EstadoTratamiento;
use App\Models\Tratamiento;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pasa a "terminado" lo que ya terminó.
 *
 * Sin esto la ficha se llena de tratamientos "en curso" de hace meses y deja de
 * servir para lo único que importa: saber qué está tomando la mascota hoy.
 *
 * Se cierra por la última toma programada, no por la fecha de fin: es el dato
 * que refleja de verdad el final del cronograma, incluso cuando la última dosis
 * cae a la medianoche del día siguiente.
 */
class CerrarTratamientosVencidos extends Command
{
    protected $signature = 'huella:cerrar-tratamientos';

    protected $description = 'Finaliza los tratamientos cuyo cronograma ya terminó';

    public function handle(): int
    {
        $cerrados = 0;

        Tratamiento::query()
            ->where('estado', EstadoTratamiento::Activo)
            ->whereNotNull('fecha_fin')
            ->orWhere(
                fn (Builder $consulta) => $consulta
                    ->where('estado', EstadoTratamiento::Activo)
                    ->whereNotNull('duracion_dias'),
            )
            ->with('mascota.propietario')
            ->chunkById(200, function ($tratamientos) use (&$cerrados) {
                foreach ($tratamientos as $tratamiento) {
                    if ($this->yaTermino($tratamiento)) {
                        $tratamiento->update(['estado' => EstadoTratamiento::Finalizado]);
                        $cerrados++;
                    }
                }
            });

        $this->info("Tratamientos finalizados: {$cerrados}");

        return self::SUCCESS;
    }

    /**
     * Terminó cuando no le queda ninguna toma por delante. Las pendientes
     * vencidas no lo mantienen abierto: son deuda del pasado, no cronograma.
     */
    private function yaTermino(Tratamiento $tratamiento): bool
    {
        $tienePendientesFuturas = $tratamiento->tomas()
            ->where('estado', EstadoToma::Pendiente)
            ->where('fecha_hora_programada', '>', now())
            ->exists();

        if ($tienePendientesFuturas) {
            return false;
        }

        $ultimoDia = $tratamiento->ultimoDia();

        // Sin fecha de fin ni duración es un tratamiento abierto: no se cierra
        // solo, aunque el tope de 90 días haya dejado de generar tomas.
        return $ultimoDia !== null && $ultimoDia->isBefore(now()->startOfDay());
    }
}
