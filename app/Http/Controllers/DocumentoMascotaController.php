<?php

namespace App\Http\Controllers;

use App\Enums\TipoAdjunto;
use App\Http\Requests\GuardarDocumentoMascotaRequest;
use App\Http\Requests\GuardarVencimientoRabiaRequest;
use App\Models\Mascota;
use App\Services\ArchivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * La libreta sanitaria y el certificado de rabia de una mascota.
 *
 * Son adjuntos colgados de la mascota, no de una visita: no pertenecen a un
 * episodio clínico, valen para toda su vida y son lo que el dueño muestra en un
 * veterinario nuevo, en un viaje o en una guardería.
 *
 * El borrado va por `AdjuntoController::destroy`, que ya resuelve la propiedad
 * subiendo por la relación polimórfica. No hace falta uno propio.
 */
class DocumentoMascotaController extends Controller
{
    public function __construct(private readonly ArchivoService $archivos) {}

    public function store(
        GuardarDocumentoMascotaRequest $request,
        Mascota $mascota,
    ): RedirectResponse {
        $tipo = TipoAdjunto::from($request->validated('tipo'));
        $descripcion = $request->validated('descripcion');

        /*
         * `file()` devuelve un solo archivo, un array o null según lo que llegue.
         * `Arr::wrap` deja siempre un array, sin suponer la forma del request.
         */
        $archivos = Arr::wrap($request->file('archivos'));

        $cantidad = count($archivos);
        abort_if($cantidad === 0, 422, 'No llegó ningún archivo.');

        /*
         * En una transacción: si el archivo 4 de 6 falla, no queda media libreta
         * cargada sin que nadie se entere de qué falta. Los archivos ya escritos
         * en disco quedan huérfanos —Storage no participa del rollback—, y es el
         * lado bueno para equivocarse: sobra un archivo, no falta un registro.
         */
        DB::transaction(function () use ($mascota, $tipo, $archivos, $descripcion): void {
            foreach ($archivos as $archivo) {
                $this->archivos->adjuntar(
                    $mascota->adjuntos(),
                    $archivo,
                    $tipo,
                    $descripcion,
                    "mascotas/{$mascota->id}/documentos",
                );
            }
        });

        return back()->with(
            'success',
            $cantidad === 1
                ? 'Archivo agregado a '.mb_strtolower($tipo->etiqueta()).'.'
                : "Se agregaron {$cantidad} archivos a ".mb_strtolower($tipo->etiqueta()).'.',
        );
    }

    /**
     * La fecha de vencimiento del certificado.
     *
     * No escribe el recordatorio: lo hace `MascotaObserver` al detectar que la
     * columna cambió. Así vale igual si la fecha entra por acá, por un seeder o
     * por un import futuro.
     */
    public function vencimientoRabia(
        GuardarVencimientoRabiaRequest $request,
        Mascota $mascota,
    ): RedirectResponse {
        $fecha = $request->validated('rabia_vencimiento');

        $mascota->update(['rabia_vencimiento' => $fecha]);

        return back()->with(
            'success',
            $fecha === null
                ? 'Se quitó el vencimiento del certificado.'
                : 'Vencimiento guardado.',
        );
    }
}
