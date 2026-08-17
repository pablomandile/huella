<?php

namespace App\Models;

use App\Contracts\Catalogo;
use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use App\Models\Concerns\EsCatalogo;
use App\Policies\CatalogoPolicy;
use Database\Factories\AlimentoFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property string|null $marca
 * @property string $nombre
 * @property TipoAlimento $tipo
 * @property GamaAlimento|null $gama
 * @property Especie $especie
 * @property EtapaVida $etapa
 * @property string|null $presentacion
 * @property bool $medicado
 * @property string|null $notas
 */
#[UsePolicy(CatalogoPolicy::class)]
class Alimento extends Model implements Catalogo
{
    /** @use HasFactory<AlimentoFactory> */
    use EsCatalogo, HasFactory, SoftDeletes;

    protected $table = 'alimentos';

    protected $fillable = [
        'marca',
        'nombre',
        'tipo',
        'gama',
        'especie',
        'etapa',
        'presentacion',
        'medicado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAlimento::class,
            'gama' => GamaAlimento::class,
            'especie' => Especie::class,
            'etapa' => EtapaVida::class,
            'medicado' => 'boolean',
        ];
    }
}
