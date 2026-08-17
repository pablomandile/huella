<?php

namespace App\Services;

use App\Models\AplicacionVacuna;
use App\Models\CicloCelo;
use App\Models\Desparasitacion;
use App\Models\Dieta;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\Tratamiento;
use App\Models\Visita;
use App\Support\EventoTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * La línea de tiempo unificada: la pantalla principal del diario.
 *
 * Mezcla ocho fuentes que no se parecen entre sí y las devuelve en una sola
 * lista, de lo más nuevo a lo más viejo.
 *
 * **Por qué no una VIEW SQL con UNION.** Las columnas de las ocho tablas no
 * coinciden ni en nombre ni en tipo, así que una VIEW obligaría a castear todo
 * a texto y perdería los índices; además habría que mantenerla en migraciones
 * cada vez que una fase suma un campo. Acá cada fuente se consulta con su
 * propio modelo —relaciones, casts y scopes incluidos— y la mezcla se hace en
 * PHP sobre un puñado de filas.
 *
 * **Paginado por cursor y no por offset.** Con offset, cargar la página 5 exige
 * contar y descartar las 4 anteriores en las ocho tablas, y si entra un evento
 * nuevo mientras el usuario scrollea, las filas se corren y se saltea o repite
 * alguna. El cursor es `(fecha, clave)` del último evento devuelto.
 */
class TimelineService
{
    /** Cuántos eventos por página. */
    public const POR_PAGINA = 20;

    /**
     * Los tipos que puede traer la línea de tiempo, en el orden en que se
     * ofrecen los filtros.
     *
     * @var list<string>
     */
    public const TIPOS = [
        'visita',
        'vacuna',
        'desparasitacion',
        'tratamiento',
        'peso',
        'dieta',
        'celo',
        'entrada',
    ];

    /**
     * @param  array{tipos?: list<string>, desde?: string|null, hasta?: string|null, busqueda?: string|null}  $filtros
     * @return array{eventos: list<array<string, mixed>>, cursor: string|null, hay_mas: bool}
     */
    public function para(Mascota $mascota, array $filtros = [], ?string $cursor = null): array
    {
        $tipos = $this->tiposPedidos($filtros);
        $posicion = $this->decodificarCursor($cursor);

        // Se pide una fila más que el tamaño de página: si vuelve, hay siguiente.
        $tope = self::POR_PAGINA + 1;

        $eventos = collect();

        foreach ($tipos as $tipo) {
            $eventos = $eventos->concat(
                $this->traerDe($tipo, $mascota, $filtros, $posicion, $tope),
            );
        }

        // El orden tiene que coincidir **exactamente** con el que aplica el
        // cursor: fecha, después tipo, después id numérico. Ordenar por la
        // clave `tipo:id` como texto parece equivalente pero no lo es —"peso:10"
        // queda antes que "peso:9"—, y ese desajuste hace que la página
        // siguiente se saltee eventos.
        $ordenados = $eventos
            ->sortByDesc(fn (EventoTimeline $evento) => [
                $evento->fecha->toDateString(),
                $evento->tipo,
                $evento->id,
            ])
            ->values();

        $hayMas = $ordenados->count() > self::POR_PAGINA;
        $pagina = $ordenados->take(self::POR_PAGINA);
        $ultimo = $pagina->last();

        return [
            'eventos' => array_values(
                $pagina->map(fn (EventoTimeline $e) => $e->paraElFront())->all(),
            ),
            'cursor' => $hayMas && $ultimo instanceof EventoTimeline
                ? $this->codificarCursor($ultimo)
                : null,
            'hay_mas' => $hayMas,
        ];
    }

    /**
     * Cuántos eventos hay en total, por tipo. Alimenta los contadores del filtro.
     *
     * @param  array{tipos?: list<string>, desde?: string|null, hasta?: string|null, busqueda?: string|null}  $filtros
     * @return array<string, int>
     */
    public function totalesPorTipo(Mascota $mascota, array $filtros = []): array
    {
        $totales = [];

        foreach (self::TIPOS as $tipo) {
            // Sin el filtro de tipos: el contador tiene que decir cuántos hay
            // de cada uno, aunque ahora mismo estén ocultos.
            $totales[$tipo] = $this->consulta($tipo, $mascota, $filtros)->count();
        }

        return $totales;
    }

    /**
     * @param  array{tipos?: list<string>, desde?: string|null, hasta?: string|null, busqueda?: string|null}  $filtros
     * @param  array{fecha: string, tipo: string, id: int}|null  $posicion
     * @return Collection<int, EventoTimeline>
     */
    private function traerDe(
        string $tipo,
        Mascota $mascota,
        array $filtros,
        ?array $posicion,
        int $tope,
    ): Collection {
        $consulta = $this->consulta($tipo, $mascota, $filtros);
        $columna = $this->columnaDeFecha($tipo);

        if ($posicion !== null) {
            $this->aplicarCursor($consulta, $tipo, $columna, $posicion);
        }

        return $consulta
            ->orderByDesc($columna)
            ->orderByDesc('id')
            ->take($tope)
            ->get()
            ->map(fn ($registro) => $this->aEvento($tipo, $registro, $mascota));
    }

