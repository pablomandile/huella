<?php

namespace App\Models;

use App\Enums\Especie;
use App\Enums\RolCuidador;
use App\Enums\Sexo;
use App\Enums\TipoPelaje;
use App\Observers\MascotaObserver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\MascotaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property string $nombre
 * @property Especie $especie
 * @property string|null $raza
 * @property Sexo $sexo
 * @property CarbonImmutable|null $fecha_nacimiento
 * @property bool $fecha_nacimiento_estimada
 * @property CarbonImmutable|null $fecha_adopcion
 * @property string|null $color
 * @property TipoPelaje|null $tipo_pelaje
 * @property string|null $senias_particulares
 * @property string|null $descripcion
 * @property string|null $foto_perfil
 * @property string|null $microchip
 * @property CarbonImmutable|null $fecha_microchip
 * @property string|null $libreta_sanitaria
 * @property string|null $pedigree
 * @property bool $castrado
 * @property CarbonImmutable|null $fecha_castracion
 * @property string|null $seguro_compania
 * @property string|null $seguro_poliza
 * @property CarbonImmutable|null $seguro_vencimiento
 * @property bool $activo
 * @property CarbonImmutable|null $fecha_fallecimiento
 * @property-read string|null $edad
 * @property-read bool $celo_visible
 * @property-read bool $fallecida
 */
#[ObservedBy(MascotaObserver::class)]
class Mascota extends Model
{
    /** @use HasFactory<MascotaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'mascotas';

    protected $fillable = [
        'nombre',
        'especie',
        'raza',
        'sexo',
        'fecha_nacimiento',
        'fecha_nacimiento_estimada',
        'fecha_adopcion',
        'color',
        'tipo_pelaje',
        'senias_particulares',
        'descripcion',
        'microchip',
        'fecha_microchip',
        'libreta_sanitaria',
        'pedigree',
        'castrado',
        'fecha_castracion',
        'seguro_compania',
        'seguro_poliza',
        'seguro_vencimiento',
        'fecha_fallecimiento',
    ];

    protected function casts(): array
    {
        return [
            'especie' => Especie::class,
            'sexo' => Sexo::class,
            'tipo_pelaje' => TipoPelaje::class,
            'fecha_nacimiento' => 'date',
            'fecha_nacimiento_estimada' => 'boolean',
            'fecha_adopcion' => 'date',
            'fecha_microchip' => 'date',
            'castrado' => 'boolean',
            'fecha_castracion' => 'date',
            'seguro_vencimiento' => 'date',
            'activo' => 'boolean',
            'fecha_fallecimiento' => 'date',
        ];
    }

    /**
     * Dueño original (quien la dio de alta).
     *
     * @return BelongsTo<User, $this>
     */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Todos los usuarios con acceso, con su rol en el pivote.
     * La autorización pasa SIEMPRE por acá, nunca por usuario_id.
     *
     * @return BelongsToMany<User, $this>
     */
    public function cuidadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mascota_usuario', 'mascota_id', 'usuario_id')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * @return HasMany<FotoMascota, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoMascota::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    /**
     * @return HasMany<Alergia, $this>
     */
    public function alergias(): HasMany
    {
        return $this->hasMany(Alergia::class);
    }

