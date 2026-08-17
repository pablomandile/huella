<?php

namespace App\Http\Controllers;

use App\Enums\TipoAdjunto;
use App\Http\Requests\GuardarAdjuntoRequest;
use App\Models\Adjunto;
use App\Models\Mascota;
use App\Models\Visita;
use App\Services\ArchivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdjuntoController extends Controller
{
    public function __construct(private readonly ArchivoService $archivos) {}

    public function store(
        GuardarAdjuntoRequest $request,
        Mascota $mascota,
        Visita $visita,
    ): RedirectResponse {
        abort_unless($visita->mascota_id === $mascota->id, 404);

        $this->archivos->adjuntar(
            $visita->adjuntos(),
            $request->file('archivo'),
            TipoAdjunto::from($request->validated('tipo')),
            $request->validated('descripcion'),
            "mascotas/{$mascota->id}/adjuntos",
        );

        return back()->with('success', 'Archivo adjuntado.');
    }

    public function destroy(Adjunto $adjunto): RedirectResponse
    {
        Gate::authorize('delete', $adjunto);

        $this->archivos->eliminar($adjunto);
        $adjunto->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    /**
     * Sirve el archivo tras verificar propiedad.
     *
     * La Policy sube por la relación polimórfica hasta la mascota, y la mascota
     * responde por el pivote. Nunca hay URL pública: adivinar el id no alcanza,
     * y eso es lo que se verifica en los tests de privacidad.
     */
    public function mostrar(Request $request, Adjunto $adjunto): StreamedResponse
    {
        Gate::authorize('view', $adjunto);

        $ruta = $adjunto->ruta;

        if ($request->boolean('min') && $adjunto->es_imagen) {
            $miniatura = $this->archivos->rutaMiniatura($ruta);

            if (Storage::exists($miniatura)) {
                $ruta = $miniatura;
            }
        }

        abort_unless(Storage::exists($ruta), 404);

        $nombre = $adjunto->nombre_original ?? basename($ruta);

        // Las imágenes se muestran en línea; los PDF se descargan salvo que se
        // los pida para ver.
        return $request->boolean('descargar')
            ? Storage::download($ruta, $nombre)
            : Storage::response($ruta, $nombre, [
                'Cache-Control' => 'private, max-age=86400',
            ]);
    }
}