    /**
     * El cursor recorta cada fuente por separado.
     *
     * Dentro de una fuente el tipo es constante, así que la comparación
     * `(fecha, tipo:id) < (cursorFecha, cursorClave)` se puede resolver
     * comparando el tipo una sola vez, en PHP, en vez de armar la clave en SQL
     * —que cada motor concatena distinto—.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     * @param  array{fecha: string, tipo: string, id: int}  $posicion
     */
    private function aplicarCursor(
        Builder $consulta,
        string $tipo,
        string $columna,
        array $posicion,
    ): void {
        $fecha = $posicion['fecha'];

        if ($tipo === $posicion['tipo']) {
            // Misma fuente: se sigue por id dentro del mismo día.
            $consulta->where(
                fn (Builder $c) => $c
                    ->whereDate($columna, '<', $fecha)
                    ->orWhere(
                        fn (Builder $mismoDia) => $mismoDia
                            ->whereDate($columna, '=', $fecha)
                            ->where('id', '<', $posicion['id']),
                    ),
            );

            return;
        }

        // Otra fuente: su clave del mismo día es menor o mayor según el nombre
        // del tipo, que es lo que ordena el desempate.
        $consulta->whereDate(
            $columna,
            $tipo < $posicion['tipo'] ? '<=' : '<',
            $fecha,
        );
    }

    /**
     * @param  array{tipos?: list<string>, desde?: string|null, hasta?: string|null, busqueda?: string|null}  $filtros
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function consulta(string $tipo, Mascota $mascota, array $filtros): Builder
    {
        $columna = $this->columnaDeFecha($tipo);
        $consulta = $this->consultaBase($tipo, $mascota);

        if (! empty($filtros['desde'])) {
            $consulta->whereDate($columna, '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $consulta->whereDate($columna, '<=', $filtros['hasta']);
        }

        if (! empty($filtros['busqueda'])) {
            $this->buscar($consulta, $tipo, (string) $filtros['busqueda']);
        }

        return $consulta;
    }

    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function consultaBase(string $tipo, Mascota $mascota): Builder
    {
        $suyos = fn (string $modelo) => $modelo::query()->where('mascota_id', $mascota->id);

        return match ($tipo) {
            'visita' => Visita::query()
                ->where('mascota_id', $mascota->id)
                ->with(['veterinaria', 'tratamientos', 'adjuntos']),
            'vacuna' => AplicacionVacuna::query()
                ->where('mascota_id', $mascota->id)
                ->with('vacuna'),
            'desparasitacion' => Desparasitacion::query()
                ->where('mascota_id', $mascota->id)
                ->with('medicamento'),
            // Solo los que no salieron de una visita: los demás ya se ven ahí, y
            // duplicarlos llenaría el diario de ruido.
            'tratamiento' => Tratamiento::query()
                ->where('mascota_id', $mascota->id)
                ->whereNull('visita_id')
                ->with('medicamento'),
            'peso' => $suyos(RegistroPeso::class),
            'dieta' => Dieta::query()
                ->where('mascota_id', $mascota->id)
                ->with('alimento'),
            'celo' => $suyos(CicloCelo::class),
            'entrada' => $suyos(EntradaDiario::class),
            default => throw new InvalidArgumentException("Fuente sin consulta: {$tipo}"),
        };
    }

    /**
     * Búsqueda por texto: motivos, diagnósticos, notas y los nombres libres.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     */
    private function buscar(Builder $consulta, string $tipo, string $termino): void
    {
        $como = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';

        $campos = match ($tipo) {
            'visita' => ['motivo', 'diagnostico', 'indicaciones', 'notas'],
            'vacuna' => ['vacuna_libre', 'marca', 'reacciones', 'notas'],
            'desparasitacion' => ['medicamento_libre', 'dosis', 'notas'],
            'tratamiento' => ['medicamento_libre', 'dosis', 'notas'],
            'peso' => ['notas'],
            'dieta' => ['motivo', 'notas'],
            'celo' => ['sintomas', 'notas'],
            'entrada' => ['titulo', 'contenido'],
            default => throw new InvalidArgumentException("Fuente sin búsqueda: {$tipo}"),
        };

        $consulta->where(function (Builder $agrupada) use ($campos, $como) {
            foreach ($campos as $campo) {
                $agrupada->orWhere($campo, 'like', $como);
            }
        });
    }

    private function columnaDeFecha(string $tipo): string
    {
        return match ($tipo) {
            'visita' => 'fecha_hora',
            'tratamiento' => 'fecha_inicio',
            'dieta' => 'fecha_inicio',
            'celo' => 'fecha_inicio',
            default => 'fecha',
        };
    }

