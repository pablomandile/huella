<?php

namespace App\Services;

use App\Enums\EstadoToma;
use App\Models\Tratamiento;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Convierte la posología de un tratamiento en tomas concretas.
 *
 * "1 comprimido cada 8 horas por 7 días" se vuelve 21 filas con fecha y hora,
 * que es lo que después se marca de un tap en "Medicación de hoy" y lo que
 * permite ver si el tratamiento se cumplió.
 */
class GeneradorTomasService
{
    /**
     * Tope de generación.
     *
     * Un tratamiento crónico sin fecha de fin generaría filas para siempre. Se
     * generan 90 días por vez y se extiende cuando haga falta: alcanza de sobra
     * para cualquier tratamiento agudo, que es el 99% de los casos.
     */
    public const DIAS_MAXIMOS = 90;

    /** Si no dijo a qué hora, las 8 de la mañana. */
    private const HORA_POR_DEFECTO = '08:00';

    /**
     * Cronograma completo desde el primer día, incluso si es retroactivo:
     * quien carga el lunes un tratamiento que empezó el sábado quiere ver
     * —y poder marcar— las tomas del fin de semana.
     *
     * @return int cuántas tomas quedaron creadas
     */
    public function generar(Tratamiento $tratamiento): int
    {
        return $this->crear($tratamiento, desde: null);
    }

    /**
     * Rearma el cronograma tras editar la posología.
     *
     * Borra **solo las tomas futuras que siguen pendientes**. Lo que ya se
     * administró u omitió es historia clínica y no se toca; y una pendiente
     * vencida tampoco, porque es una deuda real ("no le di la de ayer") y
     * borrarla sería falsear la adherencia.
     *
     * @return int cuántas tomas nuevas se crearon
     */
    public function regenerar(Tratamiento $tratamiento): int
    {
        $ahora = CarbonImmutable::now('UTC');

        $tratamiento->tomas()
            ->where('estado', EstadoToma::Pendiente)
            ->where('fecha_hora_programada', '>', $ahora)
            ->delete();

        if (! $tratamiento->estado->estaEnCurso()) {
            return 0; // suspendido o terminado: no se le programan tomas nuevas
        }

        return $this->crear($tratamiento, desde: $ahora);
    }

    /**
     * @param  CarbonImmutable|null  $desde  instante mínimo; null = desde el inicio del tratamiento
     */
    private function crear(Tratamiento $tratamiento, ?CarbonImmutable $desde): int
    {
        $intervalo = $tratamiento->intervaloHoras();

        // Sin frecuencia no hay cronograma que armar: es un "dárselo si le
        // duele". Se registra el tratamiento y nada más.
        if ($intervalo === null) {
            return 0;
        }

        $usuario = $this->duenio($tratamiento);
        $momento = $this->primeraToma($tratamiento, $usuario);
        $limite = $this->limite($tratamiento, $usuario, $momento);

        // Instantes que ya existen (administrados, omitidos o vencidos): no se
        // duplican al regenerar.
        $existentes = $tratamiento->tomas()
            ->pluck('fecha_hora_programada')
            ->map(fn ($fecha) => $fecha->format('Y-m-d H:i:00'))
            ->all();

        $filas = [];
        $ahora = CarbonImmutable::now('UTC');

        while ($momento->lessThanOrEqualTo($limite)) {
            $clave = $momento->format('Y-m-d H:i:00');

            $sirve = ($desde === null || $momento->greaterThan($desde))
                && ! in_array($clave, $existentes, strict: true);

            if ($sirve) {
                $filas[] = [
                    'tratamiento_id' => $tratamiento->id,
                    'fecha_hora_programada' => $momento->format('Y-m-d H:i:s'),
                    'estado' => EstadoToma::Pendiente->value,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $existentes[] = $clave;
            }

            $momento = $momento->addHours($intervalo);
        }

        if ($filas !== []) {
            $tratamiento->tomas()->insert($filas);
        }

        return count($filas);
    }

    /**
     * Primera toma, en UTC.
     *
     * `fecha_inicio` y `hora_primera_toma` están en el reloj del usuario: si
     * anotó "a las 8", la toma tiene que caer a las 8 de su mañana y no a las 8
     * del servidor.
     */
    private function primeraToma(Tratamiento $tratamiento, User $usuario): CarbonImmutable
    {
        $hora = substr($tratamiento->hora_primera_toma ?? self::HORA_POR_DEFECTO, 0, 5);
        $dia = $tratamiento->fecha_inicio->format('Y-m-d');

        return $usuario->aUtc("{$dia} {$hora}") ?? CarbonImmutable::now('UTC');
    }

    /**
     * Último instante para el que se generan tomas, en UTC.
     *
     * Acá hay una decisión que importa. "Cada 8 horas por 7 días" son **21
     * dosis**: es lo que trae la caja y lo que el veterinario indicó. Si el
     * límite fuera el fin del séptimo día de calendario saldrían 20, porque el
     * cronograma arranca a las 8 de la mañana y no a medianoche — y faltaría
     * una dosis del tratamiento.
     *
     * Por eso `duracion_dias` cuenta **dosis** (días × 24 horas desde la
     * primera toma), no días de almanaque. La contra es que la última puede
     * caer a la medianoche del día siguiente, que es exactamente lo que pasa
     * en la vida cuando se toma a las 8, a las 16 y a las 24.
     *
     * Cuando en cambio hay una `fecha_fin` explícita, manda el calendario: ahí
     * el veterinario habló de un día concreto.
     */
    private function limite(
        Tratamiento $tratamiento,
        User $usuario,
        CarbonImmutable $primera,
    ): CarbonImmutable {
        $tope = $primera->addDays(self::DIAS_MAXIMOS)->subSecond();

        if ($tratamiento->fecha_fin !== null) {
            $fin = $usuario->aUtc($tratamiento->fecha_fin->format('Y-m-d').' 23:59')
                ?? $tope;

            return $fin->lessThan($tope) ? $fin : $tope;
        }

        if ($tratamiento->duracion_dias !== null && $tratamiento->duracion_dias > 0) {
            $fin = $primera->addDays($tratamiento->duracion_dias)->subSecond();

            return $fin->lessThan($tope) ? $fin : $tope;
        }

        return $tope; // tratamiento abierto: manda el tope
    }

    /**
     * La zona horaria sale del propietario de la mascota, no de quien edita.
     * Si en v2 un cuidador en otro país corrige la dosis, las horas de las
     * tomas no tienen que moverse.
     */
    private function duenio(Tratamiento $tratamiento): User
    {
        return $tratamiento->mascota->propietario;
    }
}
