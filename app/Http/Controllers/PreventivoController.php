<?php

namespace App\Http\Controllers;

use App\Enums\TipoDesparasitacion;
use App\Http\Requests\GuardarAplicacionVacunaRequest;
use App\Http\Requests\GuardarDesparasitacionRequest;
use App\Http\Resources\AplicacionVacunaResource;
use App\Http\Resources\DesparasitacionResource;
use App\Http\Resources\MascotaResource;
use App\Http\Resources\MedicamentoResource;
use App\Http\Resources\VacunaResource;
use App\Http\Resources\VeterinariaResource;
use App\Http\Resources\VeterinarioResource;
use App\Models\AplicacionVacuna;
use App\Models\Desparasitacion;
use App\Models\Mascota;
use App\Models\Medicamento;
use App\Models\Vacuna;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use App\Services\EstadoVacunacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vacunas y desparasitaciones de una mascota, en una sola pantalla.
 *
 * Van juntas porque son lo mismo desde el punto de vista del usuario: cosas que
 * se aplican cada tanto y hay que volver a dar. Y las dos generan su
 * recordatorio por observer al guardarse.
 */
class PreventivoController extends Controller
{
    public function __construct(private readonly EstadoVacunacionService $vacunacion) {}

    public function index(Request $request, Mascota $mascota): Response
    {
        Gate::authorize('view', $mascota);

        $usuario = $request->user();

        return Inertia::render('preventivo/Index', [
            'mascota' => MascotaResource::make($mascota)->resolve(),
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
            'veterinarios' => VeterinarioResource::collection(
                Veterinario::disponiblesPara($usuario)->with('veterinaria')->orderBy('nombre')->get(),
            )->resolve(),
            'tiposDesparasitacion' => TipoDesparasitacion::opciones(),
            'hoy' => $usuario->hoy()->toDateString(),
        ]);
    }

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
}
