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
 * @property string|null $foto
 * @property bool $medicado
 * @property string|null $notas
 * @property-read string|null $ruta_foto_miniatura
 */
#[UsePolicy(CatalogoPolicy::class)]
class Alimento extends Model implements Catalogo
{
    /** @use HasFactory<AlimentoFactory> */
    use EsCatalogo, HasFactory, SoftDeletes;

    protected $table = 'alimentos';

    /**
     * `foto` **no** es fillable: es una ruta del disco privado que escribe
     * `ImagenService`, no un valor que llegue de un formulario. Igual que
     * `mascotas.foto_perfil`.
     */
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

    /**
     * La miniatura se deriva del nombre, no se guarda en otra columna: siempre
     * viven juntas y una segunda columna solo agregaría una forma de que queden
     * desincronizadas.
     */
    public function getRutaFotoMiniaturaAttribute(): ?string
    {
        return $this->foto === null
            ? null
            : preg_replace('/\.webp$/', '-min.webp', $this->foto);
    }
}
