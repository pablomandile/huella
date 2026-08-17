<?php

namespace App\Services;

use App\Models\User;
use App\Support\CuentaDeGoogle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alta y vínculo de cuentas que entran con Google.
 *
 * La regla de fondo: **el email manda**. Google ya lo verificó, así que si
 * coincide con una cuenta que existe es la misma persona y se le vincula el
 * `google_id`. Crear una segunda cuenta con el mismo email dejaría a alguien con
 * dos historias clínicas separadas para la misma mascota, cada una invisible
 * desde la otra.
 */
class IngresoConGoogleService
{
    /**
     * ¿Está configurado el ingreso con Google?
     *
     * Se pregunta antes de mostrar el botón y en las rutas: sin credenciales, la
     * app tiene que funcionar como si la opción no existiera, no tirar un 500.
     */
    public static function configurado(): bool
    {
        // `Config::get` y no `Config::string`: sin las variables en el .env el
        // valor es null, y `Config::string` lo toma como un tipo inválido y tira
        // excepción en vez de devolver el default.
        return filled(Config::get('services.google.client_id'))
            && filled(Config::get('services.google.client_secret'));
    }

    /**
     * El usuario de Huella detrás de una cuenta de Google, creándolo si hace falta.
     */
    public function resolver(CuentaDeGoogle $cuenta): User
    {
        if (blank($cuenta->id) || blank($cuenta->email)) {
            // Sin email no hay forma de vincular la cuenta ni de avisarle nada.
            throw new RuntimeException('Google no devolvió el email de la cuenta.');
        }

        if (! $cuenta->emailVerificado) {
            throw new RuntimeException('La cuenta de Google no tiene el email verificado.');
        }

        return DB::transaction(function () use ($cuenta): User {
            $porGoogle = User::where('google_id', $cuenta->id)->first();

            if ($porGoogle !== null) {
                // El email en Google puede haber cambiado desde la última vez; el
                // id es el que se mantiene.
                $porGoogle->email = $cuenta->email;
                $porGoogle->save();

                return $porGoogle;
            }

            $porEmail = User::where('email', $cuenta->email)->first();

            if ($porEmail !== null) {
                $porEmail->google_id = $cuenta->id;
                $porEmail->save();

                // Si se había registrado por email y todavía no lo había
                // confirmado, Google acaba de hacerlo por él.
                if (! $porEmail->hasVerifiedEmail()) {
                    $porEmail->markEmailAsVerified();
                }

                return $porEmail;
            }

            return $this->crear($cuenta);
        });
    }

    /**
     * Cuenta nueva. Queda **sin contraseña**: nunca eligió una, y guardarle una
     * al azar la haría figurar como que puede entrar con email y clave.
     */
    private function crear(CuentaDeGoogle $cuenta): User
    {
        $usuario = new User;

        // `forceFill` y no asignación: `password` y `email_verified_at` no son
        // fillable, y acá se escriben desde el sistema y no desde un formulario.
        $usuario->forceFill([
            'name' => $cuenta->nombre ?: (string) str($cuenta->email)->before('@'),
            'email' => $cuenta->email,
            'google_id' => $cuenta->id,
            'password' => null,
            // Google ya verificó el email: pedirle que confirme el mismo email
            // sería hacerlo esperar un mail para nada.
            'email_verified_at' => $usuario->freshTimestamp(),
        ]);

        // La zona horaria queda en el default de la tabla y se cambia desde el
        // perfil: es la de Buenos Aires, que es lo que asume todo el proyecto
        // salvo que la persona diga otra cosa.
        $usuario->save();

        return $usuario;
    }
}
