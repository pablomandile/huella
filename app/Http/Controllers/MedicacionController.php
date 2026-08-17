<?php

namespace App\Http\Controllers;

use App\Enums\EstadoToma;
use App\Http\Requests\MarcarTomaRequest;
use App\Models\TomaMedicamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Medicación de hoy": lo único que hay que hacer ahora mismo.
 *
 * Va de todas las mascotas juntas y no de la activa a propósito. A la mañana se
 * reparten los remedios de todos los que viven en la casa, y tener que cambiar
 * de mascota entre uno y otro es la forma más segura de saltearse una dosis.
 */
class MedicacionController extends Controller
{
    /** Más atrás de una semana, marcar una toma ya no le sirve a nadie. */
    private const DIAS_DE_DEUDA = 7;

    public function index(Request $request): Response
    {
        $usuario = $request->user();

        // "Hoy" es el día del usuario, no del servidor: con el servidor en UTC
        // y el usuario en Buenos Aires, las tomas de la noche caerían en el día
        // siguiente y desaparecerían de la pantalla.
        $inicioDeHoy = $usuario->hoy();
        $finDeHoy = $inicioDeHoy->addDay()->subSecond();
        $desdeDeuda = $inicioDeHoy->subDays(self::DIAS_DE_DEUDA);

        $mascotas = $usuario->mascotas()->pluck('mascotas.id');

        $tomas = TomaMedicamento::query()
            ->whereHas(
                'tratamiento',
                fn ($consulta) => $consulta->whereIn('mascota_id', $mascotas),
            )
            ->with(['tratamiento.mascota', 'tratamiento.medicamento'])
            ->where(function ($consulta) use ($inicioDeHoy, $finDeHoy, $desdeDeuda) {
                $consulta
                    // Todo lo de hoy, dado o no.
                    ->whereBetween('fecha_hora_programada', [
                        $inicioDeHoy->utc(),
                        $finDeHoy->utc(),
                    ])
                    // Y la deuda de los días anteriores, que es lo que se olvida.
                    ->orWhere(
                        fn ($atrasadas) => $atrasadas
                            ->where('estado', EstadoToma::Pendiente)
                            ->whereBetween('fecha_hora_programada', [
                                $desdeDeuda->utc(),
                                $inicioDeHoy->utc(),
                            ]),
                    );
            })
            ->orderBy('fecha_hora_programada')
            ->get();

        return Inertia::render('medicacion/Index', [
            'tomas' => $tomas->map(fn (TomaMedicamento $toma) => $this->paraLista($toma, $request))->all(),
            'hoy' => $inicioDeHoy->translatedFormat('l j \d\e F'),
        ]);
    }

    /**
     * Un tap y queda registrada. Sin confirmación: si se marcó mal, se vuelve a
     * tocar y se corrige, y eso es mejor que dos taps por cada dosis.
     */
    public function update(MarcarTomaRequest $request, TomaMedicamento $toma): RedirectResponse
    {
        $estado = EstadoToma::from($request->validated('estado'));

        $toma->update([
            'estado' => $estado,
            // La hora real solo tiene sentido si se dio; al desmarcar se limpia.
            'fecha_hora_real' => $estado === EstadoToma::Administrada
                ? ($request->validated('fecha_hora_real') ?? now())
                : null,
            'notas' => $request->validated('notas') ?? $toma->notas,
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function paraLista(TomaMedicamento $toma, Request $request): array
    {
        $usuario = $request->user();
        $local = $usuario->enSuZona($toma->fecha_hora_programada);
        $tratamiento = $toma->tratamiento;
        $mascota = $tratamiento->mascota;

        return [
            'id' => $toma->id,
            'hora' => $local?->format('H:i'),
            'fecha_legible' => $local?->translatedFormat('D j/n'),
            'atrasada' => $toma->estaPendiente()
                && $local !== null
                && $local->lessThan($usuario->hoy()),
            'estado' => $toma->estado->value,
            'estado_etiqueta' => $toma->estado->etiqueta(),
            'medicamento' => $tratamiento->nombre_medicamento,
            'dosis' => $tratamiento->dosis,
            'via_etiqueta' => $tratamiento->via->etiqueta(),
            'notas_tratamiento' => $tratamiento->notas,
            'mascota_id' => $mascota->id,
            'mascota_nombre' => $mascota->nombre,
            'mascota_foto_url' => $mascota->foto_perfil
                ? route('mascotas.foto-perfil', ['mascota' => $mascota->id, 'min' => 1])
                : null,
        ];
    }
}
