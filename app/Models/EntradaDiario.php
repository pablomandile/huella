<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use App\Policies\RegistroClinicoPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\EntradaDiarioFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bitácora libre: lo que no encaja en ningún módulo estructurado.
 *
 * "Hoy vomitó dos veces", "primera vez que sube solo al auto". Es la mitad
 * cotidiana del diario, y la que hace que la línea de tiempo se lea como la
 * vida de la mascota y no como una planilla clínica.
 *
 * @property int $id
 * @property int $mascota_id
 * @property CarbonImmutable $fecha
 * @property string|null $titulo
 * @property string $contenido
 * @property CategoriaEntrada $categoria
 * @property Animo|null $animo
 * @property-read Mascota $mascota
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class EntradaDiario extends Model implements PerteneceAMascota
{
    /** @use HasFactory<EntradaDiarioFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'entradas_diario';

    protected $fillable = [
        'fecha',
        'titulo',
        'contenido',
        'categoria',
        'animo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'categoria' => CategoriaEntrada::class,
            'animo' => Animo::class,
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
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'adjuntable');
    }

    /** El título si lo hay; si no, el arranque del contenido. */
    public function encabezado(): string
    {
        if ($this->titulo !== null && trim($this->titulo) !== '') {
            return $this->titulo;
        }

        return mb_strimwidth(trim($this->contenido), 0, 80, '…');
    }
}
