<?php

namespace App\Http\Requests;

class ActualizarMascotaRequest extends GuardarMascotaRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('mascota'));
    }
}
