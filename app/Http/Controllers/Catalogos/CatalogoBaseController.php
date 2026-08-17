<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarCatalogoRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los cinco catálogos se comportan igual: listar lo disponible, dar de alta
 * algo propio, editar y borrar solo lo propio, y duplicar un semilla cuando
 * hace falta cambiarle algo. Eso vive acá una sola vez.
 *
 * Cada hijo aporta su modelo, su Resource y su página, y redeclara `store` y
 * `update` únicamente para poner el FormRequest correcto en la firma — que es
 * como Laravel sabe qué validar.
 */
abstract class CatalogoBaseController extends Controller
{
    /** Página Inertia del listado (minúscula, ver CLAUDE.md). */
    abstract protected function pagina(): string;

    /**
     * @return Builder<covariant Model>
     */
    abstract protected function disponiblesPara(User $usuario): Builder;

    /** Instancia vacía para el alta. */
    abstract protected function nuevo(): Model&Catalogo;

    /** @return class-string<JsonResource> */
    abstract protected function recurso(): string;

    /** Columna con el nombre: ordena el listado y nombra las copias. */
    protected function columnaNombre(): string
    {
        return 'nombre';
    }

    /**
     * Eager loading explícito del listado.
     *
     * @return list<string>
     */
    protected function relaciones(): array
    {
        return [];
    }

    /**
     * Props extra de la página (opciones de enums, catálogos relacionados).
     *
     * @return array<string, mixed>
     */
    protected function opciones(Request $request): array
    {
        return [];
    }

    public function index(Request $request): Response
    {
        return Inertia::render($this->pagina(), [
            'registros' => $this->listar($request->user()),
            ...$this->opciones($request),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listar(User $usuario): array
    {
        $registros = $this->disponiblesPara($usuario)
            ->with($this->relaciones())
            ->orderBy($this->columnaNombre())
            ->get();

        $recurso = $this->recurso();

        return $recurso::collection($registros)->resolve();
    }

    protected function guardar(GuardarCatalogoRequest $request): RedirectResponse|JsonResponse
    {
        $registro = $this->nuevo();
        $registro->fill($request->validated());
        $registro->asignarPropietario($request->user());
        $registro->save();

        return $this->responder($request, $registro, 'Se agregó al catálogo.');
    }

    protected function modificar(
        GuardarCatalogoRequest $request,
        Model&Catalogo $registro,
    ): RedirectResponse|JsonResponse {
        // La autorización ya la hizo el FormRequest: editar solo lo propio.
        $registro->fill($request->validated());
        $registro->save();

        return $this->responder($request, $registro, 'Cambios guardados.');
    }

    protected function borrar(Model&Catalogo $registro): RedirectResponse
    {
        Gate::authorize('delete', $registro);

        $registro->delete(); // soft delete: lo ya registrado no pierde su referencia

        return back()->with('success', 'Se quitó del catálogo.');
    }

    /**
     * Regla de negocio 4: un semilla no se edita, se duplica. La copia nace
     * a nombre del usuario y con " (copia)" en el nombre, para que en la lista
     * se distinga del original de un vistazo.
     */
    protected function copiar(Request $request, Model&Catalogo $original): RedirectResponse
    {
        Gate::authorize('duplicar', $original);

        $columna = $this->columnaNombre();

        $copia = $original->replicate();
        $copia->asignarPropietario($request->user());
        $copia->setAttribute($columna, mb_substr(
            $original->getAttribute($columna).' (copia)',
            0,
            120,
        ));
        $copia->save();

        return back()->with('success', 'Se creó una copia editable.');
    }

    /**
     * Dos consumidores, dos respuestas.
     *
     * Desde el listado esto es una navegación Inertia normal. Desde el combo
     * de otro formulario es un `fetch`, y ahí contestamos JSON: el usuario
     * suma la opción sin recargar y **sin perder lo que ya venía cargando**.
     */
    private function responder(
        Request $request,
        Model&Catalogo $registro,
        string $mensaje,
    ): RedirectResponse|JsonResponse {
        if ($request->wantsJson()) {
            $registro->load($this->relaciones());
            $recurso = $this->recurso();

            return response()->json([
                'registro' => $recurso::make($registro)->resolve(),
                'mensaje' => $mensaje,
            ], 201);
        }

        return back()->with('success', $mensaje);
    }
}
