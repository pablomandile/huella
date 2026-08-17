<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Alimento;
use App\Models\Medicamento;
use App\Models\Vacuna;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portada de los catálogos: qué hay cargado en cada uno.
 *
 * Los títulos y los íconos viven en el front; de acá solo salen los números,
 * que son lo único que el servidor sabe.
 */
class PanelCatalogosController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('catalogos/Index', [
            'totales' => [
                'veterinarias' => Veterinaria::disponiblesPara($usuario)->count(),
                'veterinarios' => Veterinario::disponiblesPara($usuario)->count(),
                'medicamentos' => Medicamento::disponiblesPara($usuario)->count(),
                'vacunas' => Vacuna::disponiblesPara($usuario)->count(),
                'alimentos' => Alimento::disponiblesPara($usuario)->count(),
            ],
        ]);
    }
}
