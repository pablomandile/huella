<?php

namespace App\Services;

use App\Enums\EstadoRecordatorio;
use App\Enums\TipoRecordatorio;
use App\Models\Mascota;
use App\Models\Recordatorio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Crea y mantiene los recordatorios que nacen de otro registro.
 *
 * Lo llaman **los observers**, nunca un controlador: así da igual por dónde se
 * cargó la vacuna —el formulario, un seeder, un import futuro—, el recordatorio
 * aparece igual.
 *
 * La idempotencia es por `origen_type` + `origen_id` + `tipo`. Guardar tres
 * veces la misma aplicación de vacuna deja un solo recordatorio, con la fecha
 * actualizada.
 */
class GeneradorRecordatoriosService
{
    /**
     * Sincroniza el recordatorio de un origen con su fecha.
     *
     * Si la fecha es null —el usuario borró la próxima dosis— el recordatorio
     * abierto se descarta: ya no hay nada que recordar.
     */
    public function sincronizar(
        Model $origen,
        Mascota $mascota,
        TipoRecordatorio $tipo,
        ?CarbonInterface $fecha,
        string $titulo,
        ?string $descripcion = null,
    ): ?Recordatorio {
        $existente = $this->buscar($origen, $tipo);

        if ($fecha === null) {
            $this->descartar($existente);

            return null;
        }

        // Una mascota fallecida conserva su historial pero no espera nada más
        // (regla de negocio 3).
        if ($mascota->fallecida) {
            $this->descartar($existente);

            return null;
        }

        if ($existente === null) {
            return $this->crear($origen, $mascota, $tipo, $fecha, $titulo, $descripcion);
        }

        // El usuario ya lo resolvió: su decisión manda sobre la regeneración.
        // Si no, un "ya se lo di" volvería a aparecer con cada guardado.
        if ($existente->estado->loResolvioElUsuario()) {
            return $existente;
        }

        $cambioLaFecha = ! $existente->fecha_objetivo->isSameDay($fecha);

        $existente->fill([
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'fecha_objetivo' => $fecha->toDateString(),
        ]);

        // Si la fecha se movió hay que volver a avisar, aunque ya se hubiera
        // mandado el mail de la anterior.
        if ($cambioLaFecha) {
            $existente->estado = EstadoRecordatorio::Pendiente;
        }

        $existente->save();

        return $existente;
    }

    /**
     * Descarta los recordatorios abiertos de un origen que se dio de baja.
     */
    public function descartarDe(Model $origen, ?TipoRecordatorio $tipo = null): int
    {
        return $this->consultaDe($origen, $tipo)
            ->abiertos()
            ->update(['estado' => EstadoRecordatorio::Descartado]);
    }

    /**
     * Descarta los recordatorios abiertos de una mascota.
     *
     * Con `$tipo` sirve para la regla 2 (al castrar se van los de celo) y sin
     * él para la 3 (al fallecer se van todos).
     */
    public function descartarDeLaMascota(Mascota $mascota, ?TipoRecordatorio $tipo = null): int
    {
        $consulta = Recordatorio::query()
            ->where('mascota_id', $mascota->id)
            ->abiertos();

        if ($tipo !== null) {
            $consulta->where('tipo', $tipo);
        }

        return $consulta->update(['estado' => EstadoRecordatorio::Descartado]);
    }

    /**
     * Deja un solo recordatorio abierto de este tipo para la mascota: el de
     * este origen. Los que colgaban de otro se descartan.
     *
     * Lo necesita el celo: la estimación es **una por mascota**, no una por
     * ciclo, y cada ciclo nuevo cambia el origen del que se calcula. Sin esto,
     * cada celo cargado dejaría su propio aviso abierto y el usuario recibiría
     * la misma cosa varias veces.
     */
    public function dejarSoloEste(
        Mascota $mascota,
        TipoRecordatorio $tipo,
        Model $origen,
    ): int {
        return Recordatorio::query()
            ->where('mascota_id', $mascota->id)
            ->where('tipo', $tipo)
            ->abiertos()
            ->where(fn ($consulta) => $consulta
                ->where('origen_type', '!=', $origen->getMorphClass())
                ->orWhere('origen_id', '!=', $origen->getKey())
                ->orWhereNull('origen_id'),
            )
            ->update(['estado' => EstadoRecordatorio::Descartado]);
    }

    private function crear(
        Model $origen,
        Mascota $mascota,
        TipoRecordatorio $tipo,
        CarbonInterface $fecha,
        string $titulo,
        ?string $descripcion,
    ): Recordatorio {
        $recordatorio = new Recordatorio([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'fecha_objetivo' => $fecha->toDateString(),
            'dias_anticipacion' => $this->anticipacionPara($tipo, $mascota),
        ]);

        $recordatorio->mascota_id = $mascota->id;
        $recordatorio->origen_type = $origen->getMorphClass();
        $recordatorio->origen_id = $origen->getKey();
        $recordatorio->save();

        return $recordatorio;
    }

    /**
     * Los días de anticipación salen del tipo, salvo el celo: ahí manda la
     * preferencia del usuario (`users.dias_anticipacion_celo`).
     */
    private function anticipacionPara(TipoRecordatorio $tipo, Mascota $mascota): int
    {
        if ($tipo === TipoRecordatorio::Celo) {
            return $mascota->propietario->dias_anticipacion_celo;
        }

        return $tipo->diasDeAnticipacion();
    }

    private function buscar(Model $origen, TipoRecordatorio $tipo): ?Recordatorio
    {
        return $this->consultaDe($origen, $tipo)->first();
    }

    /**
     * @return Builder<Recordatorio>
     */
    private function consultaDe(Model $origen, ?TipoRecordatorio $tipo)
    {
        $consulta = Recordatorio::query()
            ->where('origen_type', $origen->getMorphClass())
            ->where('origen_id', $origen->getKey());

        if ($tipo !== null) {
            $consulta->where('tipo', $tipo);
        }

        return $consulta;
    }

    private function descartar(?Recordatorio $recordatorio): void
    {
        if ($recordatorio !== null && $recordatorio->estado->estaAbierto()) {
            $recordatorio->update(['estado' => EstadoRecordatorio::Descartado]);
        }
    }
}
