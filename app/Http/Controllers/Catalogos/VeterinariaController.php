<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Http\Requests\GuardarVeterinariaRequest;
use App\Http\Resources\VeterinariaResource;
use App\Models\User;
use App\Models\Veterinaria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class VeterinariaController extends CatalogoBaseController
{
    protected function pagina(): string
    {
        return 'catalogos/Veterinarias';
    }

    protected function disponiblesPara(User $usuario): Builder
    {
        return Veterinaria::disponiblesPara($usuario);
    }

    protected function nuevo(): Model&Catalogo
    {
        return new Veterinaria;
    }

    protected function recurso(): string
    {
        return VeterinariaResource::class;
    }

    public function store(GuardarVeterinariaRequest $request): RedirectResponse|JsonResponse
    {
        return $this->guardar($request);
    }

    public function update(
        GuardarVeterinariaRequest $request,
        Veterinaria $veterinaria,
    ): RedirectResponse|JsonResponse {
        return $this->modificar($request, $veterinaria);
    }

    public function destroy(Veterinaria $veterinaria): RedirectResponse
    {
        return $this->borrar($veterinaria);
    }
}
