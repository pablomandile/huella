<?php

namespace App\Services;

use App\Models\CicloCelo;
use App\Models\Mascota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Estima cuándo viene el próximo celo.
 *
 * El método es simple a propósito: **el promedio de los intervalos entre los
 * ciclos que el usuario cargó**. No hay modelo por raza ni por tamaño, porque
 * eso sería predecir y el sistema registra.
 *
 * Con menos de dos ciclos no hay intervalo que promediar y se cae al valor de
 * referencia de 180 días para caninos. Y eso **se dice**: la estimación viaja
 * siempre con su nivel de confianza, para que nadie tome una fecha inventada
 * por un dato.
 */
class EstimadorCeloService
{
    /** Referencia habitual en caninos cuando todavía no hay historia propia. */
    public const DIAS_POR_DEFECTO = 180;

    /** Con menos de esto, la estimación es de baja confianza. */
    private const CICLOS_PARA_CONFIAR = 3;

    /**
     * @return array{
     *     fecha: CarbonImmutable|null,
     *     dias_promedio: int,
     *     confianza: string,
     *     confianza_etiqueta: string,
     *     detalle: string,
     *     intervalos: list<int>,
     *     usa_promedio_real: bool,
     *     vencida: bool
     * }
     */
    public function para(Mascota $mascota): array
    {
        // reorder y no orderBy: la relación viene ordenada descendente para el
        // historial, y un orderBy encima solo agregaría un criterio secundario.
        // Con el orden invertido los intervalos salen negativos.
        $ciclos = $mascota->ciclosCelo()
            ->reorder('fecha_inicio')
            ->get(['id', 'fecha_inicio']);

        $intervalos = $this->intervalos($ciclos);
        $usaPromedioReal = $intervalos !== [];
        $dias = $usaPromedioReal
            ? (int) round(array_sum($intervalos) / count($intervalos))
            : self::DIAS_POR_DEFECTO;

        $ultimo = $ciclos->last();
        $fecha = $ultimo?->fecha_inicio->toImmutable()->addDays($dias);

        [$confianza, $etiqueta] = $this->confianza($ciclos->count());

        // La fecha estimada puede haber quedado atrás: pasa cuando el celo
        // ocurrió y no se cargó. Presentarla como "el próximo" sería decir algo
        // falso, así que se marca y el texto lo dice.
        $vencida = $fecha !== null
            && $fecha->lessThan($mascota->propietario->hoyCalendario());

        return [
            'fecha' => $fecha,
            'dias_promedio' => $dias,
            'confianza' => $confianza,
            'confianza_etiqueta' => $etiqueta,
            'detalle' => $this->detalle($ciclos->count(), $intervalos, $dias, $vencida),
            'intervalos' => $intervalos,
            'usa_promedio_real' => $usaPromedioReal,
            'vencida' => $vencida,
        ];
    }

    /**
     * Días entre el inicio de cada ciclo y el siguiente.
     *
     * Con N ciclos hay N-1 intervalos: uno solo no dice nada sobre cada cuánto
     * le viene.
     *
     * @param  Collection<int, CicloCelo>  $ciclos
     * @return list<int>
     */
    private function intervalos(Collection $ciclos): array
    {
        $intervalos = [];
        $anterior = null;

        foreach ($ciclos as $ciclo) {
            if ($anterior !== null) {
                $intervalos[] = (int) $anterior->diffInDays($ciclo->fecha_inicio);
            }

            $anterior = $ciclo->fecha_inicio;
        }

        return $intervalos;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function confianza(int $cantidadDeCiclos): array
    {
        return match (true) {
            $cantidadDeCiclos >= self::CICLOS_PARA_CONFIAR => ['media', 'Estimación sobre su historia'],
            $cantidadDeCiclos === 2 => ['baja', 'Estimación con pocos datos'],
            default => ['muy_baja', 'Valor de referencia, no de ella'],
        };
    }

    /**
     * @param  list<int>  $intervalos
     */
    private function detalle(
        int $cantidadDeCiclos,
        array $intervalos,
        int $dias,
        bool $vencida = false,
    ): string {
        $aviso = $vencida
            ? ' La fecha estimada ya pasó: si tuvo el celo, cargalo para que la '
                .'próxima estimación salga bien.'
            : '';

        if ($cantidadDeCiclos === 0) {
            return 'Todavía no cargaste ningún ciclo. Cuando registres dos, la estimación '
                .'empieza a usar sus propios intervalos.';
        }

        if ($intervalos === []) {
            return sprintf(
                'Con un solo ciclo cargado no hay intervalo que promediar, así que se usan '
                .'%d días, que es la referencia habitual en perros. Con el próximo ciclo '
                .'la estimación pasa a ser sobre ella.%s',
                self::DIAS_POR_DEFECTO,
                $aviso,
            );
        }

        return sprintf(
            'Promedio de %s %s entre sus ciclos: %s días. Estimación: cada %d días.%s',
            count($intervalos),
            count($intervalos) === 1 ? 'intervalo' : 'intervalos',
            implode(', ', $intervalos),
            $dias,
            $aviso,
        );
    }
}
