<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\OrigenPeso;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\RegistroPesoFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mascota_id
 * @property int|null $visita_id
 * @property CarbonImmutable $fecha
 * @property string $peso_kg
 * @property int|null $condicion_corporal
 * @property OrigenPeso $origen
 * @property string|null $notas
 * @property-read Mascota $mascota
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class RegistroPeso extends Model implements PerteneceAMascota
{
    /** @use HasFactory<RegistroPesoFactory> */
    use HasFactory;

    protected $table = 'registros_peso';

    protected $fillable = [
        'visita_id',
        'fecha',
        'peso_kg',
        'condicion_corporal',
        'origen',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'peso_kg' => 'decimal:2',
            'condicion_corporal' => 'integer',
            'origen' => OrigenPeso::class,
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

    public function kilos(): float
    {
        return (float) $this->peso_kg;
    }
}
