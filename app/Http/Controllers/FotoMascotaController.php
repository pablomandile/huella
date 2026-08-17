<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarFotoMascotaRequest;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Services\ImagenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FotoMascotaController extends Controller
{
    public function __construct(private readonly ImagenService $imagenes) {}

    public function store(GuardarFotoMascotaRequest $request, Mascota $mascota): RedirectResponse
    {
        $rutas = $this->imagenes->guardar($request->file('foto'), "mascotas/{$mascota->id}");

        $mascota->fotos()->create([
            'ruta' => $rutas['ruta'],
            'ruta_miniatura' => $rutas['ruta_miniatura'],
            'fecha' => $request->validated('fecha'),
            'epigrafe' => $request->validated('epigrafe'),
        ]);

        return back()->with('success', 'Foto agregada a la galería.');
    }

    public function destroy(Request $request, Mascota $mascota, FotoMascota $foto): RedirectResponse
    {
        Gate::authorize('update', $mascota);

        abort_unless($foto->mascota_id === $mascota->id, 404);

        $foto->delete();

        // El archivo se conserva si es la foto de perfil vigente o si otra
        // entrada de la galería lo comparte.
        $compartida = $mascota->foto_perfil === $foto->ruta
            || $mascota->fotos()->where('ruta', $foto->ruta)->exists();

        if (! $compartida) {
            $this->imagenes->eliminar($foto->ruta, $foto->ruta_miniatura);
        }

        return back()->with('success', 'Foto eliminada.');
    }

    /**
     * Sirve la imagen tras verificar propiedad. Nunca hay URL pública directa:
     * es el requisito de privacidad de la especificación.
     */
    public function mostrar(Request $request, Mascota $mascota, FotoMascota $foto): StreamedResponse
    {
        Gate::authorize('view', $mascota);

        abort_unless($foto->mascota_id === $mascota->id, 404);

        $ruta = $request->boolean('min') && $foto->ruta_miniatura
            ? $foto->ruta_miniatura
            : $foto->ruta;

        abort_unless(Storage::exists($ruta), 404);

        return Storage::response($ruta, headers: ['Cache-Control' => 'private, max-age=86400']);
    }

    public function fotoPerfil(Request $request, Mascota $mascota): StreamedResponse
    {
        Gate::authorize('view', $mascota);

        abort_unless($mascota->foto_perfil !== null, 404);

        $ruta = $request->boolean('min')
            ? preg_replace('/\.webp$/', '-min.webp', $mascota->foto_perfil)
            : $mascota->foto_perfil;

        if (! Storage::exists($ruta)) {
            $ruta = $mascota->foto_perfil; // sin miniatura, cae a la principal
        }

        abort_unless(Storage::exists($ruta), 404);

        return Storage::response($ruta, headers: ['Cache-Control' => 'private, max-age=86400']);
    }
}
