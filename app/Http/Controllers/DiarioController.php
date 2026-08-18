<?php

namespace App\Http\Controllers;

use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use App\Http\Requests\FiltrarTimelineRequest;
use App\Http\Requests\GuardarEntradaDiarioRequest;
use App\Http\Resources\MascotaResource;
use App\Models\EntradaDiario;
use App\Models\Mascota;
use App\Services\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El diario: la línea de tiempo unificada y las entradas libres.
 *
 * Es la pantalla principal de una mascota según la especificación §4.13: mezcla
 * todo lo que pasó, ordenado por fecha, con el ícono y el color de cada tipo.
 */
class DiarioController extends Controller
{
    public function __construct(private readonly TimelineService $timeline) {}

    public function index(FiltrarTimelineRequest $request, Mascota $mascota): Response
    {
        Gate::authorize('view', $mascota);

        $filtros = $request->filtros();

        return Inertia::render('diario/Index', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            ...$this->timeline->para($mascota, $filtros),
            // Los contadores se calculan sin el filtro de tipos: tienen que
            // decir cuántos hay de cada uno, aunque estén ocultos ahora.
            'totales' => $this->timeline->totalesPorTipo($mascota, [
                ...$filtros,
                'tipos' => [],
            ]),
            'filtros' => $filtros,
            'tipos' => TimelineService::TIPOS,
            'categorias' => CategoriaEntrada::opciones(),
            'animos' => Animo::opciones(),
            'puedeRegistrar' => $request->user()->can('registrarEventos', $mascota),
            // La zona del **propietario**, no la de quien mira: si un lector en otro
            // país abre la ficha, "hoy" tiene que seguir siendo el día de la casa
            // donde vive la mascota. `Mascota::$with` ya trae al propietario.
            'hoy' => $mascota->propietario->hoy()->toDateString(),
        ]);
    }

    /**
     * Página siguiente del scroll infinito.
     *
     * Contesta JSON y no Inertia a propósito: el scroll suma eventos a la lista
     * que ya está en pantalla, y una navegación Inertia la reemplazaría entera
     * —perdiendo la posición del scroll, que es justo lo que no puede pasar—.
     */
    public function mas(FiltrarTimelineRequest $request, Mascota $mascota): JsonResponse
    {
        Gate::authorize('view', $mascota);

        return response()->json(
            $this->timeline->para(
                $mascota,
                $request->filtros(),
                $request->safe()->string('cursor')->toString() ?: null,
            ),
        );
    }

    public function store(
        GuardarEntradaDiarioRequest $request,
        Mascota $mascota,
    ): RedirectResponse {
        $mascota->entradasDiario()->create($request->validated());

        return back()->with('success', 'Nota agregada al diario.');
    }

    public function update(
        GuardarEntradaDiarioRequest $request,
        Mascota $mascota,
        EntradaDiario $entrada,
    ): RedirectResponse {
        abort_unless($entrada->mascota_id === $mascota->id, 404);

        $entrada->update($request->validated());

        return back()->with('success', 'Nota actualizada.');
    }

    public function destroy(Mascota $mascota, EntradaDiario $entrada): RedirectResponse
    {
        Gate::authorize('delete', $entrada);
        abort_unless($entrada->mascota_id === $mascota->id, 404);

        $entrada->delete();

        return back()->with('success', 'Nota eliminada.');
    }
}
