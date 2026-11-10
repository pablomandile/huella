<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TamanoLetraUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class TamanoLetraController extends Controller
{
    /**
     * Guarda el tamaño de letra en **los dos lados**: la cookie evita el salto
     * de tamaño en la próxima carga —el servidor la lee antes de renderizar—, y
     * la columna hace que la preferencia siga a la persona a otro teléfono.
     *
     * **No pide sesión**: sin sesión queda solo la cookie. En la demo tampoco
     * se toca la cuenta, que es compartida (ver HandleTamanoLetra).
     */
    public function update(TamanoLetraUpdateRequest $request): RedirectResponse
    {
        $tamano = $request->tamano();

        if (! config('demo.enabled')) {
            $request->user()?->forceFill(['tamano_letra' => $tamano])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listo, cambiamos el tamaño de la letra.']);

        return back()->withCookie(Cookie::forever('tamano_letra', $tamano->value));
    }
}