    /**
     * @return HasMany<Visita, $this>
     */
    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class)->orderByDesc('fecha_hora')->orderByDesc('id');
    }

    /**
     * @return HasMany<Tratamiento, $this>
     */
    public function tratamientos(): HasMany
    {
        return $this->hasMany(Tratamiento::class)->orderByDesc('fecha_inicio')->orderByDesc('id');
    }

    /**
     * @return HasMany<AplicacionVacuna, $this>
     */
    public function vacunasAplicadas(): HasMany
    {
        return $this->hasMany(AplicacionVacuna::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    /**
     * @return HasMany<Desparasitacion, $this>
     */
    public function desparasitaciones(): HasMany
    {
        return $this->hasMany(Desparasitacion::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    /**
     * @return HasMany<Recordatorio, $this>
     */
    public function recordatorios(): HasMany
    {
        return $this->hasMany(Recordatorio::class)->orderBy('fecha_objetivo');
    }

    /**
     * Pesos en orden cronológico: es como se dibuja la curva.
     *
     * @return HasMany<RegistroPeso, $this>
     */
    public function pesos(): HasMany
    {
        return $this->hasMany(RegistroPeso::class)->orderBy('fecha')->orderBy('id');
    }

    /**
     * @return HasMany<Dieta, $this>
     */
    public function dietas(): HasMany
    {
        return $this->hasMany(Dieta::class)->orderByDesc('fecha_inicio')->orderByDesc('id');
    }

    /**
     * @return HasMany<CicloCelo, $this>
     */
    public function ciclosCelo(): HasMany
    {
        return $this->hasMany(CicloCelo::class)->orderByDesc('fecha_inicio')->orderByDesc('id');
    }

    /**
     * @return HasMany<EntradaDiario, $this>
     */
    public function entradasDiario(): HasMany
    {
        return $this->hasMany(EntradaDiario::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    /**
     * La dieta que está comiendo ahora. Solo puede haber una (regla 1).
     *
     * @return HasOne<Dieta, $this>
     */
    public function dietaVigente(): HasOne
    {
        return $this->hasOne(Dieta::class)->whereNull('fecha_fin')->latestOfMany('fecha_inicio');
    }

    /**
     * El último peso cargado, para el dashboard y la variación.
     *
     * @return HasOne<RegistroPeso, $this>
     */
    public function ultimoPeso(): HasOne
    {
        return $this->hasOne(RegistroPeso::class)->latestOfMany('fecha');
    }

    public function rolDe(User $usuario): ?RolCuidador
    {
        $fila = $this->cuidadores->firstWhere('id', $usuario->id);
        $rol = $fila?->getRelationValue('pivot')?->getAttribute('rol');

        return is_string($rol) ? RolCuidador::from($rol) : null;
    }

    /**
     * Edad legible: "3 años y 2 meses", "8 meses", "20 días".
     * Si la fecha de nacimiento es estimada, se antepone "~".
     * Para una mascota fallecida se calcula hasta el fallecimiento.
     */
    public function getEdadAttribute(): ?string
    {
        if (! $this->fecha_nacimiento) {
            return null;
        }

        $hasta = $this->fecha_fallecimiento ?? Carbon::today();

        if ($this->fecha_nacimiento->gt($hasta)) {
            return null;
        }

        $texto = $this->formatearEdad($this->fecha_nacimiento, $hasta);

        return $this->fecha_nacimiento_estimada ? "~{$texto}" : $texto;
    }

    private function formatearEdad(CarbonInterface $desde, CarbonInterface $hasta): string
    {
        $anios = (int) $desde->diffInYears($hasta);
        $meses = (int) $desde->copy()->addYears($anios)->diffInMonths($hasta);

        return match (true) {
            $anios >= 1 && $meses >= 1 => sprintf(
                '%d %s y %d %s',
                $anios,
                $anios === 1 ? 'año' : 'años',
                $meses,
                $meses === 1 ? 'mes' : 'meses',
            ),
            $anios >= 1 => sprintf('%d %s', $anios, $anios === 1 ? 'año' : 'años'),
            $meses >= 1 => sprintf('%d %s', $meses, $meses === 1 ? 'mes' : 'meses'),
            default => sprintf('%d días', (int) $desde->diffInDays($hasta)),
        };
    }

    /**
     * Regla de negocio 1: el módulo de celo solo existe para hembras no
     * castradas y vivas. El frontend usa esto para ocultarlo; las fases
     * siguientes también lo consultan al generar recordatorios.
     *
     * @return Attribute<bool, never>
     */
    protected function celoVisible(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->sexo === Sexo::Hembra
                && ! $this->castrado
                && $this->fecha_fallecimiento === null,
        );
    }

    /**
     * Una mascota fallecida pasa a modo lectura: no se cargan eventos nuevos.
     *
     * @return Attribute<bool, never>
     */
    protected function fallecida(): Attribute
    {
        return Attribute::get(fn (): bool => $this->fecha_fallecimiento !== null);
    }
}
