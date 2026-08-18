<?php

namespace App\Http\Controllers;

use App\Enums\EstadoToma;
use App\Enums\OrigenPeso;
use App\Http\Resources\DietaResource;
use App\Http\Resources\MascotaResource;
use App\Http\Resources\RecordatorioResource;
use App\Http\Resources\RegistroPesoResource;
use App\Http\Resources\VisitaResource;
use App\Models\Mascota;
use App\Models\Recordatorio;
use App\Models\TomaMedicamento;
use App\Models\User;
use App\Services\EstadoVacunacionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El dashboard completo de la especificación §5.
 *
 * El orden de los bloques no es decorativo: primero lo que hay que hacer hoy
 * (medicación y recordatorios), después el estado (peso, dieta, vacunación) y
 * al final el historial. Quien abre la app a la mañana quiere saber qué le toca,
 * no cuánto pesa.
 */
class DashboardController extends Controller
{
    /** Los recordatorios que se muestran: los próximos 30 días. */
    private const DIAS_DE_AGENDA = 30;

    public function __construct(private readonly EstadoVacunacionService $vacunacion) {}

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        // Acá sí van todas, compartidas incluidas: el selector de mascota activa
        // es para mirar. Lo que hay que *hacer* —tomas y recordatorios, más
        // abajo— sale de `mascotasACargo`.
        $mascotas = $usuario->mascotas()->orderBy('nombre')->get();

        $activa = $mascotas->firstWhere('id', (int) session('mascota_activa_id'))
            ?? $mascotas->first();

        // Solo las de la activa, que es la única cuyo estado se muestra: cargarlas
        // en el `get()` de arriba costaría una query por relación por cada mascota
        // de la casa para tirar todas menos una.
        $activa?->load(['ultimoPeso', 'dietaVigente.alimento']);

        return Inertia::render('Dashboard', [
            'mascotaActiva' => $activa ? MascotaResource::make($activa)->resolve() : null,
            'totalMascotas' => $mascotas->count(),

            // Lo que hay que hacer hoy, de **todas** las mascotas: a la mañana
            // se reparten los remedios de todos los que viven en la casa.
            'tomasDeHoy' => $this->tomasDeHoy($usuario),
            'recordatorios' => $this->recordatorios($usuario),

            // El estado de la mascota activa.
            'ultimoPeso' => $activa?->ultimoPeso
                ? RegistroPesoResource::make($activa->ultimoPeso)->resolve()
                : null,
            'variacionPeso' => $this->variacionPeso($activa),
            'dietaVigente' => $this->dietaVigente($activa),
            'estadoVacunacion' => $activa ? $this->vacunacion->para($activa) : null,
            'ultimaVisita' => $this->ultimaVisita($activa, $request),

            'origenesPeso' => OrigenPeso::opciones(),
            'hoy' => $usuario->hoy()->toDateString(),
            'puedeRegistrar' => $activa !== null
                && $usuario->can('registrarEventos', $activa),
        ]);
    }

    /**
     * Las tomas pendientes de hoy, con la deuda de días anteriores.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tomasDeHoy(User $usuario): array
    {
        $inicioDeHoy = $usuario->hoy();
        $finDeHoy = $inicioDeHoy->addDay()->subSecond();

        return TomaMedicamento::query()
            ->whereHas(
                'tratamiento',
                fn ($consulta) => $consulta->whereIn(
                    'mascota_id',
                    $usuario->mascotasACargo()->select('mascotas.id'),
                ),
            )
            ->where('estado', EstadoToma::Pendiente)
            ->where('fecha_hora_programada', '<=', $finDeHoy->utc())
            // Una semana de deuda: más atrás ya no le sirve a nadie.
            ->where('fecha_hora_programada', '>=', $inicioDeHoy->subDays(7)->utc())
            ->with(['tratamiento.mascota', 'tratamiento.medicamento'])
            ->orderBy('fecha_hora_programada')
            ->take(6)
            ->get()
            ->map(fn (TomaMedicamento $toma) => [
                'id' => $toma->id,
                'hora' => $usuario->enSuZona($toma->fecha_hora_programada)?->format('H:i'),
                'atrasada' => $toma->fecha_hora_programada->lessThan($inicioDeHoy->utc()),
                // Con varias tomas atrasadas, la hora sola no alcanza: se leen
                // como si fueran de hoy.
                'dia' => $toma->fecha_hora_programada->lessThan($inicioDeHoy->utc())
                    ? $usuario->enSuZona($toma->fecha_hora_programada)?->translatedFormat('D j/n')
                    : null,
                'medicamento' => $toma->tratamiento->nombre_medicamento,
                'dosis' => $toma->tratamiento->dosis,
                'mascota' => $toma->tratamiento->mascota->nombre,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recordatorios(User $usuario): array
    {
        $hasta = $usuario->hoyCalendario()->addDays(self::DIAS_DE_AGENDA);

        $recordatorios = Recordatorio::query()
            ->whereIn('mascota_id', $usuario->mascotasACargo()->select('mascotas.id'))
            ->abiertos()
            ->whereDate('fecha_objetivo', '<=', $hasta->toDateString())
            ->with('mascota')
            ->orderBy('fecha_objetivo')
            ->take(6)
            ->get();

        return RecordatorioResource::collection($recordatorios)->resolve();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dietaVigente(?Mascota $mascota): ?array
    {
        // La relación y su alimento ya vienen cargados desde index().
        $dieta = $mascota?->dietaVigente;

        return $dieta !== null
            ? DietaResource::make($dieta)->resolve()
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ultimaVisita(?Mascota $mascota, Request $request): ?array
    {
        $visita = $mascota?->visitas()->with('veterinaria')->first();

        return $visita !== null
            ? VisitaResource::make($visita)->toArray($request)
            : null;
    }

    /**
     * Cuánto cambió el peso desde la medición anterior.
     *
     * @return array{kilos: float, texto: string, sube: bool}|null
     */
    private function variacionPeso(?Mascota $mascota): ?array
    {
        if ($mascota === null) {
            return null;
        }

        $pesos = $mascota->pesos()->reorder('fecha', 'desc')->take(2)->get();

        if ($pesos->count() < 2) {
            return null;
        }

        $diferencia = round($pesos[0]->kilos() - $pesos[1]->kilos(), 2);

        if ($diferencia === 0.0) {
            return ['kilos' => 0.0, 'texto' => 'Igual que antes', 'sube' => false];
        }

        return [
            'kilos' => $diferencia,
            'texto' => sprintf(
                '%s %s',
                $diferencia > 0 ? '+' : '−',
                number_format(abs($diferencia), 2, ',', '.').' kg',
            ),
            'sube' => $diferencia > 0,
        ];
    }
}
