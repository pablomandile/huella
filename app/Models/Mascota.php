<?php

namespace App\Models;

use App\Enums\Especie;
use App\Enums\RolCuidador;
use App\Enums\Sexo;
use App\Enums\TipoPelaje;
use App\Enums\TipoRecordatorio;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property CarbonImmutable|null $rabia_vencimiento
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

    /**
     * El propietario y los cuidadores vienen siempre cargados.
     *
     * No es comodidad, son las dos relaciones que **todo** el dominio necesita:
     *
     * - `propietario`: media docena de servicios usan su `zona_horaria` para
     *   cualquier cálculo de fechas (`EstadoVacunacionService`,
     *   `EstimadorCeloService`, `GeneradorRecordatoriosService`,
     *   `HistoriaClinicaService`…), y todos llegan a ella navegando la relación.
     * - `cuidadores`: es el pivote por el que pasa la autorización de toda
     *   mascota, así que cada `Gate::authorize()` la toca.
     *
     * Con `preventLazyLoading` activo, olvidarse del eager loading en cualquier
     * pantalla nueva es un 500 — ya pasó en el dashboard. Son un `belongsTo` a
     * una fila de `users` y un pivote de una fila: dos queries por request.
     *
     * @var list<string>
     */
    protected $with = ['propietario', 'cuidadores'];

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
        'rabia_vencimiento',
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
            'rabia_vencimiento' => 'date',
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
     * Documentación propia de la mascota: la libreta sanitaria y el certificado
     * de rabia. Son adjuntos colgados directo de ella, no de una visita, porque
     * no pertenecen a un episodio clínico: valen para toda su vida.
     *
     * `Adjunto::mascotaAsociada()` ya resolvía este caso, así que la Policy
     * funciona sin tocar nada.
     *
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'adjuntable')->orderBy('id');
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

    /**
     * En qué estado está el certificado de rabia: vigente, por vencer o vencido.
     *
     * Se compara con **`hoyCalendario()` del propietario**, no con `today()` ni
     * con `hoy()`. `rabia_vencimiento` es una columna `date`, que Carbon lee a
     * medianoche UTC; medirla contra un instante con zona corre el resultado tres
     * horas y "vence mañana" se lee como "vence hoy". Y la zona es la del
     * propietario, no la de quien mira, para que en v2 un cuidador en otro país
     * no vea otro vencimiento.
     *
     * @return array{estado: string, dias: int, texto: string}|null
     */
    public function getEstadoRabiaAttribute(): ?array
    {
        if (! $this->rabia_vencimiento) {
            return null;
        }

        $hoy = $this->propietario->hoyCalendario();
        $dias = (int) $hoy->diffInDays($this->rabia_vencimiento, false);

        [$estado, $texto] = match (true) {
            $dias < 0 => ['vencido', $dias === -1
                ? 'Venció ayer'
                : 'Venció hace '.abs($dias).' días'],
            $dias === 0 => ['vencido', 'Vence hoy'],
            $dias === 1 => ['por_vencer', 'Vence mañana'],
            $dias <= TipoRecordatorio::CertificadoRabia->diasDeAnticipacion() => [
                'por_vencer',
                "Vence en {$dias} días",
            ],
            default => ['vigente', 'Vigente hasta el '.$this->rabia_vencimiento->format('d/m/Y')],
        };

        return ['estado' => $estado, 'dias' => $dias, 'texto' => $texto];
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
