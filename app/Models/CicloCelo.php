<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\IntensidadCelo;
use App\Observers\CicloCeloObserver;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\CicloCeloFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un ciclo de celo. Solo aplica a hembras no castradas y vivas: lo controla
 * `Mascota::celo_visible`.
 *
 * @property int $id
 * @property int $mascota_id
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable|null $fecha_fin
 * @property int|null $duracion_dias
 * @property IntensidadCelo|null $intensidad
 * @property string|null $sintomas
 * @property bool $hubo_monta
 * @property CarbonImmutable|null $proxima_estimada
 * @property string|null $notas
 * @property-read Mascota $mascota
 */
#[ObservedBy(CicloCeloObserver::class)]
#[UsePolicy(RegistroClinicoPolicy::class)]
class CicloCelo extends Model implements PerteneceAMascota
{
    /** @use HasFactory<CicloCeloFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'ciclos_celo';

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'intensidad',
        'sintomas',
        'hubo_monta',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'duracion_dias' => 'integer',
            'intensidad' => IntensidadCelo::class,
            'hubo_monta' => 'boolean',
            'proxima_estimada' => 'date',
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
     * Cuántos días duró, si ya terminó.
     *
     * Inclusivo: un celo que empezó el 1º y terminó el 20 duró 20 días, no 19.
     * `duracion_dias` y `proxima_estimada` no son fillable —los calcula el
     * sistema, no el usuario— y los escribe el observer.
     */
    public function diasDeDuracion(): ?int
    {
        if ($this->fecha_fin === null) {
            return null;
        }

        return (int) $this->fecha_inicio->diffInDays($this->fecha_fin) + 1;
    }

    public function estaEnCurso(): bool
    {
        return $this->fecha_fin === null;
    }
}
