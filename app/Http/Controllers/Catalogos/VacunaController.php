<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Enums\Especie;
use App\Http\Requests\GuardarVacunaRequest;
use App\Http\Resources\VacunaResource;
use App\Models\User;
use App\Models\Vacuna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VacunaController extends CatalogoBaseController
{
    protected function pagina(): string
    {
        return 'catalogos/Vacunas';
    }

    protected function disponiblesPara(User $usuario): Builder
    {
        return Vacuna::disponiblesPara($usuario);
    }

    protected function nuevo(): Model&Catalogo
    {
        return new Vacuna;
    }

    protected function recurso(): string
    {
        return VacunaResource::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function opciones(Request $request): array
    {
        return ['especies' => Especie::opciones()];
    }

    public function store(GuardarVacunaRequest $request): RedirectResponse|JsonResponse
    {
        return $this->guardar($request);
    }

    public function update(
        GuardarVacunaRequest $request,
        Vacuna $vacuna,
    ): RedirectResponse|JsonResponse {
        return $this->modificar($request, $vacuna);
    }

    public function destroy(Vacuna $vacuna): RedirectResponse
    {
        return $this->borrar($vacuna);
    }

    public function duplicar(Request $request, Vacuna $vacuna): RedirectResponse
    {
        return $this->copiar($request, $vacuna);
    }
}
