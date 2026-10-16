<?php

namespace App\Console\Commands;

use App\Models\Adjunto;
use App\Models\Alimento;
use App\Models\Mascota;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra de verdad las cuentas eliminadas hace más de 30 días.
 *
 * `User` usa SoftDeletes: «Eliminar cuenta» solo marca la fila, y sin esto
 * nada se borraba nunca —ni las mascotas, ni los archivos—. La política de
 * privacidad promete exactamente este plazo; cambiarlo acá obliga a cambiarla
 * allá y a subir `config('legales.version')`.
 *
 * El orden importa:
 *   1. Los archivos del disco, primero: la cascada de la base se lleva las
 *      filas que dicen dónde está cada uno.
 *   2. Los adjuntos, a mano: son polimórficos y no tienen FK que los arrastre.
 *   3. `forceDelete()` del usuario: los `cascadeOnDelete` hacen el resto
 *      (mascotas y todo lo que cuelga de ellas, el pivote, los catálogos).
 */
class PurgarCuentasEliminadas extends Command
{
    public const DIAS = 30;

    protected $signature = 'huella:purgar-cuentas';

    protected $description = 'Borra definitivamente las cuentas eliminadas hace más de '.self::DIAS.' días, con sus archivos';

    public function handle(): int
    {
        $purgadas = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(self::DIAS))
            ->chunkById(50, function ($usuarios) use (&$purgadas) {
                foreach ($usuarios as $usuario) {
                    $this->purgar($usuario);
                    $purgadas++;
                }
            });

        $this->info("Cuentas purgadas: {$purgadas}");

        return self::SUCCESS;
    }

    private function purgar(User $usuario): void
    {
        $mascotas = Mascota::withTrashed()->where('usuario_id', $usuario->id)->pluck('id');

        foreach ($mascotas as $id) {
            // Fotos, galería, adjuntos de visitas y documentos: todo vive acá.
            Storage::deleteDirectory("mascotas/{$id}");
        }

        foreach (Alimento::withTrashed()->where('usuario_id', $usuario->id)->pluck('id') as $id) {
            Storage::deleteDirectory("catalogos/alimentos/{$id}");
        }

        DB::transaction(function () use ($usuario, $mascotas) {
            $duenios = [
                (new Mascota)->getMorphClass() => $mascotas,
                (new Visita)->getMorphClass() => Visita::withTrashed()->whereIn('mascota_id', $mascotas)->pluck('id'),
                (new Tratamiento)->getMorphClass() => Tratamiento::withTrashed()->whereIn('mascota_id', $mascotas)->pluck('id'),
            ];

            foreach ($duenios as $tipo => $ids) {
                Adjunto::query()
                    ->where('adjuntable_type', $tipo)
                    ->whereIn('adjuntable_id', $ids)
                    ->delete();
            }

            DB::table('sessions')->where('user_id', $usuario->id)->delete();

            $usuario->forceDelete();
        });
    }
}
