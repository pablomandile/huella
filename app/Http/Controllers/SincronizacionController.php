<?php

namespace App\Http\Controllers;

use App\Http\Requests\SincronizarRequest;
use App\Services\SincronizacionService;
use Illuminate\Http\JsonResponse;

/**
 * Recibe la cola de cambios que el teléfono guardó sin conexión.
 *
 * JSON y no Inertia: la llama el código de sincronización en segundo plano, no
 * una pantalla. Contesta una línea por operación, y una que falla no frena a
 * las demás: un peso duplicado no tiene por qué trabar la toma que se marcó
 * después.
 */
class SincronizacionController extends Controller
{
    public function __construct(private readonly SincronizacionService $sincronizacion) {}

    public function __invoke(SincronizarRequest $request): JsonResponse
    {
        /** @var list<array<mixed>> $ops */
        $ops = $request->validated('ops');
        $usuario = $request->user();

        // En orden: es el orden en que se hicieron en el teléfono.
        $resultados = array_map(
            fn (array $op) => $this->sincronizacion->aplicar($usuario, $op),
            $ops,
        );

        return response()->json(['resultados' => $resultados]);
    }
}
