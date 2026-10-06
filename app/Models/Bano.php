<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\LugarBano;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\BanoFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $mascota_id
 * @property CarbonImmutable $fecha
 * @property LugarBano $lugar
 * @property string|null $notas
 * @property-read Mascota $mascota
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class Bano extends Model implements PerteneceAMascota
{
    /** @use HasFactory<BanoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'banos';

    protected $fillable = [
        'fecha',
        'lugar',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'lugar' => LugarBano::class,
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
}
