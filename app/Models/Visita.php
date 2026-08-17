<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\TipoVisita;
use App\Observers\VisitaObserver;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\VisitaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
 * @property int|null $veterinaria_id
 * @property int|null $veterinario_id
 * @property CarbonImmutable $fecha_hora
 * @property TipoVisita $tipo
 * @property string|null $motivo
 * @property string|null $diagnostico
 * @property string|null $indicaciones
 * @property string|null $temperatura
 * @property string|null $costo
 * @property string $moneda
 * @property CarbonImmutable|null $proximo_control
 * @property string|null $notas
 * @property-read Mascota $mascota
 */
#[ObservedBy(VisitaObserver::class)]
#[UsePolicy(RegistroClinicoPolicy::class)]
class Visita extends Model implements PerteneceAMascota
{
    /** @use HasFactory<VisitaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'visitas';

    protected $fillable = [
        'veterinaria_id',
        'veterinario_id',
        'fecha_hora',
        'tipo',
        'motivo',
        'diagnostico',
        'indicaciones',
        'temperatura',
        'costo',
        'moneda',
        'proximo_control',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'tipo' => TipoVisita::class,
            'temperatura' => 'decimal:1',
            'costo' => 'decimal:2',
            'proximo_control' => 'date',
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
     * @return BelongsTo<Veterinaria, $this>
     */
    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }

    /**
     * @return BelongsTo<Veterinario, $this>
     */
    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(Veterinario::class);
    }

    /**
     * @return HasMany<Tratamiento, $this>
     */
    public function tratamientos(): HasMany
    {
        return $this->hasMany(Tratamiento::class);
    }

    /**
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'adjuntable');
    }
}
