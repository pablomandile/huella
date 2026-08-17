<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTratamientoRequest;
use App\Models\Mascota;
use App\Models\Tratamiento;
use App\Services\GeneradorTomasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TratamientoController extends Controller
{
    public function __construct(private readonly GeneradorTomasService $tomas) {}

    public function store(GuardarTratamientoRequest $request, Mascota $mascota): RedirectResponse
    {
        $tratamiento = $mascota->tratamientos()->create($request->validated());
        $tratamiento->setRelation('mascota', $mascota);

        $this->tomas->generar($tratamiento);

        return back()->with('success', 'Tratamiento agregado.');
    }

    /**
     * Al cambiar la posología se rearma el cronograma, pero **solo hacia
     * adelante**: lo que ya se administró u omitió es historia y no se toca
     * (regla de negocio 5).
     */
    public function update(
        GuardarTratamientoRequest $request,
        Mascota $mascota,
        Tratamiento $tratamiento,
    ): RedirectResponse {
        abort_unless($tratamiento->mascota_id === $mascota->id, 404);

        $tratamiento->update($request->validated());
        $tratamiento->setRelation('mascota', $mascota);

        $creadas = $this->tomas->regenerar($tratamiento);

        return back()->with('success', $creadas > 0
            ? "Tratamiento actualizado. Se reprogramaron {$creadas} tomas."
            : 'Tratamiento actualizado.');
    }

    public function destroy(Mascota $mascota, Tratamiento $tratamiento): RedirectResponse
    {
        Gate::authorize('delete', $tratamiento);
        abort_unless($tratamiento->mascota_id === $mascota->id, 404);

        $tratamiento->delete();

        return back()->with('success', 'Tratamiento eliminado.');
    }
}