    /**
     * Traduce cada registro a la forma común del timeline.
     */
    private function aEvento(string $tipo, mixed $registro, Mascota $mascota): EventoTimeline
    {
        return match ($tipo) {
            'visita' => new EventoTimeline(
                tipo: 'visita',
                id: $registro->id,
                fecha: $registro->fecha_hora->toImmutable(),
                titulo: $registro->motivo ?? $registro->tipo->etiqueta(),
                detalle: $registro->diagnostico,
                url: route('mascotas.visitas.show', [$mascota, $registro]),
                datos: [
                    'etiqueta_tipo' => $registro->tipo->etiqueta(),
                    'veterinaria' => $registro->veterinaria?->nombre,
                    'medicamentos' => $registro->tratamientos->count(),
                    'adjuntos' => $registro->adjuntos->count(),
                ],
            ),
            'vacuna' => new EventoTimeline(
                tipo: 'vacuna',
                id: $registro->id,
                fecha: $registro->fecha->toImmutable(),
                titulo: $registro->nombre_vacuna,
                detalle: $registro->dosis_nro !== null
                    ? "{$registro->dosis_nro}ª dosis"
                    : null,
                url: route('mascotas.preventivo.index', $mascota),
                datos: ['proxima_dosis' => $registro->proxima_dosis?->toDateString()],
            ),
            'desparasitacion' => new EventoTimeline(
                tipo: 'desparasitacion',
                id: $registro->id,
                fecha: $registro->fecha->toImmutable(),
                titulo: $registro->nombre_medicamento,
                detalle: $registro->tipo->etiqueta(),
                url: route('mascotas.preventivo.index', $mascota),
            ),
            'tratamiento' => new EventoTimeline(
                tipo: 'tratamiento',
                id: $registro->id,
                fecha: $registro->fecha_inicio->toImmutable(),
                titulo: $registro->nombre_medicamento,
                detalle: $registro->dosis,
                url: route('mascotas.seguimiento.index', $mascota),
            ),
            'peso' => new EventoTimeline(
                tipo: 'peso',
                id: $registro->id,
                fecha: $registro->fecha->toImmutable(),
                titulo: $this->kilosLegibles($registro->kilos()),
                detalle: $registro->origen->etiqueta(),
                url: route('mascotas.seguimiento.index', $mascota),
            ),
            'dieta' => new EventoTimeline(
                tipo: 'dieta',
                id: $registro->id,
                fecha: $registro->fecha_inicio->toImmutable(),
                titulo: trim(sprintf(
                    '%s %s',
                    $registro->alimento->marca ?? '',
                    $registro->alimento->nombre,
                )),
                detalle: $registro->motivo,
                url: route('mascotas.seguimiento.index', $mascota),
                datos: ['vigente' => $registro->estaVigente()],
            ),
            'celo' => new EventoTimeline(
                tipo: 'celo',
                id: $registro->id,
                fecha: $registro->fecha_inicio->toImmutable(),
                titulo: 'Celo',
                detalle: $registro->duracion_dias !== null
                    ? "Duró {$registro->duracion_dias} días"
                    : 'En curso',
                url: route('mascotas.seguimiento.index', $mascota),
            ),
            'entrada' => new EventoTimeline(
                tipo: 'entrada',
                id: $registro->id,
                fecha: $registro->fecha->toImmutable(),
                titulo: $registro->encabezado(),
                detalle: $registro->titulo !== null ? $registro->contenido : null,
                url: null,
                datos: [
                    'categoria' => $registro->categoria->value,
                    'categoria_etiqueta' => $registro->categoria->etiqueta(),
                    'animo_etiqueta' => $registro->animo?->etiqueta(),
                    'contenido' => $registro->contenido,
                ],
            ),
            default => throw new InvalidArgumentException("Fuente sin mapeo: {$tipo}"),
        };
    }

    private function kilosLegibles(float $kilos): string
    {
        $decimales = fmod($kilos, 1.0) === 0.0 ? 0 : 1;

        return number_format($kilos, $decimales, ',', '.').' kg';
    }

    /**
     * @param  array{tipos?: list<string>, desde?: string|null, hasta?: string|null, busqueda?: string|null}  $filtros
     * @return list<string>
     */
    private function tiposPedidos(array $filtros): array
    {
        $pedidos = $filtros['tipos'] ?? [];

        if ($pedidos === []) {
            return self::TIPOS;
        }

        return array_values(array_intersect(self::TIPOS, $pedidos));
    }

    private function codificarCursor(EventoTimeline $evento): string
    {
        return base64_encode(sprintf(
            '%s|%s|%d',
            $evento->fecha->toDateString(),
            $evento->tipo,
            $evento->id,
        ));
    }

    /**
     * @return array{fecha: string, tipo: string, id: int}|null
     */
    private function decodificarCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $partes = explode('|', (string) base64_decode($cursor, true), 3);

        if (count($partes) !== 3) {
            return null; // cursor manoseado: se arranca de cero
        }

        [$fecha, $tipo, $id] = $partes;

        if (! in_array($tipo, self::TIPOS, strict: true)) {
            return null;
        }

        try {
            CarbonImmutable::parse($fecha);
        } catch (\Throwable) {
            return null;
        }

        return ['fecha' => $fecha, 'tipo' => $tipo, 'id' => (int) $id];
    }
}
