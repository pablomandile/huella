<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tamaño de letra de la app, elegible en Ajustes → Apariencia.
 *
 * El default es **Mediano**, no el chico: la app se usa con el celular en la
 * mano adentro de una veterinaria, con mala luz. Pequeño es como se veía Huella
 * antes de que existiera esto.
 *
 * Los valores son **porcentajes sobre `<html>`**, nunca píxeles. Un
 * `font-size: 20px` en la raíz pisa el tamaño que la persona ya configuró en su
 * navegador —justo lo que alguien con poca vista probablemente ya tocó—. Con un
 * porcentaje las dos preferencias se multiplican.
 *
 * Tailwind mide en `rem`, así que mover la raíz escala la letra **y** el
 * espaciado. Lo que no escala son los breakpoints (`md:`), que se miden en px
 * contra el viewport: con Grande entra menos en el mismo ancho.
 */
enum TamanoLetra: string
{
    /**
     * Duplica a `porDefecto()` porque el `$attributes` de User solo admite
     * expresiones constantes.
     */
    public const string PORDEFECTO = 'mediano';

    case Pequeno = 'pequeno';
    case Mediano = 'mediano';
    case Grande = 'grande';

    public static function porDefecto(): self
    {
        return self::from(self::PORDEFECTO);
    }

    /**
     * El caso, o el default si el valor no es ninguno. La cookie la escribe el
     * cliente: un valor desconocido cae al default y no rompe la página.
     */
    public static function desde(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::porDefecto();
    }

    /** Lo que se aplica al `font-size` de `<html>` (ver app.css). */
    public function porcentaje(): string
    {
        return match ($this) {
            self::Pequeno => '100%',
            self::Mediano => '112.5%',
            self::Grande => '125%',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pequeno => 'Pequeño',
            self::Mediano => 'Mediano',
            self::Grande => 'Grande',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Pequeno => 'Entra más en pantalla. Es como se veía Huella antes.',
            self::Mediano => 'Se lee cómodo con el celular en la mano. Es el que viene puesto.',
            self::Grande => 'El más grande. La pantalla crece hacia abajo.',
        };
    }

    /** El porcentaje como número, para dibujar cada muestra a su tamaño real. */
    public function escala(): float
    {
        return (float) rtrim($this->porcentaje(), '%');
    }

    /**
     * Para el selector de Ajustes, de menor a mayor.
     *
     * @return list<array{valor: string, etiqueta: string, descripcion: string, escala: float}>
     */
    public static function opciones(): array
    {
        return array_map(static fn (self $caso): array => [
            'valor' => $caso->value,
            'etiqueta' => $caso->etiqueta(),
            'descripcion' => $caso->descripcion(),
            'escala' => $caso->escala(),
        ], self::cases());
    }
}
