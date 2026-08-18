<?php

namespace App\Http\Controllers;

use App\Enums\EstadoRecordatorio;
use App\Enums\TipoRecordatorio;
use App\Http\Requests\GuardarRecordatorioRequest;
use App\Http\Requests\ResolverRecordatorioRequest;
use App\Http\Resources\RecordatorioResource;
use App\Models\Mascota;
use App\Models\Recordatorio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La bandeja: todo lo que hay que agendar, de todas las mascotas juntas.
 *
 * Como «Medicación de hoy», es global y no de la mascota activa: lo que el
 * usuario quiere saber al abrirla es qué le falta hacer, no de quién.
 */
class RecordatorioController extends Controller
{
    /** Cuántos días se pospone por defecto de un tap. */
    private const DIAS_A_POSPONER = 7;

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        // A cargo y no todas: la bandeja es lo que hay que hacer, y un lector no
        // resuelve ni pospone nada de la mascota que le compartieron.
        $mascotas = $usuario->mascotasACargo()->pluck('mascotas.id');

        $abiertos = Recordatorio::query()
            ->whereIn('mascota_id', $mascotas)
            ->abiertos()
            ->with('mascota')
            ->orderBy('fecha_objetivo')
            ->get();

        // Los resueltos hace poco quedan a la vista para poder deshacer.
        $resueltos = Recordatorio::query()
            ->whereIn('mascota_id', $mascotas)
            ->whereIn('estado', [
                EstadoRecordatorio::Completado,
                EstadoRecordatorio::Descartado,
            ])
            ->with('mascota')
            ->orderByDesc('updated_at')
            ->take(10)
            ->get();

        return Inertia::render('recordatorios/Index', [
            'abiertos' => RecordatorioResource::collection($abiertos)->resolve(),
            'resueltos' => RecordatorioResource::collection($resueltos)->resolve(),
            // El combo del alta manual: ofrecer una ajena termina en un 403.
            'mascotas' => $usuario->mascotasACargo()
                ->orderBy('nombre')
                ->get(['mascotas.id', 'mascotas.nombre'])
                ->map(fn (Mascota $m) => ['id' => $m->id, 'nombre' => $m->nombre])
                ->all(),
            'diasAPosponer' => self::DIAS_A_POSPONER,
        ]);
    }

    /** Alta manual de algo que no sale de ningún otro registro. */
    public function store(GuardarRecordatorioRequest $request, Mascota $mascota): RedirectResponse
    {
        $recordatorio = new Recordatorio([
            ...$request->validated(),
            'tipo' => TipoRecordatorio::Personalizado,
        ]);

        $recordatorio->mascota_id = $mascota->id;
        $recordatorio->save();

        return back()->with('success', 'Recordatorio agregado.');
    }

    public function update(
        GuardarRecordatorioRequest $request,
        Recordatorio $recordatorio,
    ): RedirectResponse {
        $recordatorio->update($request->validated());

        return back()->with('success', 'Recordatorio actualizado.');
    }

    /**
     * Las tres salidas de la bandeja.
     *
     * Un recordatorio recurrente que se completa no desaparece: se corre al
     * siguiente intervalo, que es de lo que sirve marcarlo como recurrente.
     */
    public function resolver(
        ResolverRecordatorioRequest $request,
        Recordatorio $recordatorio,
    ): RedirectResponse {
        // La validación ya limitó `accion` a estos tres valores.
        $accion = (string) $request->validated('accion');
        $dias = (int) ($request->validated('dias') ?? self::DIAS_A_POSPONER);

        match ($accion) {
            'completar' => $this->completar($recordatorio),
            'posponer' => $this->posponer($recordatorio, $dias),
            default => $recordatorio->update(['estado' => EstadoRecordatorio::Descartado]),
        };

        return back();
    }

    /** Vuelve a abrir algo resuelto por error. */
    public function reabrir(Recordatorio $recordatorio): RedirectResponse
    {
        Gate::authorize('update', $recordatorio);

        // `fecha_completado` no es fillable a propósito: la escribe el sistema
        // cuando se resuelve, no el usuario desde un formulario.
        $recordatorio->estado = EstadoRecordatorio::Pendiente;
        $recordatorio->fecha_completado = null;
        $recordatorio->save();

        return back();
    }

    public function destroy(Recordatorio $recordatorio): RedirectResponse
    {
        Gate::authorize('delete', $recordatorio);

        // Los automáticos no se borran: se volverían a crear con su origen y
        // sería un botón que no hace nada. Para esos está descartar.
        abort_if($recordatorio->tipo->esAutomatico(), 403);

        $recordatorio->delete();

        return back()->with('success', 'Recordatorio eliminado.');
    }

    private function completar(Recordatorio $recordatorio): void
    {
        if ($recordatorio->recurrente && $recordatorio->intervalo_dias !== null) {
            $recordatorio->update([
                'fecha_objetivo' => $recordatorio->fecha_objetivo
                    ->addDays($recordatorio->intervalo_dias)
                    ->toDateString(),
                'estado' => EstadoRecordatorio::Pendiente,
            ]);

            return;
        }

        $recordatorio->estado = EstadoRecordatorio::Completado;
        $recordatorio->fecha_completado = now();
        $recordatorio->save();
    }

    private function posponer(Recordatorio $recordatorio, int $dias): void
    {
        $recordatorio->update([
            'fecha_objetivo' => $recordatorio->fecha_objetivo->addDays($dias)->toDateString(),
            // Vuelve a pendiente: hay que avisar de nuevo en la fecha nueva.
            'estado' => EstadoRecordatorio::Pendiente,
        ]);
    }
}
