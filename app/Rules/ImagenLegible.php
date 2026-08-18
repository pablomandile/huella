<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Que la imagen se pueda **abrir de verdad**, no solo que lo diga su cabecera.
 *
 * `image` y `mimes:` de Laravel miran los primeros bytes del archivo. Una foto
 * que se cortó a mitad de la subida —mala señal en el celular, que es donde se
 * usa esta app— conserva la cabecera PNG o JPEG y pasa las dos reglas; después
 * el decodificador falla y el usuario recibe una pantalla de error 500 sin
 * entender qué pasó ni poder corregirlo.
 *
 * Con esto recibe "no se pudo leer" y vuelve a sacar la foto, que es lo único
 * que puede hacer.
 */
class ImagenLegible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return; // de que sea un archivo se encargan las otras reglas
        }

        $ruta = $value->getRealPath();

        if ($ruta === false) {
            $fail('No se pudo leer :attribute. Probá de nuevo.');

            return;
        }

        try {
            app(ImageManager::class)->decodePath($ruta);
        } catch (Throwable) {
            $fail('No se pudo leer :attribute: puede estar dañada o incompleta. Probá con otra.');
        }
    }
}
