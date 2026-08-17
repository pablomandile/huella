<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarAlergiaRequest;
use App\Models\Alergia;
use App\Models\Mascota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlergiaController extends Controller
{
    public function store(GuardarAlergiaRequest $request, Mascota $mascota): RedirectResponse
    {
        $mascota->alergias()->create($request->validated());

        return back()->with('success', 'Alergia registrada.');
    }

    public function destroy(Request $request, Mascota $mascota, Alergia $alergia): RedirectResponse
    {
        Gate::authorize('update', $mascota);

        abort_unless($alergia->mascota_id === $mascota->id, 404);

        $alergia->delete(); // soft delete

        return back()->with('success', 'Alergia eliminada.');
    }
}
