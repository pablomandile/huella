<?php

namespace App\Models;

use App\Contracts\Catalogo;
use App\Models\Concerns\EsCatalogo;
use App\Policies\CatalogoPolicy;
use Database\Factories\VeterinarioFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property int|null $veterinaria_id
 * @property string $nombre
 * @property string|null $matricula
 * @property string|null $especialidad
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $notas
 * @property bool $activo
 * @property-read Veterinaria|null $veterinaria
 */
#[UsePolicy(CatalogoPolicy::class)]
class Veterinario extends Model implements Catalogo
{
    /** @use HasFactory<VeterinarioFactory> */
    use EsCatalogo, HasFactory, SoftDeletes;

    protected $table = 'veterinarios';

    protected $fillable = [
        'veterinaria_id',
        'nombre',
        'matricula',
        'especialidad',
        'telefono',
        'email',
        'notas',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Veterinaria, $this>
     */
    public function veterinaria(): BelongsTo
    {
        return $this->belongsTo(Veterinaria::class);
    }
}
