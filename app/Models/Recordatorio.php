<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\EstadoRecordatorio;
use App\Enums\TipoRecordatorio;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\RecordatorioFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Todo lo que hay que recordar, en una sola tabla.
 *
 * Los genera `GeneradorRecordatoriosService` desde los observers de la entidad
 * que los origina —nunca un controlador— y son idempotentes por
 * `origen_type` + `origen_id` + `tipo`.
 *
 * @property int $id
 * @property int $mascota_id
 * @property TipoRecordatorio $tipo
 * @property string $titulo
 * @property string|null $descripcion
 * @property CarbonImmutable $fecha_objetivo
 * @property int $dias_anticipacion
 * @property string $hora_notificacion
 * @property bool $recurrente
 * @property int|null $intervalo_dias
 * @property EstadoRecordatorio $estado
 * @property CarbonImmutable|null $fecha_completado
 * @property string|null $origen_type
 * @property int|null $origen_id
 * @property-read Mascota $mascota
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class Recordatorio extends Model implements PerteneceAMascota
{
    /** @use HasFactory<RecordatorioFactory> */
    use HasFactory;

    protected $table = 'recordatorios';

    protected $fillable = [
        'tipo',
        'titulo',
        'descripcion',
        'fecha_objetivo',
        'dias_anticipacion',
        'hora_notificacion',
        'recurrente',
        'intervalo_dias',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRecordatorio::class,
            'fecha_objetivo' => 'date',
            'dias_anticipacion' => 'integer',
            'recurrente' => 'boolean',
            'intervalo_dias' => 'integer',
            'estado' => EstadoRecordatorio::class,
            'fecha_completado' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Mascota, $this>
     */
    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class);
    }

    public function mascotaAsociada(): ?Mascota
    {
        return $this->mascota;
    }

    /**
     * El registro que lo originó: la aplicación de vacuna, la visita, el ciclo.
     * Sirve para llevar al usuario al lugar donde puede resolverlo.
     *
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A partir de qué día empieza a avisar.
     *
     * Es la fecha objetivo menos la anticipación: una vacuna del 15 con 15 días
     * de aviso empieza a molestar el 1º.
     */
    public function desdeCuandoAvisa(): CarbonImmutable
    {
        return $this->fecha_objetivo->toImmutable()
            ->startOfDay()
            ->subDays($this->dias_anticipacion);
    }

    /**
     * Los que siguen abiertos: pendientes o ya avisados. Que haya salido el
     * mail no significa que la vacuna se haya dado.
     *
     * @param  Builder<Recordatorio>  $consulta
     * @return Builder<Recordatorio>
     */
    public function scopeAbiertos(Builder $consulta): Builder
    {
        return $consulta->whereIn('estado', EstadoRecordatorio::abiertos());
    }
}
