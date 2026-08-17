<?php

namespace App\Services;

use App\Models\Mascota;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Exporta todo lo que el usuario cargó, en JSON.
 *
 * No es un backup del sistema: es **lo que el usuario escribió**, en un formato
 * que pueda abrir y leer sin Huella. Por eso las fechas van en ISO, los enums
 * con su valor y su etiqueta, y nada de ids internos de catálogos compartidos.
 *
 * Los archivos adjuntos no van adentro: un JSON con radiografías en base64 pesa
 * cientos de megas y no se puede abrir. Van sus datos y desde dónde bajarlos.
 */
class ExportadorDatosService
{
    /**
     * @return array<string, mixed>
     */
    public function para(User $usuario): array
    {
        $mascotas = $usuario->mascotas()
            ->with([
                'alergias',
                'visitas.veterinaria',
                'visitas.veterinario',
                'visitas.tratamientos.medicamento',
                'visitas.adjuntos',
                'tratamientos.medicamento',
                'vacunasAplicadas.vacuna',
                'desparasitaciones.medicamento',
                'pesos',
                'dietas.alimento',
                'ciclosCelo',
                'entradasDiario',
                'recordatorios',
            ])
            ->orderBy('nombre')
            ->get();

        return [
            'exportado_el' => CarbonImmutable::now()->toIso8601String(),
            'generado_por' => 'Huella',
            'aviso' => 'Este archivo reúne lo que registró el responsable de las mascotas. '
                .'No es un diagnóstico ni una indicación clínica.',
            'usuario' => [
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'telefono' => $usuario->telefono,
                'zona_horaria' => $usuario->zona_horaria,
            ],
            'mascotas' => $mascotas->map(fn (Mascota $mascota) => $this->mascota($mascota))->all(),
        ];
    }

