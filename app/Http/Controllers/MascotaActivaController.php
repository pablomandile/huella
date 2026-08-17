<?php

namespace App\Http\Controllers;

use App\Models\Mascota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MascotaActivaController extends Controller
{
    /**
     * Cambia la mascota activa de la sesión. Con varias mascotas, define cuál
     * muestran el dashboard y las cargas rápidas.
     */
    public function update(Mascota $mascota): RedirectResponse
    {
        Gate::authorize('view', $mascota);

        session(['mascota_activa_id' => $mascota->id]);

        return back();
    }
}
