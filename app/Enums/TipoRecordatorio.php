<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum TipoRecordatorio: string
{
    use TieneOpciones;

    case Vacuna = 'vacuna';
    case Desparasitacion = 'desparasitacion';
    case Celo = 'celo';
    case Control = 'control';
    case Medicacion = 'medicacion';
    case Peso = 'peso';
    case Seguro = 'seguro';
    case CertificadoRabia = 'certificado_rabia';
    case Personalizado = 'personalizado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vacuna => 'Vacuna',
            self::Desparasitacion => 'Desparasitación',
            self::Celo => 'Celo',
            self::Control => 'Control',
            self::Medicacion => 'Medicación',
            self::Peso => 'Peso',
            self::Seguro => 'Seguro',
            self::CertificadoRabia => 'Certificado de rabia',
            self::Personalizado => 'Otro',
        };
    }

    /**
     * Con cuántos días de anticipación avisar, por tipo.
     *
     * Una vacuna se puede dar con holgura y conviene avisar con tiempo para
     * conseguir turno; un control es más puntual. El celo lo decide el usuario
     * en su perfil (`users.dias_anticipacion_celo`).
     */
    public function diasDeAnticipacion(): int
    {
        return match ($this) {
            /*
             * El certificado de rabia avisa con más tiempo que una vacuna
             * cualquiera: para tenerlo al día hay que conseguir turno, dar la
             * dosis y que el veterinario emita el papel. Es el documento que se
             * pide en un viaje o en una guardería, y llegar tarde ahí significa
             * no poder viajar.
             */
            self::CertificadoRabia => 30,
            self::Vacuna, self::Seguro => 15,
            self::Celo => 14,
            self::Desparasitacion, self::Control, self::Peso => 7,
            self::Medicacion, self::Personalizado => 3,
        };
    }

    /** Los que se generan solos desde otro registro y no se editan a mano. */
    public function esAutomatico(): bool
    {
        return $this !== self::Personalizado;
    }
}