    public function nombreDeArchivo(User $usuario): string
    {
        return sprintf('huella-%s.json', CarbonImmutable::now()->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    private function mascota(Mascota $mascota): array
    {
        return [
            'nombre' => $mascota->nombre,
            'especie' => $mascota->especie->etiqueta(),
            'raza' => $mascota->raza,
            'sexo' => $mascota->sexo->etiqueta(),
            'fecha_nacimiento' => $mascota->fecha_nacimiento?->toDateString(),
            'fecha_nacimiento_estimada' => $mascota->fecha_nacimiento_estimada,
            'fecha_adopcion' => $mascota->fecha_adopcion?->toDateString(),
            'edad' => $mascota->edad,
            'color' => $mascota->color,
            'pelaje' => $mascota->tipo_pelaje?->etiqueta(),
            'senias_particulares' => $mascota->senias_particulares,
            'descripcion' => $mascota->descripcion,
            'microchip' => $mascota->microchip,
            'libreta_sanitaria' => $mascota->libreta_sanitaria,
            'castrado' => $mascota->castrado,
            'fecha_castracion' => $mascota->fecha_castracion?->toDateString(),
            'fecha_fallecimiento' => $mascota->fecha_fallecimiento?->toDateString(),

            'alergias' => $mascota->alergias->map(fn ($alergia) => [
                'agente' => $alergia->agente,
                'tipo' => $alergia->tipo->etiqueta(),
                'severidad' => $alergia->severidad?->etiqueta(),
                'fecha_deteccion' => $alergia->fecha_deteccion?->toDateString(),
                'sintomas' => $alergia->sintomas,
                'notas' => $alergia->notas,
            ])->all(),

            'visitas' => $mascota->visitas->map(fn ($visita) => [
                'fecha_hora' => $visita->fecha_hora->toIso8601String(),
                'tipo' => $visita->tipo->etiqueta(),
                'motivo' => $visita->motivo,
                'diagnostico' => $visita->diagnostico,
                'indicaciones' => $visita->indicaciones,
                'temperatura' => $visita->temperatura,
                'costo' => $visita->costo,
                'moneda' => $visita->moneda,
                'proximo_control' => $visita->proximo_control?->toDateString(),
                'notas' => $visita->notas,
                'veterinaria' => $visita->veterinaria?->nombre,
                'veterinario' => $visita->veterinario?->nombre,
                'tratamientos' => $visita->tratamientos
                    ->map(fn ($t) => $this->tratamiento($t))
                    ->all(),
                // Los archivos no van embebidos: se listan y se bajan aparte.
                'adjuntos' => $visita->adjuntos->map(fn ($adjunto) => [
                    'tipo' => $adjunto->tipo->etiqueta(),
                    'nombre' => $adjunto->nombre_original,
                    'tamanio' => $adjunto->tamanio_legible,
                    'descargar_en' => route('adjuntos.mostrar', [
                        'adjunto' => $adjunto->id,
                        'descargar' => 1,
                    ]),
                ])->all(),
            ])->all(),

            'tratamientos_sin_visita' => $mascota->tratamientos
                ->whereNull('visita_id')
                ->map(fn ($t) => $this->tratamiento($t))
                ->values()
                ->all(),

            'vacunas' => $mascota->vacunasAplicadas->map(fn ($aplicacion) => [
                'fecha' => $aplicacion->fecha->toDateString(),
                'vacuna' => $aplicacion->nombre_vacuna,
                'dosis_nro' => $aplicacion->dosis_nro,
                'marca' => $aplicacion->marca,
                'lote' => $aplicacion->lote,
                'vencimiento_lote' => $aplicacion->vencimiento_lote?->toDateString(),
                'proxima_dosis' => $aplicacion->proxima_dosis?->toDateString(),
                'reacciones' => $aplicacion->reacciones,
                'notas' => $aplicacion->notas,
            ])->all(),

            'desparasitaciones' => $mascota->desparasitaciones->map(fn ($d) => [
                'fecha' => $d->fecha->toDateString(),
                'producto' => $d->nombre_medicamento,
                'tipo' => $d->tipo->etiqueta(),
                'dosis' => $d->dosis,
                'peso_al_momento' => $d->peso_al_momento,
                'proxima_fecha' => $d->proxima_fecha?->toDateString(),
                'notas' => $d->notas,
            ])->all(),

            'pesos' => $mascota->pesos->map(fn ($peso) => [
                'fecha' => $peso->fecha->toDateString(),
                'peso_kg' => $peso->kilos(),
                'origen' => $peso->origen->etiqueta(),
                'condicion_corporal' => $peso->condicion_corporal,
                'notas' => $peso->notas,
            ])->all(),

            'dietas' => $mascota->dietas->map(fn ($dieta) => [
                'desde' => $dieta->fecha_inicio->toDateString(),
                'hasta' => $dieta->fecha_fin?->toDateString(),
                'vigente' => $dieta->estaVigente(),
                'alimento' => trim(sprintf(
                    '%s %s',
                    $dieta->alimento->marca ?? '',
                    $dieta->alimento->nombre,
                )),
                'racion_diaria_g' => $dieta->racion_diaria_g,
                'tomas_por_dia' => $dieta->tomas_por_dia,
                'motivo' => $dieta->motivo,
                'prescripta' => $dieta->prescripta,
                'notas' => $dieta->notas,
            ])->all(),

            'ciclos_celo' => $mascota->ciclosCelo->map(fn ($ciclo) => [
                'fecha_inicio' => $ciclo->fecha_inicio->toDateString(),
                'fecha_fin' => $ciclo->fecha_fin?->toDateString(),
                'duracion_dias' => $ciclo->duracion_dias,
                'intensidad' => $ciclo->intensidad?->etiqueta(),
                'sintomas' => $ciclo->sintomas,
                'hubo_monta' => $ciclo->hubo_monta,
                'proxima_estimada' => $ciclo->proxima_estimada?->toDateString(),
                'notas' => $ciclo->notas,
            ])->all(),

            'diario' => $mascota->entradasDiario->map(fn ($entrada) => [
                'fecha' => $entrada->fecha->toDateString(),
                'titulo' => $entrada->titulo,
                'contenido' => $entrada->contenido,
                'categoria' => $entrada->categoria->etiqueta(),
                'animo' => $entrada->animo?->etiqueta(),
            ])->all(),

            'recordatorios' => $mascota->recordatorios->map(fn ($recordatorio) => [
                'tipo' => $recordatorio->tipo->etiqueta(),
                'titulo' => $recordatorio->titulo,
                'descripcion' => $recordatorio->descripcion,
                'fecha_objetivo' => $recordatorio->fecha_objetivo->toDateString(),
                'estado' => $recordatorio->estado->etiqueta(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tratamiento(mixed $tratamiento): array
    {
        return [
            'medicamento' => $tratamiento->nombre_medicamento,
            'dosis' => $tratamiento->dosis,
            'via' => $tratamiento->via->etiqueta(),
            'frecuencia_horas' => $tratamiento->frecuencia_horas,
            'veces_por_dia' => $tratamiento->veces_por_dia,
            'fecha_inicio' => $tratamiento->fecha_inicio->toDateString(),
            'fecha_fin' => $tratamiento->fecha_fin?->toDateString(),
            'duracion_dias' => $tratamiento->duracion_dias,
            'estado' => $tratamiento->estado->etiqueta(),
            'notas' => $tratamiento->notas,
        ];
    }
}
