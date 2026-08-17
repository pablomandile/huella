<?php

namespace App\Http\Controllers;

use App\Enums\TipoAdjunto;
use App\Enums\TipoVisita;
use App\Enums\ViaAdministracion;
use App\Http\Requests\GuardarVisitaRequest;
use App\Http\Resources\MascotaResource;
use App\Http\Resources\MedicamentoResource;
use App\Http\Resources\VeterinariaResource;
use App\Http\Resources\VeterinarioResource;
use App\Http\Resources\VisitaResource;
use App\Models\Mascota;
use App\Models\Medicamento;
use App\Models\User;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use App\Models\Visita;
use App\Services\RegistroVisitaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisitaController extends Controller
{
    public function __construct(private readonly RegistroVisitaService $visitas) {}

    public function index(Request $request, Mascota $mascota): Response
    {
        Gate::authorize('view', $mascota);

        // `tratamientos.medicamento` y no solo `tratamientos`: el Resource lee
        // `nombre_medicamento`, que sale de la relación.
        $visitas = $mascota->visitas()
            ->with(['veterinaria', 'veterinario', 'tratamientos.medicamento', 'adjuntos'])
            ->get();

        return Inertia::render('visitas/Index', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            'visitas' => VisitaResource::collection($visitas)->resolve(),
            'puedeRegistrar' => $request->user()->can('registrarEventos', $mascota),
        ]);
    }

    public function create(Request $request, Mascota $mascota): Response
    {
        Gate::authorize('registrarEventos', $mascota);

        return Inertia::render('visitas/Create', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            // Fecha y hora de ahora en el reloj del usuario: la visita se carga
            // en el momento, y así no hay que tocar el campo.
            'ahora' => $request->user()->ahora()->format('Y-m-d\TH:i'),
            ...$this->opciones($request->user()),
        ]);
    }

    public function store(GuardarVisitaRequest $request, Mascota $mascota): RedirectResponse
    {
        $datos = $request->safe()->except(['tratamientos', 'adjuntos', 'tipo_adjunto']);

        $visita = $this->visitas->crear(
            $mascota,
            $datos,
            $this->tratamientosDe($request),
            $this->adjuntosDe($request),
        );

        return redirect()
            ->route('mascotas.visitas.show', [$mascota, $visita])
            ->with('success', 'Visita registrada.');
    }

    public function show(Request $request, Mascota $mascota, Visita $visita): Response
    {
        Gate::authorize('view', $visita);
        abort_unless($visita->mascota_id === $mascota->id, 404);

        $visita->load([
            'veterinaria',
            'veterinario',
            'adjuntos',
            'tratamientos.medicamento',
            'tratamientos.tomas',
        ]);

        return Inertia::render('visitas/Show', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            'visita' => VisitaResource::make($visita)->resolve(),
            'puedeEditar' => $request->user()->can('update', $visita),
            'tiposAdjunto' => TipoAdjunto::opciones(),
            ...$this->opciones($request->user()),
        ]);
    }

    public function edit(Request $request, Mascota $mascota, Visita $visita): Response
    {
        Gate::authorize('update', $visita);
        abort_unless($visita->mascota_id === $mascota->id, 404);

        return Inertia::render('visitas/Edit', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            'visita' => VisitaResource::make($visita)->resolve(),
            ...$this->opciones($request->user()),
        ]);
    }

    public function update(
        GuardarVisitaRequest $request,
        Mascota $mascota,
        Visita $visita,
    ): RedirectResponse {
        abort_unless($visita->mascota_id === $mascota->id, 404);

        // Los tratamientos y los adjuntos se gestionan uno por uno desde la
        // ficha: acá solo cambian los datos de la consulta.
        $this->visitas->actualizar(
            $visita,
            $request->safe()->except(['tratamientos', 'adjuntos', 'tipo_adjunto']),
        );

        return redirect()
            ->route('mascotas.visitas.show', [$mascota, $visita])
            ->with('success', 'Visita actualizada.');
    }

    public function destroy(Mascota $mascota, Visita $visita): RedirectResponse
    {
        Gate::authorize('delete', $visita);
        abort_unless($visita->mascota_id === $mascota->id, 404);

        $visita->delete(); // soft delete: es historia clínica

        return redirect()
            ->route('mascotas.visitas.index', $mascota)
            ->with('success', 'Visita eliminada.');
    }

    /**
     * Los tratamientos vienen indexados por el formulario; se reindexan y se
     * descartan los que quedaron vacíos porque el usuario abrió la sección y
     * después la cerró sin cargar nada.
     *
     * @return list<array<string, mixed>>
     */
    private function tratamientosDe(GuardarVisitaRequest $request): array
    {
        /** @var array<int, array<string, mixed>> $tratamientos */
        $tratamientos = $request->safe()->array('tratamientos');

        return array_values($tratamientos);
    }

    /**
     * @return list<array{archivo: UploadedFile, tipo: string|null, descripcion: string|null}>
     */
    private function adjuntosDe(GuardarVisitaRequest $request): array
    {
        $tipo = $request->safe()->string('tipo_adjunto')->toString() ?: TipoAdjunto::Receta->value;
        $adjuntos = [];

        // foreach y no array_map: así el resultado es una lista con índices
        // consecutivos, que es lo que el servicio espera recibir.
        foreach ($request->file('adjuntos', []) as $archivo) {
            $adjuntos[] = ['archivo' => $archivo, 'tipo' => $tipo, 'descripcion' => null];
        }

        return $adjuntos;
    }

    /**
     * Catálogos para los combos del formulario. Se mandan enteros porque son
     * chicos y así el combo filtra y crea al vuelo sin ir al servidor.
     *
     * @return array<string, mixed>
     */
    private function opciones(User $usuario): array
    {
        return [
            'veterinarias' => VeterinariaResource::collection(
                Veterinaria::disponiblesPara($usuario)->orderBy('nombre')->get(),
            )->resolve(),
            'veterinarios' => VeterinarioResource::collection(
                Veterinario::disponiblesPara($usuario)->with('veterinaria')->orderBy('nombre')->get(),
            )->resolve(),
            'medicamentos' => MedicamentoResource::collection(
                Medicamento::disponiblesPara($usuario)->orderBy('nombre_comercial')->get(),
            )->resolve(),
            'tiposVisita' => TipoVisita::opciones(),
            'vias' => ViaAdministracion::opciones(),
        ];
    }
}
