<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Explícita y no heredada del default de la columna: un modelo
            // recién creado no lee el default de la base hasta que se relee, y
            // sin esto quedaría con la zona en null en memoria.
            'zona_horaria' => 'America/Argentina/Buenos_Aires',
            'dias_anticipacion_celo' => 14,
            // Con los términos vigentes aceptados: si no, el middleware
            // `AceptoTerminos` mandaría cada pedido de cada test a aceptarlos.
            'terminos_version' => config('legales.version'),
            'terminos_aceptados_en' => now(),
            /* @chisel-2fa */
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            /* @end-chisel-2fa */
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Una cuenta de antes de los términos (o un alta con Google): sin aceptar.
     */
    public function sinAceptarTerminos(): static
    {
        return $this->state(fn (array $attributes) => [
            'terminos_version' => null,
            'terminos_aceptados_en' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        /* @chisel-2fa */
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
        /* @end-chisel-2fa */
    }
}
