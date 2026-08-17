<?php

use App\Enums\Sexo;
use App\Models\Mascota;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-17');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('calcula la edad en años y meses', function (string $nacimiento, string $esperado) {
    $mascota = new Mascota([
        'fecha_nacimiento' => $nacimiento,
        'fecha_nacimiento_estimada' => false,
    ]);

    expect($mascota->edad)->toBe($esperado);
})->with([
    'años y meses' => ['2023-05-17', '3 años y 3 meses'],
    'año justo' => ['2025-08-17', '1 año'],
    'solo meses' => ['2026-01-17', '7 meses'],
    'un mes' => ['2026-07-17', '1 mes'],
    'días' => ['2026-08-01', '16 días'],
]);

it('antepone ~ cuando la fecha de nacimiento es estimada', function () {
    $mascota = new Mascota([
        'fecha_nacimiento' => '2024-08-17',
        'fecha_nacimiento_estimada' => true,
    ]);

    expect($mascota->edad)->toBe('~2 años');
});

it('congela la edad en la fecha de fallecimiento', function () {
    $mascota = new Mascota([
        'fecha_nacimiento' => '2010-01-01',
        'fecha_nacimiento_estimada' => false,
        'fecha_fallecimiento' => '2024-01-01',
    ]);

    expect($mascota->edad)->toBe('14 años');
});

it('no tiene edad sin fecha de nacimiento', function () {
    expect((new Mascota)->edad)->toBeNull();
});

it('muestra el módulo de celo solo para hembras enteras y vivas', function () {
    $base = ['fecha_nacimiento' => '2022-01-01', 'fecha_nacimiento_estimada' => false];

    $hembraEntera = new Mascota([...$base, 'sexo' => Sexo::Hembra, 'castrado' => false]);
    $hembraCastrada = new Mascota([...$base, 'sexo' => Sexo::Hembra, 'castrado' => true]);
    $macho = new Mascota([...$base, 'sexo' => Sexo::Macho, 'castrado' => false]);
    $fallecida = new Mascota([
        ...$base,
        'sexo' => Sexo::Hembra,
        'castrado' => false,
        'fecha_fallecimiento' => '2026-01-01',
    ]);

    expect($hembraEntera->celo_visible)->toBeTrue()
        ->and($hembraCastrada->celo_visible)->toBeFalse()
        ->and($macho->celo_visible)->toBeFalse()
        ->and($fallecida->celo_visible)->toBeFalse();
});
