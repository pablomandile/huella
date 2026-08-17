<?php

namespace App\Services;

use App\Enums\TipoAdjunto;
use App\Models\Mascota;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Da de alta una visita con todo lo que salió de ella en un solo movimiento.
 *
 * Es el criterio de la fase: una consulta por gastroenteritis con dos remedios
 * y la receta sacada con la cámara tiene que entrar sin salir de la pantalla,
 * porque se carga parado en el mostrador de la veterinaria.
 *
 * Todo dentro de una transacción: media visita cargada es peor que ninguna.
 */
class RegistroVisitaService
{
    public function __construct(
        private readonly GeneradorTomasService $tomas,
        private readonly ArchivoService $archivos,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $tratamientos
     * @param  list<array{archivo: UploadedFile, tipo: string|null, descripcion: string|null}>  $adjuntos
     */
    public function crear(
        Mascota $mascota,
        array $datos,
        array $tratamientos = [],
        array $adjuntos = [],
    ): Visita {
        $rutas = [];

        try {
            return DB::transaction(function () use ($mascota, $datos, $tratamientos, $adjuntos, &$rutas) {
                $visita = $mascota->visitas()->create(
                    $this->conFechaEnUtc($datos, $mascota),
                );

                foreach ($tratamientos as $datosTratamiento) {
                    $this->agregarTratamiento($visita, $datosTratamiento);
                }

                foreach ($adjuntos as $adjunto) {
                    $rutas[] = $this->archivos->adjuntar(
                        $visita->adjuntos(),
                        $adjunto['archivo'],
                        TipoAdjunto::tryFrom($adjunto['tipo'] ?? '') ?? TipoAdjunto::Otro,
                        $adjunto['descripcion'] ?? null,
                        "mascotas/{$mascota->id}/adjuntos",
                    )->ruta;
                }

                return $visita;
            });
        } catch (Throwable $error) {
            // Si la transacción se cayó, los archivos ya escritos quedarían
            // huérfanos: no hay fila que los referencie ni forma de llegar a ellos.
            $this->limpiar($rutas);

            throw $error;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Visita $visita, array $datos): Visita
    {
        $visita->update($this->conFechaEnUtc($datos, $visita->mascota));

        return $visita;
    }

    /**
     * Un tratamiento nuevo, con sus tomas ya programadas.
     *
     * @param  array<string, mixed>  $datos
     */
    public function agregarTratamiento(Visita $visita, array $datos): Tratamiento
    {
        // Se crea desde la mascota y no desde la visita: `mascota_id` no es
        // asignable en masa —es la FK de la que depende toda la autorización— y
        // la relación es la que la completa.
        $tratamiento = $visita->mascota->tratamientos()->make($datos);
        $tratamiento->visita_id = $visita->id;
        $tratamiento->save();

        $tratamiento->setRelation('mascota', $visita->mascota);
        $this->tomas->generar($tratamiento);

        return $tratamiento;
    }

    /**
     * La fecha llega de un `datetime-local`, que no trae zona: es la del
     * propietario de la mascota. Se usa esa y no la de quien carga para que
     * todos los registros de una mascota queden en la misma referencia,
     * incluso cuando en v2 la cargue un cuidador desde otro país.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conFechaEnUtc(array $datos, Mascota $mascota): array
    {
        if (! array_key_exists('fecha_hora', $datos)) {
            return $datos;
        }

        $fecha = $datos['fecha_hora'];

        $datos['fecha_hora'] = is_string($fecha)
            ? $this->duenio($mascota)->aUtc($fecha)
            : $fecha;

        return $datos;
    }

    private function duenio(Mascota $mascota): User
    {
        return $mascota->propietario;
    }

    /**
     * @param  list<string>  $rutas
     */
    private function limpiar(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            Storage::delete([$ruta, $this->archivos->rutaMiniatura($ruta)]);
        }
    }
}
