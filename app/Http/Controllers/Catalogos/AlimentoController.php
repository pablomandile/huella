<?php

namespace App\Http\Controllers\Catalogos;

use App\Contracts\Catalogo;
use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use App\Http\Requests\GuardarAlimentoRequest;
use App\Http\Requests\GuardarCatalogoRequest;
use App\Http\Resources\AlimentoResource;
use App\Models\Alimento;
use App\Models\User;
use App\Services\ImagenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlimentoController extends CatalogoBaseController
{
    public function __construct(private readonly ImagenService $imagenes) {}

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

    /**
     * La foto del paquete: ruta de archivo, así que no la hereda una copia.
     *
     * @return list<string>
     */
    protected function columnasQueNoSeCopian(): array
    {
        return ['foto'];
    }

    /**
     * Guarda, reemplaza o quita la foto del paquete.
     *
     * Va por el hook y no por `fill()` porque es un archivo. Se recomprime a
     * WebP —al contrario que un adjunto clínico, donde el original es la prueba—:
     * acá la imagen solo tiene que alcanzar para reconocer la bolsa en la
     * góndola, y una foto de celular sin tocar pesa cinco megas.
     */
    protected function despuesDeGuardar(
        GuardarCatalogoRequest $request,
        Model&Catalogo $registro,
    ): void {
        if (! $registro instanceof Alimento) {
            return;
        }

        $anterior = [$registro->foto, $registro->ruta_foto_miniatura];

        if ($request->boolean('quitar_foto') && ! $request->hasFile('foto')) {
            $this->imagenes->eliminar(...$anterior);
            $registro->forceFill(['foto' => null])->save();

            return;
        }

        if (! $request->hasFile('foto')) {
            return;
        }

        $guardada = $this->imagenes->guardar(
            $request->file('foto'),
            "catalogos/alimentos/{$registro->id}",
        );

        $registro->forceFill(['foto' => $guardada['ruta']])->save();

        // Recién ahora: si guardar la nueva falla, no se perdió la vieja.
        $this->imagenes->eliminar(...$anterior);
    }

    /**
     * Sirve la foto tras verificar que el usuario puede ver el registro.
     *
     * Nunca por URL pública, igual que el resto de las imágenes de la app. Un
     * semilla lo puede ver cualquiera —es catálogo compartido—; lo propio, solo
     * su dueño. Eso ya lo decide `CatalogoPolicy::view`.
     */
    public function foto(Request $request, Alimento $alimento): StreamedResponse
    {
        Gate::authorize('view', $alimento);

        abort_unless($alimento->foto !== null, 404);

        $ruta = $request->boolean('min')
            ? $alimento->ruta_foto_miniatura
            : $alimento->foto;

        if ($ruta === null || ! Storage::exists($ruta)) {
            $ruta = $alimento->foto; // sin miniatura, cae a la principal
        }

        abort_unless(Storage::exists($ruta), 404);

        return Storage::response($ruta, headers: ['Cache-Control' => 'private, max-age=86400']);
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
