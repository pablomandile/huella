<?php

namespace App\Http\Controllers;

use App\Enums\Especie;
use App\Enums\Sexo;
use App\Enums\TipoPelaje;
use App\Http\Requests\ActualizarMascotaRequest;
use App\Http\Requests\GuardarMascotaRequest;
use App\Http\Resources\MascotaResource;
use App\Models\Mascota;
use App\Services\FichaMascotaService;
use App\Services\ImagenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MascotaController extends Controller
{
    public function __construct(
        private readonly ImagenService $imagenes,
        private readonly FichaMascotaService $ficha,
    ) {}

    public function index(Request $request): Response
    {
        $mascotas = $request->user()
            ->mascotas()
            ->orderBy('nombre')
            ->get();

        return Inertia::render('mascotas/Index', [
            'mascotas' => MascotaResource::collection($mascotas)->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('mascotas/Create', $this->opciones());
    }

    public function store(GuardarMascotaRequest $request): RedirectResponse
    {
        $mascota = new Mascota($request->safe()->except('foto'));
        $mascota->usuario_id = $request->user()->id;
        $mascota->save();

        if ($request->hasFile('foto')) {
            $this->reemplazarFotoPerfil($mascota, $request);
        }

        session(['mascota_activa_id' => $mascota->id]);

        return redirect()
            ->route('mascotas.show', $mascota)
            ->with('success', "{$mascota->nombre} ya tiene su ficha.");
    }

    public function show(Request $request, Mascota $mascota): Response
    {
        Gate::authorize('view', $mascota);

        return Inertia::render(
            'mascotas/Show',
            $this->ficha->datos($mascota, $request->user()),
        );
    }

    public function edit(Mascota $mascota): Response
    {
        Gate::authorize('update', $mascota);

        return Inertia::render('mascotas/Edit', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            ...$this->opciones(),
        ]);
    }

    public function update(ActualizarMascotaRequest $request, Mascota $mascota): RedirectResponse
    {
        $mascota->fill($request->safe()->except('foto'));

        // Regla de negocio 2: al marcar castración se descartan los
        // recordatorios de celo pendientes (los generará la fase 5; el
        // ocultamiento del módulo ya sale de celo_visible).
        $mascota->save();

        if ($request->hasFile('foto')) {
            $this->reemplazarFotoPerfil($mascota, $request);
        }

        return redirect()
            ->route('mascotas.show', $mascota)
            ->with('success', 'Ficha actualizada.');
    }

    public function destroy(Request $request, Mascota $mascota): RedirectResponse
    {
        Gate::authorize('delete', $mascota);

        $mascota->delete(); // soft delete: nada se borra de verdad

        if ((int) session('mascota_activa_id') === $mascota->id) {
            session()->forget('mascota_activa_id');
        }

        return redirect()
            ->route('mascotas.index')
            ->with('success', "La ficha de {$mascota->nombre} se dio de baja.");
    }

    /**
     * Convierte a WebP, guarda en el disco privado y borra la foto anterior.
     * La primera versión también entra a la galería, con la fecha de hoy.
     */
    private function reemplazarFotoPerfil(Mascota $mascota, Request $request): void
    {
        $anterior = $mascota->foto_perfil;

        $rutas = $this->imagenes->guardar($request->file('foto'), "mascotas/{$mascota->id}");

        $mascota->forceFill(['foto_perfil' => $rutas['ruta']])->save();

        $mascota->fotos()->create([
            'ruta' => $rutas['ruta'],
            'ruta_miniatura' => $rutas['ruta_miniatura'],
            'fecha' => now()->toDateString(),
            'epigrafe' => null,
        ]);

        // La anterior queda en la galería (es historia de la mascota); solo se
        // borra el archivo si ninguna foto de la galería lo referencia.
        if ($anterior && ! $mascota->fotos()->where('ruta', $anterior)->exists()) {
            $this->imagenes->eliminar($anterior, $this->rutaMiniaturaDe($anterior));
        }
    }

    private function rutaMiniaturaDe(string $ruta): string
    {
        return preg_replace('/\.webp$/', '-min.webp', $ruta);
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'especies' => Especie::opciones(),
            'sexos' => Sexo::opciones(),
            'tiposPelaje' => TipoPelaje::opciones(),
        ];
    }
}
