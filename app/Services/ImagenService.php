<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Procesa las imágenes que suben los usuarios: reduce, convierte a WebP y
 * genera una miniatura. Todo va al disco privado 'local'; las imágenes se
 * sirven por controlador tras verificar propiedad, nunca por URL pública.
 */
class ImagenService
{
    /** Lado mayor de la imagen principal. Sobra para pantalla, aliviana la cámara del celular. */
    private const ANCHO_MAXIMO = 1600;

    /** Lado mayor de la miniatura (grillas y selectores). */
    private const ANCHO_MINIATURA = 480;

    private const CALIDAD = 82;

    public function __construct(private readonly ImageManager $procesador) {}

    /**
     * Guarda la imagen y su miniatura en WebP.
     *
     * @return array{ruta: string, ruta_miniatura: string}
     */
    public function guardar(UploadedFile $archivo, string $directorio): array
    {
        $nombre = Str::uuid()->toString();
        $ruta = "{$directorio}/{$nombre}.webp";
        $rutaMiniatura = "{$directorio}/{$nombre}-min.webp";

        $imagen = $this->procesador->decodePath($archivo->getRealPath());
        $codificador = new WebpEncoder(quality: self::CALIDAD);

        Storage::put(
            $ruta,
            $imagen->scaleDown(width: self::ANCHO_MAXIMO, height: self::ANCHO_MAXIMO)
                ->encode($codificador)
                ->toString(),
        );

        Storage::put(
            $rutaMiniatura,
            $imagen->scaleDown(width: self::ANCHO_MINIATURA, height: self::ANCHO_MINIATURA)
                ->encode($codificador)
                ->toString(),
        );

        return ['ruta' => $ruta, 'ruta_miniatura' => $rutaMiniatura];
    }

    /**
     * Miniatura cuadrada de una imagen del disco privado, lista para incrustar
     * en un mail.
     *
     * **Va incrustada en el mensaje y no por URL.** Las imágenes de la app se
     * sirven por controlador tras verificar propiedad, y quien recibe una
     * invitación todavía no tiene acceso a nada: un `<img>` apuntando a la ruta
     * de la foto daría 403 y se vería rota.
     *
     * **JPEG y no el WebP que guarda la app**, por lo mismo que el logo del
     * encabezado: Outlook de escritorio no lo entiende.
     *
     * **Devuelve null si la imagen no se puede leer, y no lanza.** Un mail sin
     * foto se manda igual; perder la invitación entera por no poder generar una
     * miniatura sería el peor intercambio posible. Mismo criterio que los
     * adjuntos clínicos.
     */
    public function miniaturaParaMail(?string $ruta, int $lado = 240): ?string
    {
        if ($ruta === null || ! Storage::exists($ruta)) {
            return null;
        }

        try {
            return $this->procesador
                // `decodeBinary` y no `decodePath`: el archivo vive en el disco
                // privado, que no siempre es una ruta del filesystem local.
                ->decodeBinary((string) Storage::get($ruta))
                ->cover($lado, $lado)
                ->encode(new JpegEncoder(quality: self::CALIDAD))
                ->toString();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function eliminar(?string ...$rutas): void
    {
        Storage::delete(array_filter($rutas));
    }
}
