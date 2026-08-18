{{--
    Historia clínica en PDF, para llevar a un veterinario nuevo o a un viaje.

    Estilo deliberadamente sobrio: se imprime en blanco y negro, se lee en un
    mostrador y tiene que ser legible fotocopiado. Sin colores de marca, sin
    fondos, con tipografía de cuerpo grande.

    Las alergias van **primero**, antes de la ficha: es el dato que puede
    cambiar una decisión en una urgencia, y nadie va a hojear tres páginas
    para encontrarlo.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historia clínica de {{ $mascota->nombre }}</title>
    <style>
        @page { margin: 1.6cm 1.4cm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #111;
        }

        h1 { font-size: 17pt; margin: 0 0 2pt; }
        h2 {
            font-size: 12pt;
            margin: 18pt 0 6pt;
            padding-bottom: 3pt;
            border-bottom: 1px solid #999;
        }
        h3 { font-size: 10.5pt; margin: 10pt 0 3pt; }

        .sub { color: #555; font-size: 9pt; margin: 0; }

        table { width: 100%; border-collapse: collapse; margin-top: 4pt; }
        th, td {
            text-align: left;
            vertical-align: top;
            padding: 4pt 5pt;
            border-bottom: 1px solid #ddd;
        }
        th { background: #f2f2f2; font-size: 9pt; }
        td { font-size: 9.5pt; }

        .alergias {
            border: 2px solid #111;
            padding: 8pt 10pt;
            margin-top: 10pt;
        }
        .alergias h2 { margin-top: 0; border: 0; }
        .alergia { margin-bottom: 5pt; }
        .alergia strong { font-size: 11pt; }
        .severa { text-transform: uppercase; }

        .ficha td { border: 0; padding: 2pt 5pt 2pt 0; }
        .ficha .etiqueta { color: #555; width: 38%; }

        .vacio { color: #777; font-style: italic; font-size: 9.5pt; }
        .bloque { margin-bottom: 8pt; page-break-inside: avoid; }
        .pie {
            margin-top: 22pt;
            padding-top: 6pt;
            border-top: 1px solid #999;
            font-size: 8.5pt;
            color: #555;
        }
    </style>
</head>
<body>

<h1>{{ $mascota->nombre }}</h1>
<p class="sub">
    {{ $mascota->especie->etiqueta() }}@if ($mascota->raza) · {{ $mascota->raza }}@endif
    @if ($mascota->edad) · {{ $mascota->edad }}@endif
    · {{ $mascota->sexo->etiqueta() }}{{ $mascota->castrado ? ', castrado/a' : '' }}
</p>
<p class="sub">{{ $rango }} · Generado el {{ $generado->translatedFormat('j \d\e F \d\e Y, H:i') }}</p>

{{-- Alergias primero: es el dato de urgencia --}}
<div class="alergias">
    <h2>Alergias</h2>

    @forelse ($alergias as $alergia)
        <div class="alergia">
            <strong class="{{ $alergia->severidad?->value === 'severa' ? 'severa' : '' }}">
                {{ $alergia->agente }}
            </strong>
            ({{ $alergia->tipo->etiqueta() }}@if ($alergia->severidad), {{ $alergia->severidad->etiqueta() }}@endif)
            @if ($alergia->sintomas)
                <br>{{ $alergia->sintomas }}
            @endif
        </div>
    @empty
        <p class="vacio">Sin alergias registradas.</p>
    @endforelse
</div>

<h2>Ficha</h2>
<table class="ficha">
    @foreach ([
        'Nacimiento' => $mascota->fecha_nacimiento?->translatedFormat('j \d\e F \d\e Y')
            . ($mascota->fecha_nacimiento_estimada ? ' (estimado)' : ''),
        'Adopción' => $mascota->fecha_adopcion?->translatedFormat('j \d\e F \d\e Y'),
        'Color' => $mascota->color,
        'Pelaje' => $mascota->tipo_pelaje?->etiqueta(),
        'Señas particulares' => $mascota->senias_particulares,
        'Microchip' => $mascota->microchip,
        'Libreta sanitaria' => $mascota->libreta_sanitaria,
        {{--
            El vencimiento del certificado de rabia va en la ficha impresa porque
            es lo que se pide en una guardería o en un viaje, y ahí lo que hay es
            este papel. El estado se arma con una expresión y no con un @if: una
            directiva Blade pegada a una letra no se reconoce y deja el bloque
            abierto, con un error de sintaxis que no señala la línea real.
        --}}
        'Certificado de rabia' => $mascota->rabia_vencimiento
            ? 'vence el ' . $mascota->rabia_vencimiento->translatedFormat('j \d\e F \d\e Y')
                . ($estadoRabia && $estadoRabia['estado'] === 'vencido' ? ' (VENCIDO)' : '')
            : null,
        'Castración' => $mascota->castrado
            ? ($mascota->fecha_castracion?->translatedFormat('j \d\e F \d\e Y') ?? 'Sí')
            : 'No',
        'Responsable' => $mascota->propietario->name,
        'Teléfono' => $mascota->propietario->telefono,
    ] as $etiqueta => $valor)
        @if ($valor)
            <tr>
                <td class="etiqueta">{{ $etiqueta }}</td>
                <td>{{ $valor }}</td>
            </tr>
        @endif
    @endforeach
</table>

<h2>Medicación</h2>
@forelse ($tratamientos as $tratamiento)
    <div class="bloque">
        <h3>
            {{ $tratamiento->nombre_medicamento }}
            @if ($tratamiento->estado->estaEnCurso()) — EN CURSO @endif
        </h3>
        <p class="sub">
            {{ $tratamiento->dosis }}
            @if ($tratamiento->frecuencia_horas) · cada {{ $tratamiento->frecuencia_horas }} h @endif
            · {{ $tratamiento->via->etiqueta() }}
            · desde el {{ $tratamiento->fecha_inicio->translatedFormat('j/n/Y') }}
            @if ($tratamiento->duracion_dias) por {{ $tratamiento->duracion_dias }} días @endif
        </p>
        @if ($tratamiento->notas)
            <p class="sub">{{ $tratamiento->notas }}</p>
        @endif
    </div>
@empty
    <p class="vacio">Sin tratamientos registrados.</p>
@endforelse

<h2>Vacunas</h2>
@if ($vacunas->isEmpty())
    <p class="vacio">Sin vacunas registradas.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Vacuna</th>
                <th>Dosis</th>
                <th>Marca y lote</th>
                <th>Próxima</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vacunas as $vacuna)
                <tr>
                    <td>{{ $vacuna->fecha->translatedFormat('j/n/Y') }}</td>
                    <td>{{ $vacuna->nombre_vacuna }}</td>
                    <td>{{ $vacuna->dosis_nro ? $vacuna->dosis_nro . 'ª' : '—' }}</td>
                    <td>{{ collect([$vacuna->marca, $vacuna->lote])->filter()->implode(' / ') ?: '—' }}</td>
                    <td>{{ $vacuna->proxima_dosis?->translatedFormat('j/n/Y') ?? '—' }}</td>
                </tr>
                @if ($vacuna->reacciones)
                    <tr>
                        <td></td>
                        <td colspan="4">Reacción: {{ $vacuna->reacciones }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif

<h2>Desparasitaciones</h2>
@if ($desparasitaciones->isEmpty())
    <p class="vacio">Sin desparasitaciones registradas.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Dosis</th>
                <th>Peso</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($desparasitaciones as $desparasitacion)
                <tr>
                    <td>{{ $desparasitacion->fecha->translatedFormat('j/n/Y') }}</td>
                    <td>{{ $desparasitacion->nombre_medicamento }}</td>
                    <td>{{ $desparasitacion->tipo->etiqueta() }}</td>
                    <td>{{ $desparasitacion->dosis ?? '—' }}</td>
                    <td>{{ $desparasitacion->peso_al_momento ? $desparasitacion->peso_al_momento . ' kg' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Visitas al veterinario</h2>
@forelse ($visitas as $visita)
    <div class="bloque">
        <h3>
            {{ $visita->fecha_hora->translatedFormat('j \d\e F \d\e Y') }} —
            {{ $visita->motivo ?? $visita->tipo->etiqueta() }}
        </h3>
        <p class="sub">
            {{ $visita->tipo->etiqueta() }}
            @if ($visita->veterinaria) · {{ $visita->veterinaria->nombre }} @endif
            @if ($visita->veterinario) · {{ $visita->veterinario->nombre }} @endif
            @if ($visita->temperatura) · {{ $visita->temperatura }} °C @endif
        </p>
        @if ($visita->diagnostico)
            <p><strong>Diagnóstico:</strong> {{ $visita->diagnostico }}</p>
        @endif
        @if ($visita->indicaciones)
            <p><strong>Indicaciones:</strong> {{ $visita->indicaciones }}</p>
        @endif
        @if ($visita->tratamientos->isNotEmpty())
            <p><strong>Se recetó:</strong>
                {{ $visita->tratamientos->map(fn ($t) => $t->nombre_medicamento . ' (' . $t->dosis . ')')->implode('; ') }}
            </p>
        @endif
    </div>
@empty
    <p class="vacio">Sin visitas registradas.</p>
@endforelse

<h2>Peso</h2>
@if ($pesos->isEmpty())
    <p class="vacio">Sin registros de peso.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Peso</th>
                <th>Dónde</th>
                <th>Condición corporal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pesos->sortByDesc('fecha') as $peso)
                <tr>
                    <td>{{ $peso->fecha->translatedFormat('j/n/Y') }}</td>
                    <td>{{ $peso->peso_kg }} kg</td>
                    <td>{{ $peso->origen->etiqueta() }}</td>
                    <td>{{ $peso->condicion_corporal ? $peso->condicion_corporal . '/9' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Alimentación</h2>
@if ($dietas->isEmpty())
    <p class="vacio">Sin dietas registradas.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Alimento</th>
                <th>Ración</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dietas as $dieta)
                <tr>
                    <td>{{ $dieta->fecha_inicio->translatedFormat('j/n/Y') }}</td>
                    <td>{{ $dieta->fecha_fin?->translatedFormat('j/n/Y') ?? 'Vigente' }}</td>
                    <td>{{ trim(($dieta->alimento->marca ?? '') . ' ' . $dieta->alimento->nombre) }}</td>
                    <td>{{ $dieta->racion_diaria_g ? $dieta->racion_diaria_g . ' g/día' : '—' }}</td>
                    <td>{{ $dieta->motivo ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($ciclos->isNotEmpty())
    <h2>Ciclos de celo</h2>
    <table>
        <thead>
            <tr>
                <th>Inicio</th>
                <th>Duración</th>
                <th>Intensidad</th>
                <th>Monta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ciclos as $ciclo)
                <tr>
                    <td>{{ $ciclo->fecha_inicio->translatedFormat('j/n/Y') }}</td>
                    <td>{{ $ciclo->duracion_dias ? $ciclo->duracion_dias . ' días' : 'En curso' }}</td>
                    <td>{{ $ciclo->intensidad?->etiqueta() ?? '—' }}</td>
                    <td>{{ $ciclo->hubo_monta ? 'Sí' : 'No' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($entradas->isNotEmpty())
    <h2>Notas del diario</h2>
    @foreach ($entradas as $entrada)
        <div class="bloque">
            <h3>{{ $entrada->fecha->translatedFormat('j \d\e F \d\e Y') }} — {{ $entrada->encabezado() }}</h3>
            <p class="sub">{{ $entrada->categoria->etiqueta() }}@if ($entrada->animo) · {{ $entrada->animo->etiqueta() }}@endif</p>
            @if ($entrada->titulo)
                <p>{{ $entrada->contenido }}</p>
            @endif
        </div>
    @endforeach
@endif

<p class="pie">
    Generado con Huella. Este documento reúne lo que el responsable de la mascota
    registró; <strong>no es un diagnóstico ni una indicación clínica</strong>.
</p>

</body>
</html>
