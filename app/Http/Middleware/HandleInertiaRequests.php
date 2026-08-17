<?php

namespace App\Http\Middleware;

use App\Services\IngresoConGoogleService;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
