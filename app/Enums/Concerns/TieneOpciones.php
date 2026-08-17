<?php

namespace App\Enums\Concerns;

/**
 * Común a todos los enums del dominio: expone los casos como lista de
 * { value, label } lista para un <Select> del frontend.
 */
trait TieneOpciones
{
    abstract public function etiqueta(): string;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $caso) => ['value' => $caso->value, 'label' => $caso->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
