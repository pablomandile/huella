<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Enums\CategoriaMedicamento;
use App\Http\Requests\GuardarMedicamentoRequest;
use App\Http\Resources\MedicamentoResource;
use App\Models\Medicamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MedicamentoController extends CatalogoBaseController
{
    protected function pagina(): string
    {
        return 'catalogos/Medicamentos';
    }

    protected function columnaNombre(): string
    {
        return 'nombre_comercial';
    }

    protected function disponiblesPara(User $usuario): Builder
    {
        return Medicamento::disponiblesPara($usuario);
    }

    protected function nuevo(): Model&Catalogo
    {
        return new Medicamento;
    }

    protected function recurso(): string
    {
        return MedicamentoResource::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function opciones(Request $request): array
    {
        return ['categorias' => CategoriaMedicamento::opciones()];
    }

    public function store(GuardarMedicamentoRequest $request): RedirectResponse|JsonResponse
    {
        return $this->guardar($request);
    }

    public function update(
        GuardarMedicamentoRequest $request,
        Medicamento $medicamento,
    ): RedirectResponse|JsonResponse {
        return $this->modificar($request, $medicamento);
    }

    public function destroy(Medicamento $medicamento): RedirectResponse
    {
        return $this->borrar($medicamento);
    }

    public function duplicar(Request $request, Medicamento $medicamento): RedirectResponse
    {
        return $this->copiar($request, $medicamento);
    }
}
