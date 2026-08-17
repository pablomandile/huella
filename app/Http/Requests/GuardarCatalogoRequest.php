<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los cinco catálogos.
 *
 * El alta y la edición validan lo mismo, así que comparten reglas; lo único
 * que cambia es quién puede: crear siempre se puede, editar solo lo propio.
 * Cuando la ruta no trae registro estamos en un alta, y `authorize()` pasa.
 *
 * Los registros semilla nunca llegan acá con permiso: `CatalogoPolicy::update`
 * los rechaza, y por eso la UI ofrece duplicarlos en vez de editarlos.
 */
abstract class GuardarCatalogoRequest extends FormRequest
{
    /** Nombre del parámetro de ruta del registro que se está editando. */
    abstract protected function parametro(): string;

    public function authorize(): bool
    {
        $registro = $this->route($this->parametro());

        return $registro === null || $this->user()->can('update', $registro);
    }

    /**
     * La gente escribe "vetgreta.com.ar", no "https://vetgreta.com.ar".
     * Rechazarlo por eso sería castigar al usuario por una formalidad.
     */
    protected function normalizarSitioWeb(): void
    {
        $sitio = $this->input('sitio_web');

        if (is_string($sitio) && $sitio !== '' && ! preg_match('#^https?://#i', $sitio)) {
            $this->merge(['sitio_web' => 'https://'.$sitio]);
        }
    }
}
