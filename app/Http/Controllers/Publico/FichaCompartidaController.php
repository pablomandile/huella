<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Adjunto;
use App\Models\EnlaceCompartido;
use App\Models\Mascota;
use App\Services\HistoriaClinicaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La ficha que se ve sin cuenta, con un enlace que el dueño creó y puede revocar.
 *
 * **Vistas Blade sueltas y no Inertia**, a diferencia del resto de la app. El
 * motivo no es comodidad: `HandleInertiaRequests::share()` inyecta `auth.user`,
 * la lista completa de mascotas del usuario y la mascota activa en los props de
 * **toda** página Inertia. Si el dueño abriera su propio enlace con la sesión
 * abierta —para chequear qué se ve—, todo eso viajaría dentro de una página
 * pública, quizá proyectada en la pantalla de una veterinaria. Con Blade no hay
 * props compartidas que defender. El precedente es `offline.blade.php`.
 *
 * Por lo mismo, regla dura en todo este camino: **ningún JsonResource, ningún
 * `auth()`, ningún `$request->user()`**. `RecordatorioResource`, por ejemplo,
 * llama a `$request->user()->hoyCalendario()` y sin sesión sería un 500.
 *
 * **El token y el adjunto se resuelven a mano, sin route model binding.** El
 * binding lo hace `SubstituteBindings`, que es middleware del grupo `web` y por
 * lo tanto corre **antes** que el middleware de esta ruta: un token inexistente
 * abortaría con 404 sin pasar nunca por `ProtegerFichaCompartida`, o sea sin los
 * headers de no-indexación y sin contar el fallo para el límite de intentos.
 */
class FichaCompartidaController extends Controller
{
    public function __construct(private readonly HistoriaClinicaService $historia) {}

    public function mostrar(string $token): View
    {
        [$enlace, $mascota] = $this->resolver($token);

        // Solo acá, y no en el PDF ni en cada imagen: si contara todo, "se abrió
        // 3 veces" pasaría a ser "se abrió 47 veces" y dejaría de significar algo.
        $enlace->increment('visitas', 1, ['ultimo_acceso_en' => now()]);

        return view('publico.ficha', [
            ...$this->historia->para($mascota),
            'enlace' => $enlace,
            // En la zona del dueño de la mascota: es la única persona con zona
            // conocida en todo este camino.
            'venceEl' => $mascota->propietario
                ->enSuZona($enlace->expira_en)
                ?->translatedFormat('j \d\e F \d\e Y'),
            'adjuntos' => $this->adjuntosVisibles($enlace, $mascota),
        ]);
    }

    /**
     * El mismo PDF que baja el dueño, con el mismo servicio y la misma vista.
     *
     * Con su propio throttle en la ruta: DomPDF sobre un historial largo es caro,
     * y sin tope es el vector de denegación de servicio más barato de la app.
     */
    public function pdf(string $token): Response
    {
        [, $mascota] = $this->resolver($token);

        return Pdf::loadView('pdf.historia-clinica', $this->historia->para($mascota))
            ->setPaper('a4')
            ->download($this->historia->nombreDeArchivo($mascota));
    }

    /** La foto de perfil, para confirmar que el papel es de este animal. */
    public function foto(Request $request, string $token): StreamedResponse
    {
        [, $mascota] = $this->resolver($token);

        abort_unless($mascota->foto_perfil !== null, 404);

        $miniatura = preg_replace('/\.webp$/', '-min.webp', $mascota->foto_perfil);
        $ruta = $request->boolean('min') && Storage::exists($miniatura)
            ? $miniatura
            : $mascota->foto_perfil;

        abort_unless(Storage::exists($ruta), 404);

        // El disco sigue siendo el privado y el archivo sigue saliendo por un
        // controlador: no hay URL directa al storage, que es lo que pide la
        // especificación. Lo que cambia es quién autoriza.
        //
        // Sin `Cache-Control` propio: lo pone el middleware, y es `no-store`, no
        // el `private, max-age=86400` de las rutas autenticadas.
        return Storage::response($ruta);
    }

    /**
     * Un adjunto, si este enlace lo alcanza.
     *
     * Toda la decisión vive en `EnlaceCompartido::alcanza()`, que reusa
     * `Adjunto::mascotaAsociada()` —la misma cadena que la Policy—. Acá no se
     * escribe autorización nueva: lo único que cambia respecto del camino
     * autenticado es quién es el sujeto.
     */
    public function adjunto(string $token, int $adjunto): StreamedResponse
    {
        [$enlace] = $this->resolver($token);

        $archivo = Adjunto::find($adjunto);

        abort_if($archivo === null, 404);
        abort_unless($enlace->alcanza($archivo), 404);
        abort_unless(Storage::exists($archivo->ruta), 404);

        return Storage::response($archivo->ruta, $archivo->nombre_original);
    }

    /**
     * Las tres guardas del enlace, en un solo lugar.
     *
     * @return array{0: EnlaceCompartido, 1: Mascota}
     */
    private function resolver(string $token): array
    {
        $enlace = EnlaceCompartido::query()->where('token', $token)->first();

        // Revocar es borrar la fila, así que un enlace revocado llega acá.
        abort_if($enlace === null, 404);

        // La mascota pudo darse de baja después de crear el enlace: el enlace
        // sigue existiendo y `->mascota` vuelve null. Sin esta guarda sería un
        // 500 en una ruta pública.
        $mascota = $enlace->mascota()->first();
        abort_if($mascota === null, 404);

        abort_if($enlace->vencido, 410);

        return [$enlace, $mascota];
    }

    /**
     * Los adjuntos que este enlace muestra, agrupados como los espera la vista.
     *
     * @return array{documentos: array<int, Adjunto>, clinicos: array<int, Adjunto>}
     */
    private function adjuntosVisibles(EnlaceCompartido $enlace, Mascota $mascota): array
    {
        $documentos = $mascota->adjuntos()->get()
            ->filter(fn (Adjunto $a) => $enlace->alcanza($a))
            ->values();

        $clinicos = $enlace->incluye_adjuntos
            ? $mascota->visitas()->with('adjuntos')->get()
                ->flatMap(fn ($visita) => $visita->adjuntos)
                ->filter(fn (Adjunto $a) => $enlace->alcanza($a))
                ->values()
            : collect();

        return [
            'documentos' => $documentos->all(),
            'clinicos' => $clinicos->all(),
        ];
    }
}
