{{--
    La ficha que se ve con un enlace compartido, sin cuenta.

    Blade suelta y no Inertia: una página Inertia arrastraría los props
    compartidos —`auth.user`, todas las mascotas del usuario, la mascota activa—
    dentro de una página pública. Ver el docblock de FichaCompartidaController.

    Solo se carga el CSS con @vite, nunca `app.ts`: no hay nada que montar y
    cargar la app entera para un documento sería traer el service worker, el
    router y la sesión a una pantalla que no los usa.

    Mismo contenido que el PDF, con las **alergias primero** por la misma razón:
    es el dato que puede cambiar una decisión en una urgencia.
--}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    {{-- El header X-Robots-Tag ya lo manda el middleware; este meta es el cinturón
         además de los tirantes, y cubre al rastreador que ignore el header. --}}
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">

    {{-- Título genérico y **sin tags og:** a propósito: un og:title con el nombre
         de la mascota y un og:image con su foto convertirían cada reenvío por
         WhatsApp en una tarjetita con datos de la ficha. --}}
    <title>Ficha compartida — Huella</title>

    @vite('resources/css/app.css')
</head>
<body class="bg-background text-foreground min-h-full antialiased">

<main class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10">

    <header class="mb-6 flex items-center gap-4">
        @if ($mascota->foto_perfil)
            <img
                src="{{ route('compartido.foto', ['token' => $enlace->token, 'min' => 1]) }}"
                alt="Foto de {{ $mascota->nombre }}"
                class="size-16 shrink-0 rounded-full object-cover sm:size-20"
            >
        @endif
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold sm:text-3xl">{{ $mascota->nombre }}</h1>
            <p class="text-muted-foreground text-sm">
                {{ $mascota->especie->etiqueta() }}{{ $mascota->raza ? ' · '.$mascota->raza : '' }}{{ $mascota->edad ? ' · '.$mascota->edad : '' }}
            </p>
        </div>
    </header>

    <div class="bg-muted mb-8 rounded-lg p-4 text-sm">
        <p>
            <strong>{{ $mascota->propietario->name }}</strong> compartió esta ficha.
            Es de <strong>solo lectura</strong>@if ($venceEl) y el enlace vence el {{ $venceEl }}@endif.
        </p>
        <p class="mt-2">
            <a
                href="{{ route('compartido.pdf', $enlace->token) }}"
                class="font-medium underline underline-offset-4"
            >Descargar en PDF</a>
        </p>
    </div>

    {{-- Alergias primero, antes de la ficha --}}
    <section class="border-destructive/40 mb-8 rounded-lg border-2 p-4">
        <h2 class="mb-3 text-lg font-semibold">Alergias</h2>

        @forelse ($alergias as $alergia)
            <div class="border-border/60 border-b py-2 last:border-0 last:pb-0 first:pt-0">
                <p>
                    <strong @class(['text-destructive' => $alergia->severidad?->value === 'severa'])>
                        {{ $alergia->agente }}
                    </strong>
                    <span class="text-muted-foreground">
                        ({{ $alergia->tipo->etiqueta() }}{{ $alergia->severidad ? ', '.$alergia->severidad->etiqueta() : '' }})
                    </span>
                </p>
                @if ($alergia->sintomas)
                    <p class="text-muted-foreground text-sm">{{ $alergia->sintomas }}</p>
                @endif
            </div>
        @empty
            <p class="text-muted-foreground text-sm">Sin alergias registradas.</p>
        @endforelse
    </section>

    <x-publico.seccion titulo="Ficha">
        <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-[max-content_1fr]">
            {{--
                Lo que NO está en esta lista está afuera a propósito: el seguro
                (compañía, póliza, vencimiento) es dato financiero, y del
                propietario va solo el nombre de pila. Quien mira la ficha está
                parado al lado suyo; no hace falta publicar su contacto.
            --}}
            @foreach ([
                'Nacimiento' => $mascota->fecha_nacimiento?->translatedFormat('j \d\e F \d\e Y')
                    . ($mascota->fecha_nacimiento_estimada ? ' (estimado)' : ''),
                'Sexo' => $mascota->sexo->etiqueta(),
                'Color' => $mascota->color,
                'Pelaje' => $mascota->tipo_pelaje?->etiqueta(),
                'Señas particulares' => $mascota->senias_particulares,
                'Microchip' => $mascota->microchip,
                'Libreta sanitaria' => $mascota->libreta_sanitaria,
                'Certificado de rabia' => $mascota->rabia_vencimiento
                    ? 'vence el ' . $mascota->rabia_vencimiento->translatedFormat('j \d\e F \d\e Y')
                        . ($estadoRabia && $estadoRabia['estado'] === 'vencido' ? ' (VENCIDO)' : '')
                    : null,
                'Castración' => $mascota->castrado
                    ? ($mascota->fecha_castracion?->translatedFormat('j \d\e F \d\e Y') ?? 'Sí')
                    : 'No',
                'Responsable' => $mascota->propietario->name,
            ] as $etiqueta => $valor)
                @if ($valor)
                    <dt class="text-muted-foreground text-sm">{{ $etiqueta }}</dt>
                    <dd class="mb-1 text-sm sm:mb-0">{{ $valor }}</dd>
                @endif
            @endforeach
        </dl>
    </x-publico.seccion>

    @if ($adjuntos['documentos'])
        <x-publico.seccion titulo="Documentación">
            <ul class="space-y-2">
                @foreach ($adjuntos['documentos'] as $documento)
                    <li>
                        <a
                            href="{{ route('compartido.adjunto', [$enlace->token, $documento]) }}"
                            class="text-sm font-medium underline underline-offset-4"
                        >
                            {{ $documento->tipo->etiqueta() }}{{ $documento->descripcion ? ' — '.$documento->descripcion : '' }}
                        </a>
                        <span class="text-muted-foreground text-xs">{{ $documento->tamanio_legible }}</span>
                    </li>
                @endforeach
            </ul>
        </x-publico.seccion>
    @endif

    <x-publico.seccion titulo="Medicación">
        @forelse ($tratamientos as $tratamiento)
            <div class="border-border/60 border-b py-3 first:pt-0 last:border-0 last:pb-0">
                <p class="font-medium">
                    {{ $tratamiento->nombre_medicamento }}
                    @if ($tratamiento->estado->estaEnCurso())
                        <span class="bg-primary/10 text-primary ml-1 rounded px-1.5 py-0.5 text-xs font-semibold">en curso</span>
                    @endif
                </p>
                <p class="text-muted-foreground text-sm">
                    {{ $tratamiento->dosis }}{{ $tratamiento->frecuencia_horas ? ' · cada '.$tratamiento->frecuencia_horas.' h' : '' }}
                    · {{ $tratamiento->via->etiqueta() }}
                    · desde el {{ $tratamiento->fecha_inicio->translatedFormat('j/n/Y') }}{{ $tratamiento->duracion_dias ? ' por '.$tratamiento->duracion_dias.' días' : '' }}
                </p>
                @if ($tratamiento->notas)
                    <p class="text-muted-foreground text-sm">{{ $tratamiento->notas }}</p>
                @endif
            </div>
        @empty
            <p class="text-muted-foreground text-sm">Sin tratamientos registrados.</p>
        @endforelse
    </x-publico.seccion>

    <x-publico.seccion titulo="Vacunas">
        @if ($vacunas->isEmpty())
            <p class="text-muted-foreground text-sm">Sin vacunas registradas.</p>
        @else
            <x-publico.tabla :encabezados="['Fecha', 'Vacuna', 'Dosis', 'Próxima']">
                @foreach ($vacunas as $vacuna)
                    <tr class="border-border/60 border-b last:border-0">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $vacuna->fecha->translatedFormat('j/n/Y') }}</td>
                        <td class="py-2 pr-3">{{ $vacuna->nombre_vacuna }}</td>
                        <td class="py-2 pr-3">{{ $vacuna->dosis_nro ? $vacuna->dosis_nro.'ª' : '—' }}</td>
                        <td class="py-2 whitespace-nowrap">{{ $vacuna->proxima_dosis?->translatedFormat('j/n/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-publico.tabla>
        @endif
    </x-publico.seccion>

    <x-publico.seccion titulo="Desparasitaciones">
        @if ($desparasitaciones->isEmpty())
            <p class="text-muted-foreground text-sm">Sin desparasitaciones registradas.</p>
        @else
            <x-publico.tabla :encabezados="['Fecha', 'Producto', 'Tipo', 'Peso']">
                @foreach ($desparasitaciones as $desparasitacion)
                    <tr class="border-border/60 border-b last:border-0">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $desparasitacion->fecha->translatedFormat('j/n/Y') }}</td>
                        <td class="py-2 pr-3">{{ $desparasitacion->nombre_medicamento }}</td>
                        <td class="py-2 pr-3">{{ $desparasitacion->tipo->etiqueta() }}</td>
                        <td class="py-2 whitespace-nowrap">{{ $desparasitacion->peso_al_momento ? $desparasitacion->peso_al_momento.' kg' : '—' }}</td>
                    </tr>
                @endforeach
            </x-publico.tabla>
        @endif
    </x-publico.seccion>

    <x-publico.seccion titulo="Visitas al veterinario">
        @forelse ($visitas as $visita)
            <div class="border-border/60 border-b py-3 first:pt-0 last:border-0 last:pb-0">
                <p class="font-medium">
                    {{ $visita->fecha_hora->translatedFormat('j \d\e F \d\e Y') }} —
                    {{ $visita->motivo ?? $visita->tipo->etiqueta() }}
                </p>
                <p class="text-muted-foreground text-sm">
                    {{ $visita->tipo->etiqueta() }}{{ $visita->veterinaria ? ' · '.$visita->veterinaria->nombre : '' }}{{ $visita->veterinario ? ' · '.$visita->veterinario->nombre : '' }}{{ $visita->temperatura ? ' · '.$visita->temperatura.' °C' : '' }}
                </p>
                @if ($visita->diagnostico)
                    <p class="mt-1 text-sm"><strong>Diagnóstico:</strong> {{ $visita->diagnostico }}</p>
                @endif
                @if ($visita->indicaciones)
                    <p class="text-sm"><strong>Indicaciones:</strong> {{ $visita->indicaciones }}</p>
                @endif
                @if ($visita->tratamientos->isNotEmpty())
                    <p class="text-sm"><strong>Se recetó:</strong>
                        {{ $visita->tratamientos->map(fn ($t) => $t->nombre_medicamento.' ('.$t->dosis.')')->implode('; ') }}
                    </p>
                @endif
            </div>
        @empty
            <p class="text-muted-foreground text-sm">Sin visitas registradas.</p>
        @endforelse
    </x-publico.seccion>

    @if ($adjuntos['clinicos'])
        <x-publico.seccion titulo="Estudios y recetas">
            <ul class="space-y-2">
                @foreach ($adjuntos['clinicos'] as $adjunto)
                    <li>
                        <a
                            href="{{ route('compartido.adjunto', [$enlace->token, $adjunto]) }}"
                            class="text-sm font-medium underline underline-offset-4"
                        >
                            {{ $adjunto->tipo->etiqueta() }}{{ $adjunto->descripcion ? ' — '.$adjunto->descripcion : '' }}
                        </a>
                        <span class="text-muted-foreground text-xs">{{ $adjunto->tamanio_legible }}</span>
                    </li>
                @endforeach
            </ul>
        </x-publico.seccion>
    @endif

    <x-publico.seccion titulo="Peso">
        @if ($pesos->isEmpty())
            <p class="text-muted-foreground text-sm">Sin registros de peso.</p>
        @else
            <x-publico.tabla :encabezados="['Fecha', 'Peso', 'Dónde']">
                @foreach ($pesos->sortByDesc('fecha') as $peso)
                    <tr class="border-border/60 border-b last:border-0">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $peso->fecha->translatedFormat('j/n/Y') }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $peso->peso_kg }} kg</td>
                        <td class="py-2">{{ $peso->origen->etiqueta() }}</td>
                    </tr>
                @endforeach
            </x-publico.tabla>
        @endif
    </x-publico.seccion>

    <x-publico.seccion titulo="Alimentación">
        @if ($dietas->isEmpty())
            <p class="text-muted-foreground text-sm">Sin dietas registradas.</p>
        @else
            <x-publico.tabla :encabezados="['Desde', 'Hasta', 'Alimento', 'Ración']">
                @foreach ($dietas as $dieta)
                    <tr class="border-border/60 border-b last:border-0">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $dieta->fecha_inicio->translatedFormat('j/n/Y') }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $dieta->fecha_fin?->translatedFormat('j/n/Y') ?? 'Vigente' }}</td>
                        <td class="py-2 pr-3">{{ trim(($dieta->alimento->marca ?? '').' '.$dieta->alimento->nombre) }}</td>
                        <td class="py-2 whitespace-nowrap">{{ $dieta->racion_diaria_g ? $dieta->racion_diaria_g.' g/día' : '—' }}</td>
                    </tr>
                @endforeach
            </x-publico.tabla>
        @endif
    </x-publico.seccion>

    @if ($ciclos->isNotEmpty())
        <x-publico.seccion titulo="Ciclos de celo">
            <x-publico.tabla :encabezados="['Inicio', 'Duración', 'Intensidad']">
                @foreach ($ciclos as $ciclo)
                    <tr class="border-border/60 border-b last:border-0">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $ciclo->fecha_inicio->translatedFormat('j/n/Y') }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $ciclo->duracion_dias ? $ciclo->duracion_dias.' días' : 'En curso' }}</td>
                        <td class="py-2">{{ $ciclo->intensidad?->etiqueta() ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-publico.tabla>
        </x-publico.seccion>
    @endif

    @if ($entradas->isNotEmpty())
        <x-publico.seccion titulo="Notas del diario">
            @foreach ($entradas as $entrada)
                <div class="border-border/60 border-b py-3 first:pt-0 last:border-0 last:pb-0">
                    <p class="font-medium">
                        {{ $entrada->fecha->translatedFormat('j \d\e F \d\e Y') }} — {{ $entrada->encabezado() }}
                    </p>
                    <p class="text-muted-foreground text-sm">
                        {{ $entrada->categoria->etiqueta() }}{{ $entrada->animo ? ' · '.$entrada->animo->etiqueta() : '' }}
                    </p>
                    @if ($entrada->titulo)
                        <p class="mt-1 text-sm">{{ $entrada->contenido }}</p>
                    @endif
                </div>
            @endforeach
        </x-publico.seccion>
    @endif

    <footer class="text-muted-foreground mt-10 border-t pt-6 text-xs">
        <p>
            Generado con Huella el {{ $generado->translatedFormat('j \d\e F \d\e Y, H:i') }}.
            Reúne lo que el responsable de la mascota registró;
            <strong>no es un diagnóstico ni una indicación clínica</strong>.
        </p>
    </footer>

</main>

</body>
</html>
