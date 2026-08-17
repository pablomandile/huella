<?php

namespace App\Models;

use App\Contracts\PerteneceAMascota;
use App\Enums\TipoAdjunto;
use App\Policies\RegistroClinicoPolicy;
use Database\Factories\AdjuntoFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Archivo colgado de cualquier registro: una receta en la visita, un análisis
 * en un tratamiento y, más adelante, un certificado en una vacuna.
 *
 * Vive en el disco privado y se sirve por controlador. `AdjuntoPolicy` resuelve
 * la propiedad subiendo por `adjuntable` hasta la mascota.
 *
 * @property int $id
 * @property string $adjuntable_type
 * @property int $adjuntable_id
 * @property TipoAdjunto $tipo
 * @property string $ruta
 * @property string|null $nombre_original
 * @property string|null $mime
 * @property int|null $tamanio_bytes
 * @property string|null $descripcion
 * @property-read bool $es_imagen
 * @property-read string|null $tamanio_legible
 */
#[UsePolicy(RegistroClinicoPolicy::class)]
class Adjunto extends Model implements PerteneceAMascota
{
    /** @use HasFactory<AdjuntoFactory> */
    use HasFactory;

    protected $table = 'adjuntos';

    protected $fillable = [
        'tipo',
        'ruta',
        'nombre_original',
        'mime',
        'tamanio_bytes',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAdjunto::class,
            'tamanio_bytes' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function adjuntable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * La mascota de la que, en el fondo, es este archivo. Es lo que mira la
     * Policy para decidir.
     */
    public function mascotaAsociada(): ?Mascota
    {
        $duenio = $this->adjuntable;

        return match (true) {
            $duenio instanceof Mascota => $duenio,
            // Visita y Tratamiento cuelgan de una mascota; las entidades que se
            // sumen en las fases siguientes hacen lo mismo.
            $duenio instanceof PerteneceAMascota => $duenio->mascotaAsociada(),
            default => null,
        };
    }

    /**
     * Las imágenes se previsualizan; los PDF se descargan.
     *
     * @return Attribute<bool, never>
     */
    protected function esImagen(): Attribute
    {
        return Attribute::get(fn (): bool => str_starts_with((string) $this->mime, 'image/'));
    }

    public function getTamanioLegibleAttribute(): ?string
    {
        if ($this->tamanio_bytes === null) {
            return null;
        }

        $mb = $this->tamanio_bytes / 1_048_576;

        return $mb >= 1
            ? number_format($mb, 1, ',', '.').' MB'
            : max(1, (int) round($this->tamanio_bytes / 1024)).' KB';
    }
}
