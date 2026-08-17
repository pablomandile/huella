<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\EstadoTratamiento;
use App\Enums\ViaAdministracion;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\TratamientoFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $mascota_id
 * @property int|null $visita_id
 * @property int|null $medicamento_id
 * @property string|null $medicamento_libre
 * @property string $dosis
 * @property ViaAdministracion $via
 * @property int|null $frecuencia_horas
 * @property int|null $veces_por_dia
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable|null $fecha_fin
 * @property int|null $duracion_dias
 * @property string|null $hora_primera_toma
 * @property EstadoTratamiento $estado
 * @property string|null $notas
 * @property-read Mascota $mascota
 * @property-read Medicamento|null $medicamento
 * @property-read string $nombre_medicamento
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class Tratamiento extends Model implements PerteneceAMascota
{
    /** @use HasFactory<TratamientoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'tratamientos';

    protected $fillable = [
        'visita_id',
        'medicamento_id',
        'medicamento_libre',
        'dosis',
        'via',
        'frecuencia_horas',
        'veces_por_dia',
        'fecha_inicio',
        'fecha_fin',
        'duracion_dias',
        'hora_primera_toma',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'via' => ViaAdministracion::class,
            'frecuencia_horas' => 'integer',
            'veces_por_dia' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'duracion_dias' => 'integer',
            'estado' => EstadoTratamiento::class,
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
     * @return BelongsTo<Visita, $this>
     */
    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class);
    }

    /**
     * @return BelongsTo<Medicamento, $this>
     */
    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medicamento::class);
    }

    /**
     * @return HasMany<TomaMedicamento, $this>
     */
    public function tomas(): HasMany
    {
        return $this->hasMany(TomaMedicamento::class, 'tratamiento_id')
            ->orderBy('fecha_hora_programada');
    }

    /**
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'adjuntable');
    }

    /**
     * Del catálogo o escrito a mano: para el usuario es lo mismo, el nombre del
     * remedio que le está dando.
     *
     * Puede quedarse sin ninguno de los dos si el medicamento del catálogo se
     * dio de baja después de prescribirlo.
     */
    public function getNombreMedicamentoAttribute(): string
    {
        // getRelationValue y no ->medicamento: respeta el eager loading igual,
        // pero no le miente a PHPStan sobre que el catálogo siempre está.
        $delCatalogo = $this->getRelationValue('medicamento');

        if ($delCatalogo instanceof Medicamento) {
            return $delCatalogo->nombre_comercial;
        }

        return $this->medicamento_libre ?? 'Medicamento sin nombre';
    }

    /**
     * Cada cuántas horas toca. `frecuencia_horas` manda; si solo hay veces por
     * día se reparte en 24 horas.
     */
    public function intervaloHoras(): ?int
    {
        if ($this->frecuencia_horas !== null && $this->frecuencia_horas > 0) {
            return $this->frecuencia_horas;
        }

        if ($this->veces_por_dia !== null && $this->veces_por_dia > 0) {
            return (int) max(1, intdiv(24, $this->veces_por_dia));
        }

        return null;
    }

    /**
     * Último día del tratamiento. `fecha_fin` explícita, o la que sale de la
     * duración en días. Sin ninguna de las dos es un tratamiento abierto.
     */
    public function ultimoDia(): ?CarbonImmutable
    {
        if ($this->fecha_fin !== null) {
            return $this->fecha_fin->toImmutable()->startOfDay();
        }

        if ($this->duracion_dias !== null && $this->duracion_dias > 0) {
            return $this->fecha_inicio->toImmutable()
                ->startOfDay()
                ->addDays($this->duracion_dias - 1);
        }

        return null;
    }
}
