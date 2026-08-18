<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolCuidador;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $telefono
 * @property string $zona_horaria
 * @property int $dias_anticipacion_celo
 * @property Carbon|null $email_verified_at
 * @property string|null $password Nulo para quien entró con Google: nunca eligió una.
 * @property string|null $google_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'email', 'password', 'telefono', 'zona_horaria', 'dias_anticipacion_celo'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dias_anticipacion_celo' => 'integer',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }

    /**
     * Mascotas a las que este usuario tiene acceso, con su rol en el pivote.
     * Incluye las propias (el observer inserta la fila 'propietario' al crear)
     * y, en v2, las compartidas por otros. Autorizar SIEMPRE por esta relación.
     *
     * @return BelongsToMany<Mascota, $this>
     */
    public function mascotas(): BelongsToMany
    {
        return $this->belongsToMany(Mascota::class, 'mascota_usuario', 'usuario_id', 'mascota_id')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /*
     * Hay **tres** relaciones y cada una contesta una pregunta distinta. Elegir
     * la equivocada no da ningún error: da datos de más o avisos que no llegan.
     *
     *   mascotas()        ¿qué puedo mirar?   propietario + cuidador + lector
     *   mascotasACargo()  ¿qué puedo hacer?   propietario + cuidador
     *   mascotasPropias() ¿qué es mío?        propietario
     */

    /**
     * Las mascotas en las que este usuario **puede hacer algo**: las suyas y las
     * que le compartieron con rol de cuidador.
     *
     * Va en las pantallas de acción —Medicación de hoy, la agenda, el
     * dashboard—: un lector no da la medicación, así que mostrarle tomas que al
     * tocarlas dan 403 es peor que no mostrárselas.
     *
     * @return BelongsToMany<Mascota, $this>
     */
    public function mascotasACargo(): BelongsToMany
    {
        return $this->mascotas()->wherePivot('rol', '!=', RolCuidador::Lector->value);
    }

    /**
     * Solo las propias. Es la más restrictiva y la usan dos cosas puntuales.
     *
     * **El aviso por mail.** Los recordatorios cuelgan de la mascota, no del
     * usuario, y el comando los marca como notificados **por id**: si dos
     * personas de la misma mascota entraran en esa consulta, la que corriera
     * primero se llevaría el aviso y la otra no lo recibiría nunca. Con el mail
     * yendo solo al dueño el problema no existe. El cuidador igual ve todo lo
     * que hay que hacer en la app; lo que no recibe es el correo.
     *
     * **`mis-datos`.** Exportar es llevarse lo propio: el historial de la
     * mascota de otro no entra, ni siquiera con permiso para editarla.
     *
     * @return BelongsToMany<Mascota, $this>
     */
    public function mascotasPropias(): BelongsToMany
    {
        return $this->mascotas()->wherePivot('rol', RolCuidador::Propietario->value);
    }

    /*
     * ------------------------------------------------------------------ tiempo
     *
     * Todo se persiste en UTC (config/app.php lo fija) y cada usuario tiene su
     * `zona_horaria`. La conversión ocurre acá, en los dos sentidos, y no
     * desperdigada por controladores: una toma de las 8 de la mañana tiene que
     * aparecer a las 8 de la mañana de quien la dio, no del servidor.
     */

    /**
     * Zona del usuario. Si quedó vacía o inválida, UTC antes que reventar.
     *
     * El null es un caso real y no defensa de más: un modelo recién creado no
     * trae todavía el default de la columna hasta que se lo relee de la base.
     */
    public function zona(): DateTimeZone
    {
        try {
            return new DateTimeZone($this->zona_horaria ?: 'UTC');
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Lo que el usuario escribió en su reloj, listo para guardar.
     * Un `datetime-local` del navegador viene sin zona: es esta.
     */
    public function aUtc(?string $fechaLocal): ?CarbonImmutable
    {
        if ($fechaLocal === null || trim($fechaLocal) === '') {
            return null;
        }

        return CarbonImmutable::parse($fechaLocal, $this->zona())->utc();
    }

    /** Un instante guardado en UTC, en el reloj del usuario. */
    public function enSuZona(?CarbonInterface $instante): ?CarbonImmutable
    {
        return $instante?->toImmutable()->setTimezone($this->zona());
    }

    /** "Ahora" y "hoy" según el usuario, que es lo único que le importa. */
    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now($this->zona());
    }

    public function hoy(): CarbonImmutable
    {
        return $this->ahora()->startOfDay();
    }

    /**
     * El día de hoy del usuario, **como fecha de calendario**.
     *
     * `hoy()` devuelve un instante con zona; las columnas `date` las lee Carbon
     * a medianoche UTC. Compararlos directamente sale mal: con el usuario en
     * Buenos Aires hay tres horas de corrimiento y "mañana" se lee como "hoy".
     *
     * Regla: para comparar contra una columna `date` va esto; `hoy()` es solo
     * para comparar contra instantes (`datetime`).
     */
    public function hoyCalendario(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->hoy()->toDateString());
    }
}
