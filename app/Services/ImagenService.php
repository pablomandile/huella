<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

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

    public function eliminar(?string ...$rutas): void
    {
        Storage::delete(array_filter($rutas));
    }
}
