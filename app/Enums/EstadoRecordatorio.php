<?php

namespace App\Enums;

use App\Enums\Concerns\TieneOpciones;

enum EstadoRecordatorio: string
{
    use TieneOpciones;

    case Pendiente = 'pendiente';
    case Notificado = 'notificado';
    case Completado = 'completado';
    case Descartado = 'descartado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Notificado => 'Avisado',
            self::Completado => 'Hecho',
            self::Descartado => 'Descartado',
        };
    }

    /**
     * Todavía hay algo que hacer con él.
     *
     * Un recordatorio ya avisado sigue abierto: que llegue el mail no significa
     * que se haya dado la vacuna.
     */
    public function estaAbierto(): bool
    {
        return in_array($this, self::abiertos(), strict: true);
    }

    /**
     * Los estados abiertos, para filtrar en consultas.
     *
     * @return list<self>
     */
    public static function abiertos(): array
    {
        return [self::Pendiente, self::Notificado];
    }

    /**
     * El usuario ya decidió: no se vuelve a tocar ni se resucita al regenerar
     * el recordatorio desde su origen.
     */
    public function loResolvioElUsuario(): bool
    {
        return $this === self::Completado || $this === self::Descartado;
    }
}
