<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una operación de la cola sin conexión ya aplicada. Ver la migración.
 *
 * No tiene Policy: no se lee ni se escribe desde ninguna ruta, solo la usa
 * `SincronizacionService` para no aplicar dos veces la misma operación.
 *
 * @property int $usuario_id
 * @property string $op_id
 * @property string $entidad
 * @property string $accion
 * @property int|null $destino_id
 */
class Sincronizacion extends Model
{
    protected $table = 'sincronizaciones';

    protected $fillable = [
        'usuario_id',
        'op_id',
        'entidad',
        'accion',
        'destino_id',
    ];
}
