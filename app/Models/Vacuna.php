<?php

namespace App\Models;

use App\Contracts\Catalogo;
use App\Enums\Especie;
use App\Models\Concerns\EsCatalogo;
use App\Policies\CatalogoPolicy;
use Database\Factories\VacunaFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property string $nombre
 * @property Especie $especie
 * @property string|null $descripcion
 * @property int|null $meses_refuerzo
 * @property bool $obligatoria
 */
#[UsePolicy(CatalogoPolicy::class)]
class Vacuna extends Model implements Catalogo
{
    /** @use HasFactory<VacunaFactory> */
    use EsCatalogo, HasFactory, SoftDeletes;

    protected $table = 'vacunas';

    protected $fillable = [
        'nombre',
        'especie',
        'descripcion',
        'meses_refuerzo',
        'obligatoria',
    ];

    protected function casts(): array
    {
        return [
            'especie' => Especie::class,
            'meses_refuerzo' => 'integer',
            'obligatoria' => 'boolean',
        ];
    }
}
