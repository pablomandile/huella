<?php

namespace App\Models;

use App\Contracts\Catalogo;
use App\Enums\CategoriaMedicamento;
use App\Models\Concerns\EsCatalogo;
use App\Policies\CatalogoPolicy;
use Database\Factories\MedicamentoFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property string $nombre_comercial
 * @property string|null $droga
 * @property string|null $laboratorio
 * @property string|null $presentacion
 * @property CategoriaMedicamento $categoria
 * @property bool $requiere_receta
 * @property string|null $notas
 */
#[UsePolicy(CatalogoPolicy::class)]
class Medicamento extends Model implements Catalogo
{
    /** @use HasFactory<MedicamentoFactory> */
    use EsCatalogo, HasFactory, SoftDeletes;

    protected $table = 'medicamentos';

    protected $fillable = [
        'nombre_comercial',
        'droga',
        'laboratorio',
        'presentacion',
        'categoria',
        'requiere_receta',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaMedicamento::class,
            'requiere_receta' => 'boolean',
        ];
    }
}
