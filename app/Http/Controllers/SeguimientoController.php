<?php

namespace App\Http\Controllers;

use App\Enums\IntensidadCelo;
use App\Enums\OrigenPeso;
use App\Http\Requests\GuardarCicloCeloRequest;
use App\Http\Requests\GuardarDietaRequest;
use App\Http\Requests\GuardarPesoRequest;
use App\Http\Resources\AlimentoResource;
use App\Http\Resources\CicloCeloResource;
use App\Http\Resources\DietaResource;
use App\Http\Resources\MascotaResource;
use App\Http\Resources\RegistroPesoResource;
use App\Http\Resources\VeterinarioResource;
use App\Models\Alimento;
use App\Models\CicloCelo;
use App\Models\Dieta;
use App\Models\Mascota;
use App\Models\RegistroPeso;
use App\Models\Veterinario;
use App\Services\DietaService;
use App\Services\EstimadorCeloService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Peso, dieta y celo: lo que cambia con el tiempo.
 *
 * Van juntos porque son las tres cosas que se leen en tendencia y no en un
 * momento: cuánto pesa comparado con antes, qué viene comiendo, cada cuánto le
 * viene el celo.
 */
class SeguimientoController extends Controller
{
    public function __construct(
        private readonly DietaService $dietas,
        private readonly EstimadorCeloService $estimador,
    ) {}

    public function index(Request $request, Mascota $mascota): Response
    {
        Gate::authorize('view', $mascota);

        $usuario = $request->user();

        return Inertia::render('seguimiento/Index', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            'pesos' => RegistroPesoResource::collection($mascota->pesos)->resolve(),
            'variacion' => $this->variacion($mascota),
            'dietas' => DietaResource::collection(
                $mascota->dietas()->with(['alimento', 'veterinario'])->get(),
            )->resolve(),
            // El módulo de celo solo existe para hembras no castradas y vivas.
            'celoVisible' => $mascota->celo_visible,
            'ciclos' => $mascota->celo_visible
                ? CicloCeloResource::collection($mascota->ciclosCelo)->resolve()
                : [],
            'estimacionCelo' => $mascota->celo_visible
                ? $this->estimacionParaElFront($mascota)
                : null,
            'puedeRegistrar' => $usuario->can('registrarEventos', $mascota),

            'alimentos' => AlimentoResource::collection(
                Alimento::disponiblesPara($usuario)->orderBy('marca')->orderBy('nombre')->get(),
            )->resolve(),
            'veterinarios' => VeterinarioResource::collection(
                Veterinario::disponiblesPara($usuario)->with('veterinaria')->orderBy('nombre')->get(),
            )->resolve(),
            'origenesPeso' => OrigenPeso::opciones(),
            'intensidades' => IntensidadCelo::opciones(),
            'hoy' => $usuario->hoy()->toDateString(),
        ]);
    }

    /* ------------------------------------------------------------------ peso */

    public function guardarPeso(GuardarPesoRequest $request, Mascota $mascota): RedirectResponse
    {
        $mascota->pesos()->create($request->validated());

        return back()->with('success', 'Peso registrado.');
    }

    public function actualizarPeso(
        GuardarPesoRequest $request,
        Mascota $mascota,
        RegistroPeso $peso,
    ): RedirectResponse {
        abort_unless($peso->mascota_id === $mascota->id, 404);

        $peso->update($request->validated());

        return back()->with('success', 'Peso actualizado.');
    }

    public function eliminarPeso(Mascota $mascota, RegistroPeso $peso): RedirectResponse
    {
        Gate::authorize('delete', $peso);
        abort_unless($peso->mascota_id === $mascota->id, 404);

        // Sin soft delete: un peso mal cargado deforma la curva, y lo que se
        // quiere es que desaparezca.
        $peso->delete();

        return back()->with('success', 'Peso eliminado.');
    }

    /* ----------------------------------------------------------------- dieta */

    public function guardarDieta(GuardarDietaRequest $request, Mascota $mascota): RedirectResponse
    {
        // El cierre de la dieta anterior, en transacción, lo hace el servicio
        // (regla de negocio 1).
        $this->dietas->iniciar($mascota, $request->validated());

        return back()->with('success', 'Dieta actualizada. La anterior se cerró sola.');
    }

    public function actualizarDieta(
        GuardarDietaRequest $request,
        Mascota $mascota,
        Dieta $dieta,
    ): RedirectResponse {
        abort_unless($dieta->mascota_id === $mascota->id, 404);

        $this->dietas->actualizar($dieta, $request->validated());

        return back()->with('success', 'Dieta actualizada.');
    }

    public function eliminarDieta(Mascota $mascota, Dieta $dieta): RedirectResponse
    {
        Gate::authorize('delete', $dieta);
        abort_unless($dieta->mascota_id === $mascota->id, 404);

        $dieta->delete();

        return back()->with('success', 'Dieta eliminada.');
    }

    /* ------------------------------------------------------------------ celo */

    public function guardarCiclo(GuardarCicloCeloRequest $request, Mascota $mascota): RedirectResponse
    {
        // La duración y la estimación del próximo las calcula el observer.
        $mascota->ciclosCelo()->create($request->validated());

        return back()->with('success', 'Ciclo registrado.');
    }

    public function actualizarCiclo(
        GuardarCicloCeloRequest $request,
        Mascota $mascota,
        CicloCelo $ciclo,
    ): RedirectResponse {
        abort_unless($ciclo->mascota_id === $mascota->id, 404);

        $ciclo->update($request->validated());

        return back()->with('success', 'Ciclo actualizado.');
    }

    public function eliminarCiclo(Mascota $mascota, CicloCelo $ciclo): RedirectResponse
    {
        Gate::authorize('delete', $ciclo);
        abort_unless($ciclo->mascota_id === $mascota->id, 404);

        $ciclo->delete();

        return back()->with('success', 'Ciclo eliminado.');
    }

    /**
     * Cuánto cambió desde el peso anterior. Es el dato que se lee primero.
     *
     * @return array{kilos: float, texto: string, sube: bool}|null
     */
    private function variacion(Mascota $mascota): ?array
    {
        $pesos = $mascota->pesos;

        if ($pesos->count() < 2) {
            return null;
        }

        $ultimo = $pesos->last();
        $anterior = $pesos->slice(-2, 1)->first();
        $diferencia = round($ultimo->kilos() - $anterior->kilos(), 2);

        if ($diferencia === 0.0) {
            return ['kilos' => 0.0, 'texto' => 'Igual que la vez anterior', 'sube' => false];
        }

        return [
            'kilos' => $diferencia,
            'texto' => sprintf(
                '%s %s desde el %s',
                $diferencia > 0 ? 'Subió' : 'Bajó',
                number_format(abs($diferencia), 2, ',', '.').' kg',
                $anterior->fecha->translatedFormat('j \d\e F'),
            ),
            'sube' => $diferencia > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimacionParaElFront(Mascota $mascota): array
    {
        $estimacion = $this->estimador->para($mascota);

        return [
            ...$estimacion,
            'fecha' => $estimacion['fecha']?->toDateString(),
            'fecha_legible' => $estimacion['fecha']?->translatedFormat('j \d\e F \d\e Y'),
        ];
    }
}
