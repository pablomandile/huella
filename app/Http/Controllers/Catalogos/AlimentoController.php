<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use App\Http\Requests\GuardarAlimentoRequest;
use App\Http\Resources\AlimentoResource;
use App\Models\Alimento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlimentoController extends CatalogoBaseController
{
    protected function pagina(): string
    {
        return 'catalogos/Alimentos';
    }

    protected function disponiblesPara(User $usuario): Builder
    {
        return Alimento::disponiblesPara($usuario);
    }

    protected function nuevo(): Model&Catalogo
    {
        return new Alimento;
    }

    protected function recurso(): string
    {
        return AlimentoResource::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function opciones(Request $request): array
    {
        return [
            'tipos' => TipoAlimento::opciones(),
            'gamas' => GamaAlimento::opciones(),
            'especies' => Especie::opciones(),
            'etapas' => EtapaVida::opciones(),
        ];
    }

    public function store(GuardarAlimentoRequest $request): RedirectResponse|JsonResponse
    {
        return $this->guardar($request);
    }

    public function update(
        GuardarAlimentoRequest $request,
        Alimento $alimento,
    ): RedirectResponse|JsonResponse {
        return $this->modificar($request, $alimento);
    }

    public function destroy(Alimento $alimento): RedirectResponse
    {
        return $this->borrar($alimento);
    }

    public function duplicar(Request $request, Alimento $alimento): RedirectResponse
    {
        return $this->copiar($request, $alimento);
    }
}
