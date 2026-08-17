<?php

namespace App\Models;

use App\Enums\SeveridadAlergia;
use App\Enums\TipoAlergia;
use Database\Factories\AlergiaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mascota_id
 * @property TipoAlergia $tipo
 * @property string $agente
 * @property SeveridadAlergia|null $severidad
 * @property Carbon|null $fecha_deteccion
 * @property string|null $sintomas
 * @property string|null $notas
 */
class Alergia extends Model
{
    /** @use HasFactory<AlergiaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'alergias';

    protected $fillable = ['tipo', 'agente', 'severidad', 'fecha_deteccion', 'sintomas', 'notas'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAlergia::class,
            'severidad' => SeveridadAlergia::class,
            'fecha_deteccion' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Mascota, $this>
     */
    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class);
    }
}
