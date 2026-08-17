<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use App\Services\ExportadorDatosService;
use App\Services\HistoriaClinicaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las dos salidas de Huella: el PDF para llevar al veterinario y el JSON con
 * todo lo cargado, para que el usuario se pueda ir con sus datos.
 */
class ExportacionController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaService $historia,
        private readonly ExportadorDatosService $exportador,
    ) {}

    /**
     * Historia clínica en PDF, opcionalmente acotada por rango.
     */
    public function historiaClinica(Request $request, Mascota $mascota): Response
    {
        Gate::authorize('view', $mascota);

        $validado = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $datos = $this->historia->para($mascota, $validado);

        return Pdf::loadView('pdf.historia-clinica', $datos)
            ->setPaper('a4')
            ->download($this->historia->nombreDeArchivo($mascota));
    }

    /**
     * Todo lo que el usuario cargó, en JSON.
     *
     * Se descarga en vez de mostrarse: son datos clínicos y no tienen por qué
     * quedar en el historial del navegador.
     */
    public function datos(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()
            ->json(
                $this->exportador->para($usuario),
                200,
                [],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )
            ->withHeaders([
                'Content-Disposition' => sprintf(
                    'attachment; filename="%s"',
                    $this->exportador->nombreDeArchivo($usuario),
                ),
            ]);
    }
}
