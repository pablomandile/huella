<?php

namespace App\Http\Controllers;

use App\Enums\IntensidadCelo;
use App\Enums\LugarBano;
use App\Enums\OrigenPeso;
use App\Enums\TipoDesparasitacion;
use App\Http\Requests\GuardarAplicacionVacunaRequest;
use App\Http\Requests\GuardarBanoRequest;
use App\Http\Requests\GuardarCicloCeloRequest;
use App\Http\Requests\GuardarDesparasitacionRequest;
use App\Http\Requests\GuardarDietaRequest;
use App\Http\Requests\GuardarPesoRequest;
use App\Http\Resources\AlimentoResource;
use App\Http\Resources\AplicacionVacunaResource;
use App\Http\Resources\BanoResource;
use App\Http\Resources\CicloCeloResource;
use App\Http\Resources\DesparasitacionResource;
use App\Http\Resources\DietaResource;
use App\Http\Resources\MascotaResource;
use App\Http\Resources\MedicamentoResource;
use App\Http\Resources\RegistroPesoResource;
use App\Http\Resources\VacunaResource;
use App\Http\Resources\VeterinariaResource;
use App\Http\Resources\VeterinarioResource;
use App\Models\Alimento;
use App\Models\AplicacionVacuna;
use App\Models\Bano;
use App\Models\CicloCelo;
use App\Models\Desparasitacion;
use App\Models\Dieta;
use App\Models\Mascota;
use App\Models\Medicamento;
use App\Models\RegistroPeso;
use App\Models\Vacuna;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use App\Services\DietaService;
use App\Services\EstadoVacunacionService;
use App\Services\EstimadorCeloService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Peso, alimentación, vacunas, desparasitaciones, celo y baños: lo que se
 * sigue en el tiempo.
 *
 * Van juntos porque se leen en tendencia y no en un momento: cuánto pesa
 * comparado con antes, qué viene comiendo, cuándo toca la próxima dosis, hace
 * cuánto que no se baña, cada cuánto le viene el celo.
 *
 * Vacunas y desparasitaciones vivían en una pantalla aparte, «Preventivo»: una
 * pantalla más a la que había que saber ir, para dos listas que se consultan
 * junto con el resto. La URL vieja redirige acá.
 */
class SeguimientoController extends Controller
{
    public function __construct(
        private readonly DietaService $dietas,
        private readonly EstimadorCeloService $estimador,
        private readonly EstadoVacunacionService $vacunacion,
    ) {}

    /**
     * El paso previo del menú, que no tiene contexto de mascota: pregunta de
     * cuál. Con una sola no pregunta, porque sería un click de más en cada uso.
     */
    public function elegir(Request $request): Response|RedirectResponse
    {
        $usuario = $request->user();

        // `mascotas()` y no `mascotasACargo()`: un lector también la mira.
        $mascotas = $usuario->mascotas()->orderBy('nombre')->get();

        if ($mascotas->isEmpty()) {
            return redirect()
                ->route('mascotas.create')
                ->with('warning', 'Primero cargá una mascota para poder hacerle el seguimiento.');
        }

        if ($mascotas->count() === 1) {
            return redirect()->route('mascotas.seguimiento.index', $mascotas->first());
        }

        return Inertia::render('seguimiento/Elegir', [
            'mascotas' => $mascotas->map(fn (Mascota $mascota) => [
                ...MascotaResource::make($mascota)->resolve(),
                'url' => route('mascotas.seguimiento.index', $mascota),
            ])->all(),
            // Viene preseleccionada la que se estaba mirando, si hay alguna.
            'mascotaActivaId' => session('mascota_activa_id'),
        ]);
    }

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
            'banos' => BanoResource::collection($mascota->banos)->resolve(),
            // El módulo de celo solo existe para hembras no castradas y vivas.
            'celoVisible' => $mascota->celo_visible,
            'ciclos' => $mascota->celo_visible
                ? CicloCeloResource::collection($mascota->ciclosCelo)->resolve()
                : [],
            'estimacionCelo' => $mascota->celo_visible
                ? $this->estimacionParaElFront($mascota)
                : null,
            // El semáforo mira solo las fechas cargadas: no decide qué vacunas
            // le corresponden (regla de negocio 7).
            'estadoVacunacion' => $this->vacunacion->para($mascota),
            'aplicaciones' => AplicacionVacunaResource::collection(
                $mascota->vacunasAplicadas()->with('vacuna')->get(),
            )->resolve(),
            'desparasitaciones' => DesparasitacionResource::collection(
                $mascota->desparasitaciones()->with('medicamento')->get(),
            )->resolve(),
            'puedeRegistrar' => $usuario->can('registrarEventos', $mascota),

