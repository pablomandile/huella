<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\TipoDesparasitacion;
use App\Observers\DesparasitacionObserver;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\DesparasitacionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $mascota_id
 * @property int|null $medicamento_id
 * @property string|null $medicamento_libre
 * @property int|null $visita_id
 * @property TipoDesparasitacion $tipo
 * @property CarbonImmutable $fecha
 * @property string|null $dosis
 * @property string|null $peso_al_momento
 * @property CarbonImmutable|null $proxima_fecha
 * @property string|null $notas
 * @property-read Mascota $mascota
 * @property-read Medicamento|null $medicamento
 * @property-read string $nombre_medicamento
 */
#[ObservedBy(DesparasitacionObserver::class)]
#[UsePolicy(RegistroClinicoPolicy::class)]
class Desparasitacion extends Model implements PerteneceAMascota
{
    /** @use HasFactory<DesparasitacionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'desparasitaciones';

    protected $fillable = [
        'medicamento_id',
        'medicamento_libre',
        'visita_id',
        'tipo',
        'fecha',
        'dosis',
        'peso_al_momento',
        'proxima_fecha',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDesparasitacion::class,
            'fecha' => 'date',
            'peso_al_momento' => 'decimal:2',
            'proxima_fecha' => 'date',
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
     * @return BelongsTo<Medicamento, $this>
     */
    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medicamento::class);
    }

    /**
     * @return BelongsTo<Visita, $this>
     */
    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class);
    }

    public function getNombreMedicamentoAttribute(): string
    {
        $delCatalogo = $this->getRelationValue('medicamento');

        if ($delCatalogo instanceof Medicamento) {
            return $delCatalogo->nombre_comercial;
        }

        return $this->medicamento_libre ?? 'Antiparasitario sin nombre';
    }
}
