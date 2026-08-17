<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Un evento de la línea de tiempo, ya normalizado.
 *
 * Las siete fuentes que alimentan el diario no se parecen entre sí —una visita
 * tiene diagnóstico, un peso tiene kilos—, así que se las traduce a esta forma
 * común. El ícono y el color los decide el front a partir de `tipo`: son
 * decisiones de presentación y no tienen por qué viajar desde el servidor.
 */
final readonly class EventoTimeline
{
    /**
     * @param  string  $tipo  discrimina la fuente: visita, vacuna, peso…
     * @param  array<string, mixed>  $datos  lo propio de cada tipo
     */
    public function __construct(
        public string $tipo,
        public int $id,
        public CarbonImmutable $fecha,
        public string $titulo,
        public ?string $detalle = null,
        public ?string $url = null,
        public array $datos = [],
    ) {}

    /**
     * Clave de orden dentro de un mismo día.
     *
     * Es lo que hace determinística la paginación: sin un segundo criterio, dos
     * eventos de la misma fecha pueden salir en orden distinto en cada consulta
     * y la página siguiente se saltea o repite alguno.
     */
    public function clave(): string
    {
        return sprintf('%s:%d', $this->tipo, $this->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function paraElFront(): array
    {
        return [
            'tipo' => $this->tipo,
            'id' => $this->id,
            'clave' => $this->clave(),
            'fecha' => $this->fecha->toDateString(),
            'fecha_legible' => $this->fecha->translatedFormat('j \d\e F \d\e Y'),
            'titulo' => $this->titulo,
            'detalle' => $this->detalle,
            'url' => $this->url,
            ...$this->datos,
        ];
    }
}
