<?php

namespace App\Models;

use App\Contracts\Catalogo;
use App\Models\Concerns\EsCatalogo;
use App\Policies\CatalogoPolicy;
use Database\Factories\VeterinariaFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property string $nombre
 * @property string|null $direccion
 * @property string|null $localidad
 * @property string|null $telefono
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $sitio_web
 * @property string|null $horarios
 * @property bool $urgencias_24h
 * @property string|null $notas
 * @property bool $activa
 */
#[UsePolicy(CatalogoPolicy::class)]
class Veterinaria extends Model implements Catalogo
{
    /** @use HasFactory<VeterinariaFactory> */
    use EsCatalogo, HasFactory, SoftDeletes;

    protected $table = 'veterinarias';

    protected $fillable = [
        'nombre',
        'direccion',
        'localidad',
        'telefono',
        'whatsapp',
        'email',
        'sitio_web',
        'horarios',
        'urgencias_24h',
        'notas',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'urgencias_24h' => 'boolean',
            'activa' => 'boolean',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
        ];
    }

    /**
     * @return HasMany<Veterinario, $this>
     */
    public function veterinarios(): HasMany
    {
        return $this->hasMany(Veterinario::class);
    }
}
