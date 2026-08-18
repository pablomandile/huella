<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoAdjunto: string
{
    use TieneOpciones;

    case Receta = 'receta';
    case Analisis = 'analisis';
    case Radiografia = 'radiografia';
    case Ecografia = 'ecografia';
    case Certificado = 'certificado';
    case Factura = 'factura';
    case Foto = 'foto';

    /*
     * Estos dos cuelgan de la mascota, no de una visita: son los papeles que el
     * dueño carga una vez y lleva a cualquier veterinario. `Certificado` sigue
     * siendo el genérico de una visita —un certificado de salud para viajar, por
     * ejemplo—; el de rabia es su propio tipo porque tiene vencimiento y avisa.
     */
    case LibretaSanitaria = 'libreta_sanitaria';
    case CertificadoRabia = 'certificado_rabia';

    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Receta => 'Receta',
            self::Analisis => 'Análisis',
            self::Radiografia => 'Radiografía',
            self::Ecografia => 'Ecografía',
            self::Certificado => 'Certificado',
            self::Factura => 'Factura',
            self::Foto => 'Foto',
            self::LibretaSanitaria => 'Libreta sanitaria',
            self::CertificadoRabia => 'Certificado de rabia',
            self::Otro => 'Otro',
        };
    }

    /**
     * Los que son documentación de la mascota y no de un registro clínico.
     *
     * Es lo que separa lo que puede subirse a `mascotas/{id}/documentos` de lo
     * que solo tiene sentido colgado de una visita.
     *
     * @return array<int, self>
     */
    public static function documentosDeMascota(): array
    {
        return [self::LibretaSanitaria, self::CertificadoRabia];
    }

    public function esDocumentoDeMascota(): bool
    {
        return in_array($this, self::documentosDeMascota(), true);
    }
}
