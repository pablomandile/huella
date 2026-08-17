<?php

namespace App\Services;

use App\Enums\TipoAdjunto;
use App\Models\Adjunto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Adjuntos clínicos: recetas, análisis, radiografías, facturas.
 *
 * A diferencia de las fotos de la mascota, acá **el original se conserva tal
 * cual**. Una radiografía recomprimida a WebP con pérdida deja de servir para
 * lo que se guardó, y una receta escaneada tiene valor probatorio. De las
 * imágenes se genera además una miniatura, que es lo único que se recomprime,
 * y solo para la vista previa de la lista.
 *
 * Todo va al disco privado y se sirve por controlador tras verificar propiedad.
 */
class ArchivoService
{
    private const ANCHO_MINIATURA = 480;

    public function __construct(private readonly ImageManager $procesador) {}

    /**
     * @param  MorphMany<Adjunto, covariant Model>  $relacion
     */
    public function adjuntar(
        MorphMany $relacion,
        UploadedFile $archivo,
        TipoAdjunto $tipo,
        ?string $descripcion = null,
        string $directorio = 'adjuntos',
    ): Adjunto {
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'bin');
        $nombre = Str::uuid()->toString().".{$extension}";

        // putFileAs y no put($ruta, $archivo->get()): mueve el archivo sin
        // cargarlo entero en memoria, que con una radiografía de 10 MB importa.
        $ruta = Storage::putFileAs($directorio, $archivo, $nombre);

        if ($ruta === false) {
            throw new RuntimeException("No se pudo guardar el adjunto en {$directorio}.");
        }

        $mime = $archivo->getClientMimeType();

        if (str_starts_with($mime, 'image/')) {
            $this->generarMiniatura($archivo, $ruta);
        }

        return $relacion->create([
            'tipo' => $tipo,
            'ruta' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime' => $mime,
            'tamanio_bytes' => $archivo->getSize(),
            'descripcion' => $descripcion,
        ]);
    }

    /** Ruta de la miniatura de un adjunto, si corresponde tenerla. */
    public function rutaMiniatura(string $ruta): string
    {
        return preg_replace('/\.[^.]+$/', '', $ruta).'-min.webp';
    }

    public function eliminar(Adjunto $adjunto): void
    {
        Storage::delete([$adjunto->ruta, $this->rutaMiniatura($adjunto->ruta)]);
    }

    private function generarMiniatura(UploadedFile $archivo, string $ruta): void
    {
        Storage::put(
            $this->rutaMiniatura($ruta),
            $this->procesador->decodePath($archivo->getRealPath())
                ->scaleDown(width: self::ANCHO_MINIATURA, height: self::ANCHO_MINIATURA)
                ->encode(new WebpEncoder(quality: 80))
                ->toString(),
        );
    }
}
