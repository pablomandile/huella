<?php

namespace App\Models;

use App\Enums\EstadoToma;
use Carbon\CarbonImmutable;
use Database\Factories\TomaMedicamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una toma concreta: este remedio, a esta hora, este día.
 *
 * Las genera `GeneradorTomasService` a partir del tratamiento. No tiene soft
 * deletes: si el tratamiento se va, sus tomas no significan nada solas.
 *
 * @property int $id
 * @property int $tratamiento_id
 * @property CarbonImmutable $fecha_hora_programada
 * @property CarbonImmutable|null $fecha_hora_real
 * @property EstadoToma $estado
 * @property string|null $notas
 * @property-read Tratamiento $tratamiento
 */
class TomaMedicamento extends Model
{
    /** @use HasFactory<TomaMedicamentoFactory> */
    use HasFactory;

    protected $table = 'tomas_medicamento';

    protected $fillable = [
        'fecha_hora_programada',
        'fecha_hora_real',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_programada' => 'datetime',
            'fecha_hora_real' => 'datetime',
            'estado' => EstadoToma::class,
        ];
    }

    /**
     * @return BelongsTo<Tratamiento, $this>
     */
    public function tratamiento(): BelongsTo
    {
        return $this->belongsTo(Tratamiento::class, 'tratamiento_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoToma::Pendiente;
    }
}
