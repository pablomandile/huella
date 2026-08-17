<?php

namespace App\Console\Commands;

use App\Enums\EstadoRecordatorio;
use App\Mail\RecordatoriosDelDia;
use App\Models\Recordatorio;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Manda los avisos que ya entraron en su ventana de anticipación.
 *
 * Corre **cada hora**, no una vez al día, y eso es a propósito: la hora de
 * notificación es local de cada usuario, así que un job diario a una hora fija
 * del servidor le llegaría a la mitad de la gente a la hora equivocada. Cada
 * corrida busca a quiénes ya les pasó su hora y todavía no recibieron el aviso.
 *
 * Un solo mail por usuario con todo junto, y el recordatorio pasa a "avisado"
 * —no a "hecho": que llegue el mail no significa que la vacuna se haya dado.
 */
class ProcesarRecordatoriosDiarios extends Command
{
    protected $signature = 'huella:procesar-recordatorios
                            {--usuario= : Procesar solo este usuario, para probar}';

    protected $description = 'Envía los recordatorios cuya ventana de aviso ya se abrió';

    public function handle(): int
    {
        $avisados = 0;
        $usuarios = 0;

        $this->cadaUsuarioConPendientes(function (User $usuario) use (&$avisados, &$usuarios) {
            $pendientes = $this->pendientesDe($usuario);

            if ($pendientes->isEmpty()) {
                return;
            }

            Mail::to($usuario->email)->send(new RecordatoriosDelDia($usuario, $pendientes));

            Recordatorio::whereIn('id', $pendientes->pluck('id'))
                ->update(['estado' => EstadoRecordatorio::Notificado]);

            $usuarios++;
            $avisados += $pendientes->count();
        });

        $this->info("Recordatorios avisados: {$avisados} a {$usuarios} usuarios.");

        return self::SUCCESS;
    }

    /**
     * @param  callable(User): void  $accion
     */
    private function cadaUsuarioConPendientes(callable $accion): void
    {
        // whereIn explícito y no el scope: sobre una relación anidada el scope
        // llega como un builder genérico y no se puede tipar.
        $consulta = User::query()
            ->whereHas(
                'mascotas.recordatorios',
                fn (Builder $c) => $c->whereIn('estado', EstadoRecordatorio::abiertos()),
            );

        if ($this->option('usuario')) {
            $consulta->where('id', $this->option('usuario'));
        }

        $consulta->chunkById(100, function ($usuarios) use ($accion) {
            foreach ($usuarios as $usuario) {
                $accion($usuario);
            }
        });
    }

    /**
     * Lo que hay que avisarle a este usuario ahora mismo.
     *
     * Dos condiciones, las dos en su reloj: que ya sea la hora de notificación
     * de hoy y que la ventana de anticipación se haya abierto.
     *
     * La ventana se filtra en PHP con `desdeCuandoAvisa()` y no en SQL. Es una
     * resta entre dos columnas y cada motor la escribe distinto —los tests
     * corren en SQLite y producción en MySQL—; los pendientes de un usuario son
     * decenas, así que traerlos y filtrarlos sale más barato que sostener dos
     * dialectos de la misma expresión.
     *
     * @return Collection<int, Recordatorio>
     */
    private function pendientesDe(User $usuario): Collection
    {
        $ahora = $usuario->ahora();
        // Calendario y no instante: fecha_objetivo es una columna `date`.
        $hoy = $usuario->hoyCalendario();

        return Recordatorio::query()
            ->whereIn('mascota_id', $usuario->mascotas()->select('mascotas.id'))
            ->where('estado', EstadoRecordatorio::Pendiente)
            ->where('hora_notificacion', '<=', $ahora->format('H:i:s'))
            // Nada de hace más de un año: si nunca se avisó, ya no sirve.
            ->where('fecha_objetivo', '>=', $hoy->subYear()->toDateString())
            ->with('mascota')
            ->orderBy('fecha_objetivo')
            ->get()
            ->filter(fn (Recordatorio $r) => $r->desdeCuandoAvisa()->lessThanOrEqualTo($hoy));
    }
}
