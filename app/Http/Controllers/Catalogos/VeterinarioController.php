<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Http\Requests\GuardarVeterinarioRequest;
use App\Http\Resources\VeterinariaResource;
use App\Http\Resources\VeterinarioResource;
use App\Models\User;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VeterinarioController extends CatalogoBaseController
{
    protected function pagina(): string
    {
        return 'catalogos/Veterinarios';
    }

    protected function disponiblesPara(User $usuario): Builder
    {
        return Veterinario::disponiblesPara($usuario);
    }

    protected function nuevo(): Model&Catalogo
    {
        return new Veterinario;
    }

    protected function recurso(): string
    {
        return VeterinarioResource::class;
    }

    protected function relaciones(): array
    {
        return ['veterinaria'];
    }

    /**
     * El alta de un veterinario necesita elegir su veterinaria habitual, y esa
     * veterinaria puede no estar cargada todavía. Por eso este formulario usa
     * el combo con alta al vuelo: es el mismo componente que en la fase 4 va a
     * usar la visita.
     *
     * @return array<string, mixed>
     */
    protected function opciones(Request $request): array
    {
        return [
            'veterinarias' => VeterinariaResource::collection(
                Veterinaria::disponiblesPara($request->user())->orderBy('nombre')->get(),
            )->resolve(),
        ];
    }

    public function store(GuardarVeterinarioRequest $request): RedirectResponse|JsonResponse
    {
        return $this->guardar($request);
    }

    public function update(
        GuardarVeterinarioRequest $request,
        Veterinario $veterinario,
    ): RedirectResponse|JsonResponse {
        return $this->modificar($request, $veterinario);
    }

    public function destroy(Veterinario $veterinario): RedirectResponse
    {
        return $this->borrar($veterinario);
    }
}
