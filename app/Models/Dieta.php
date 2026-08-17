<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\DietaFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Qué come la mascota y desde cuándo.
 *
 * `fecha_fin` NULL significa vigente, y **solo puede haber una vigente por
 * mascota** (regla de negocio 1). Eso lo garantiza `DietaService` dentro de una
 * transacción: la base no puede, porque MySQL admite múltiples NULL en un
 * índice único.
 *
 * @property int $id
 * @property int $mascota_id
 * @property int $alimento_id
 * @property int|null $veterinario_id
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable|null $fecha_fin
 * @property int|null $racion_diaria_g
 * @property int|null $tomas_por_dia
 * @property string|null $motivo
 * @property bool $prescripta
 * @property string|null $notas
 * @property-read Mascota $mascota
 * @property-read Alimento $alimento
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class Dieta extends Model implements PerteneceAMascota
{
    /** @use HasFactory<DietaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'dietas';

    protected $fillable = [
        'alimento_id',
        'veterinario_id',
        'fecha_inicio',
        'fecha_fin',
        'racion_diaria_g',
        'tomas_por_dia',
        'motivo',
        'prescripta',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'racion_diaria_g' => 'integer',
            'tomas_por_dia' => 'integer',
            'prescripta' => 'boolean',
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
     * @return BelongsTo<Alimento, $this>
     */
    public function alimento(): BelongsTo
    {
        return $this->belongsTo(Alimento::class);
    }

    /**
     * @return BelongsTo<Veterinario, $this>
     */
    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(Veterinario::class);
    }

    public function estaVigente(): bool
    {
        return $this->fecha_fin === null;
    }

    /**
     * @param  Builder<Dieta>  $consulta
     * @return Builder<Dieta>
     */
    public function scopeVigente(Builder $consulta): Builder
    {
        return $consulta->whereNull('fecha_fin');
    }
}
