<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Observers\AplicacionVacunaObserver;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\AplicacionVacunaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una dosis aplicada. Su `proxima_dosis` es lo que genera el recordatorio.
 *
 * @property int $id
 * @property int $mascota_id
 * @property int|null $vacuna_id
 * @property string|null $vacuna_libre
 * @property int|null $visita_id
 * @property int|null $veterinaria_id
 * @property int|null $veterinario_id
 * @property CarbonImmutable $fecha
 * @property int|null $dosis_nro
 * @property string|null $marca
 * @property string|null $lote
 * @property CarbonImmutable|null $vencimiento_lote
 * @property CarbonImmutable|null $proxima_dosis
 * @property string|null $reacciones
 * @property string|null $notas
 * @property-read Mascota $mascota
 * @property-read Vacuna|null $vacuna
 * @property-read string $nombre_vacuna
 */
#[ObservedBy(AplicacionVacunaObserver::class)]
#[UsePolicy(RegistroClinicoPolicy::class)]
class AplicacionVacuna extends Model implements PerteneceAMascota
{
    /** @use HasFactory<AplicacionVacunaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'aplicaciones_vacuna';

    protected $fillable = [
        'vacuna_id',
        'vacuna_libre',
        'visita_id',
        'veterinaria_id',
        'veterinario_id',
        'fecha',
        'dosis_nro',
        'marca',
        'lote',
        'vencimiento_lote',
        'proxima_dosis',
        'reacciones',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'dosis_nro' => 'integer',
            'vencimiento_lote' => 'date',
            'proxima_dosis' => 'date',
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
     * @return BelongsTo<Vacuna, $this>
     */
    public function vacuna(): BelongsTo
    {
        return $this->belongsTo(Vacuna::class);
    }

    /**
     * @return BelongsTo<Visita, $this>
     */
    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class);
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
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'adjuntable');
    }

    public function getNombreVacunaAttribute(): string
    {
        $delCatalogo = $this->getRelationValue('vacuna');

        if ($delCatalogo instanceof Vacuna) {
            return $delCatalogo->nombre;
        }

        return $this->vacuna_libre ?? 'Vacuna sin nombre';
    }
}
