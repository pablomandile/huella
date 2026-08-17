<?php

namespace App\Models;

use Database\Factories\FotoMascotaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mascota_id
 * @property string $ruta
 * @property string|null $ruta_miniatura
 * @property Carbon $fecha
 * @property string|null $epigrafe
 */
class FotoMascota extends Model
{
    /** @use HasFactory<FotoMascotaFactory> */
    use HasFactory;

    protected $table = 'fotos_mascota';

    protected $fillable = ['ruta', 'ruta_miniatura', 'fecha', 'epigrafe'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Mascota, $this>
     */
    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class);
    }
}
