<?php

namespace App\Http\Middleware;

use App\Services\IngresoConGoogleService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle the incoming request.
     *
     * Una URL de Inertia devuelve dos cuerpos distintos según el header
     * `X-Inertia`: el HTML de arranque, o el JSON de la página. Lo único que las
     * distingue para una caché es `Vary: X-Inertia` —que Inertia setea, pero el
     * CDN de Hostinger descarta al comprimir con brotli—. Sin ese header, y con
     * el `Cache-Control: no-cache` que Symfony pone por defecto, el navegador
     * guarda el JSON bajo la URL de la página. Cuando Chrome descarta una
     * pestaña inactiva y después la restaura, esa navegación es de historial y
     * reusa la entrada guardada sin revalidar: aparece el JSON crudo en pantalla
     * y la app no arranca. Un F5 lo tapa porque revalida.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // Inertia lo pone y el CDN lo borra, pero se declara igual: es lo
        // correcto y sirve en cualquier intermediario que sí lo respete.
        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        /*
         * `no-store`, no `no-cache`: `no-cache` permite guardar y solo obliga a
         * revalidar, y una navegación de historial saltea la revalidación.
         *
         * Y solo sobre la respuesta XHR, **nunca** sobre el HTML: `no-store` en
         * el documento principal desactiva el back/forward cache de Chrome y
         * convierte cada "atrás" en una ida completa a la red. El JSON nunca se
         * beneficia del bfcache, así que acá no cuesta nada.
         */
        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Sin credenciales de Google el botón no se muestra: sus rutas dan
            // 404, así que ofrecerlo sería mandar al usuario a una pared.
            'googleHabilitado' => IngresoConGoogleService::configurado(),
            // Lista liviana para el selector de mascota activa del header.
            // Lazy: solo se evalúa cuando hay sesión.
            'mascotas' => fn () => $request->user()
                ? $request->user()->mascotas()
                    ->orderBy('nombre')
                    ->get()
                    ->map(fn ($mascota) => [
                        'id' => $mascota->id,
                        'nombre' => $mascota->nombre,
                        'especie' => $mascota->especie->value,
                        'foto_miniatura_url' => $mascota->foto_perfil
                            ? route('mascotas.foto-perfil', [
                                'mascota' => $mascota->id,
                                'min' => 1,
                                'v' => $mascota->updated_at?->timestamp,
                            ])
                            : null,
                    ])
                    ->all()
                : [],
            'mascotaActivaId' => fn () => $request->user()
                ? session('mascota_activa_id')
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
