<?php

namespace App\Models;

use App\Enums\TipoAdjunto;
use Carbon\CarbonImmutable;
use Database\Factories\EnlaceCompartidoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un enlace que muestra la ficha de una mascota sin pedir cuenta.
 *
 * @property int $id
 * @property int $mascota_id
 * @property int $creado_por
 * @property string $token
 * @property string|null $nombre
 * @property bool $incluye_adjuntos
 * @property CarbonImmutable|null $expira_en
 * @property CarbonImmutable|null $ultimo_acceso_en
 * @property int $visitas
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read bool $vencido
 */
class EnlaceCompartido extends Model
{
    /** @use HasFactory<EnlaceCompartidoFactory> */
    use HasFactory;

    protected $table = 'enlaces_compartidos';

    /**
     * `token`, `mascota_id` y `creado_por` quedan afuera a propósito: son de lo
     * que depende toda la autorización del enlace. Se escriben en el servicio,
     * nunca por asignación masiva. Mismo criterio que `mascota_id` en los
     * registros clínicos.
     */
    protected $fillable = [
        'nombre',
        'incluye_adjuntos',
        'expira_en',
    ];

    protected function casts(): array
    {
        return [
            'incluye_adjuntos' => 'boolean',
            'expira_en' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
            'visitas' => 'integer',
        ];
    }

    /** El enlace se resuelve por su token, no por el id. */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * 48 caracteres sobre `random_bytes()`: unos 2^285 posibles. Adivinar uno no
     * es un ataque realista, y la URL sigue entrando en un mensaje.
     */
    public static function nuevoToken(): string
    {
        return Str::random(48);
    }

    /**
     * @return BelongsTo<Mascota, $this>
     */
    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * `expira_en` es un `datetime`, así que se compara contra un instante y no
     * contra `hoyCalendario()`. Es el lado correcto de la regla del proyecto,
     * pero conviene tenerlo escrito para no aplicar el reflejo equivocado.
     *
     * NULL es "no vence" y por lo tanto nunca está vencido. El `?? false` es la
     * mitad que importa: sin él esto sería `null`, que en un `if` se lee como
     * falso por casualidad y en un `abort_if` estricto no se lee para nada.
     *
     * @return Attribute<bool, never>
     */
    protected function vencido(): Attribute
    {
        return Attribute::get(fn (): bool => $this->expira_en?->isPast() ?? false);
    }

    /**
     * @param  Builder<EnlaceCompartido>  $consulta
     * @return Builder<EnlaceCompartido>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        /*
         * El paréntesis no es cosmético: sin agrupar, el `orWhereNull` se suma
         * al final de la consulta entera y se lleva puesto cualquier `where` que
         * el llamador haya puesto antes —empezando por el `mascota_id` de la
         * relación—, que es como un listado termina mostrando los enlaces sin
         * vencimiento de todas las mascotas.
         */
        return $consulta->where(
            fn (Builder $vigencia) => $vigencia
                ->whereNull('expira_en')
                ->orWhere('expira_en', '>', now()),
        );
    }

    /**
     * ¿Este enlace da acceso a este archivo?
     *
     * Tres preguntas, y las tres importan:
     *
     * 1. **Que el adjunto sea de esta mascota.** Se resuelve con la misma cadena
     *    que usa la Policy (`Adjunto::mascotaAsociada()`, que sube adjunto →
     *    visita → mascota). Sin esto, un token de la mascota A serviría para los
     *    archivos de la B con solo cambiar el id en la URL.
     * 2. **Que no sea una factura.** Es dato financiero, no clínico: no tiene
     *    nada que hacer en la ficha que se muestra en una veterinaria.
     * 3. **Que sea documentación de la mascota, o que el dueño haya pedido
     *    incluir los adjuntos clínicos.** La libreta y el certificado de rabia
     *    son el motivo del enlace y van siempre; las radiografías y los análisis
     *    son opt-in.
     */
    public function alcanza(Adjunto $adjunto): bool
    {
        if (! $adjunto->mascotaAsociada()?->is($this->mascota)) {
            return false;
        }

        if ($adjunto->tipo === TipoAdjunto::Factura) {
            return false;
        }

        return $this->incluye_adjuntos || $adjunto->tipo->esDocumentoDeMascota();
    }
}
