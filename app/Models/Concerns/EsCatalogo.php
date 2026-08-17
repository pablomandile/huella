<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Común a los cinco catálogos.
 *
 * Hay dos familias y una sola mecánica:
 *
 * - Agenda personal (veterinarias, veterinarios): `usuario_id` NOT NULL.
 *   Nunca hay semilla, así que `esSemilla()` da siempre false.
 * - Catálogo con semilla (medicamentos, vacunas, alimentos): `usuario_id` NULL
 *   marca los registros del sistema. Los ve todo el mundo y no los edita nadie:
 *   regla de negocio 4, se duplican.
 *
 * @property int|null $usuario_id
 */
trait EsCatalogo
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Registro precargado del sistema: se ve, se duplica, no se toca. */
    public function esSemilla(): bool
    {
        return $this->usuario_id === null;
    }

    public function perteneceA(User $usuario): bool
    {
        return $this->usuario_id === $usuario->id;
    }

    public function asignarPropietario(User $usuario): void
    {
        $this->usuario_id = $usuario->id;
    }

    /**
     * Lo que el usuario puede elegir: la semilla del sistema más lo suyo.
     * Nunca lo de otra cuenta.
     *
     * Es un método estático y no un scope a propósito: `CatalogoController`
     * lo llama sobre modelos que solo conoce en abstracto, y un scope mágico
     * ahí no se puede tipar.
     *
     * @return Builder<static>
     */
    public static function disponiblesPara(User $usuario): Builder
    {
        return static::query()->where(
            fn (Builder $q) => $q->whereNull('usuario_id')->orWhere('usuario_id', $usuario->id),
        );
    }

    /**
     * Solo lo propio.
     *
     * @return Builder<static>
     */
    public static function propiosDe(User $usuario): Builder
    {
        return static::query()->where('usuario_id', $usuario->id);
    }
}