            // Catálogos para los combos, con alta al vuelo.
            'vacunas' => VacunaResource::collection(
                Vacuna::disponiblesPara($usuario)->orderBy('nombre')->get(),
            )->resolve(),
            'medicamentos' => MedicamentoResource::collection(
                Medicamento::disponiblesPara($usuario)->orderBy('nombre_comercial')->get(),
            )->resolve(),
            'veterinarias' => VeterinariaResource::collection(
                Veterinaria::disponiblesPara($usuario)->orderBy('nombre')->get(),
            )->resolve(),
            'alimentos' => AlimentoResource::collection(
                Alimento::disponiblesPara($usuario)->orderBy('marca')->orderBy('nombre')->get(),
            )->resolve(),
            'veterinarios' => VeterinarioResource::collection(
                Veterinario::disponiblesPara($usuario)->with('veterinaria')->orderBy('nombre')->get(),
            )->resolve(),
            'origenesPeso' => OrigenPeso::opciones(),
            'lugaresBano' => LugarBano::opciones(),
            'intensidades' => IntensidadCelo::opciones(),
            'tiposDesparasitacion' => TipoDesparasitacion::opciones(),
            // La zona del **propietario**, no la de quien mira: si un lector en otro
            // país abre la ficha, "hoy" tiene que seguir siendo el día de la casa
            // donde vive la mascota. `Mascota::$with` ya trae al propietario.
            'hoy' => $mascota->propietario->hoy()->toDateString(),
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

    /* ------------------------------------------------------------------ baño */

    public function guardarBano(GuardarBanoRequest $request, Mascota $mascota): RedirectResponse
    {
        $mascota->banos()->create($request->validated());

        return back()->with('success', 'Baño registrado.');
    }

    public function eliminarBano(Mascota $mascota, Bano $bano): RedirectResponse
    {
        Gate::authorize('delete', $bano);
        abort_unless($bano->mascota_id === $mascota->id, 404);

        $bano->delete();

        return back()->with('success', 'Baño eliminado.');
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

    /* ------------------------------------------- vacunas y desparasitaciones */

    public function guardarVacuna(
        GuardarAplicacionVacunaRequest $request,
        Mascota $mascota,
    ): RedirectResponse {
        // El recordatorio de la próxima dosis lo crea el observer, no esto.
        $mascota->vacunasAplicadas()->create($request->validated());

        return back()->with('success', 'Vacuna registrada.');
    }

    public function actualizarVacuna(
        GuardarAplicacionVacunaRequest $request,
        Mascota $mascota,
        AplicacionVacuna $aplicacion,
    ): RedirectResponse {
        abort_unless($aplicacion->mascota_id === $mascota->id, 404);

        $aplicacion->update($request->validated());

        return back()->with('success', 'Vacuna actualizada.');
    }

    public function eliminarVacuna(Mascota $mascota, AplicacionVacuna $aplicacion): RedirectResponse
    {
        Gate::authorize('delete', $aplicacion);
        abort_unless($aplicacion->mascota_id === $mascota->id, 404);

        $aplicacion->delete();

        return back()->with('success', 'Vacuna eliminada.');
    }

    public function guardarDesparasitacion(
        GuardarDesparasitacionRequest $request,
        Mascota $mascota,
    ): RedirectResponse {
        $mascota->desparasitaciones()->create($request->validated());

        return back()->with('success', 'Desparasitación registrada.');
    }

    public function actualizarDesparasitacion(
        GuardarDesparasitacionRequest $request,
        Mascota $mascota,
        Desparasitacion $desparasitacion,
    ): RedirectResponse {
        abort_unless($desparasitacion->mascota_id === $mascota->id, 404);

        $desparasitacion->update($request->validated());

        return back()->with('success', 'Desparasitación actualizada.');
    }

    public function eliminarDesparasitacion(
        Mascota $mascota,
        Desparasitacion $desparasitacion,
    ): RedirectResponse {
        Gate::authorize('delete', $desparasitacion);
        abort_unless($desparasitacion->mascota_id === $mascota->id, 404);

        $desparasitacion->delete();

        return back()->with('success', 'Desparasitación eliminada.');
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
