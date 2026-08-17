{{--
    Mail de recordatorios. En español rioplatense con voseo, como toda la app.

    El sistema registra, no aconseja: acá se avisa qué fecha quedó anotada y
    nada más. Ninguna recomendación clínica.
--}}
<x-mail::message>
# Hola, {{ $nombre }}

Esto es lo que te toca agendar:

@foreach ($avisos as $aviso)
**{{ $aviso['titulo'] }}**
{{ ucfirst($aviso['fecha']) }} — {{ $aviso['faltan'] }}.
@if ($aviso['descripcion'])
{{ $aviso['descripcion'] }}
@endif

@endforeach

<x-mail::button :url="route('recordatorios.index')">
Ver mis recordatorios
</x-mail::button>

Desde ahí podés marcarlos como hechos, posponerlos o descartarlos.

Gracias por usar Huella,<br>
el equipo

<x-slot:subcopy>
Huella registra información, no da consejos clínicos. Ante cualquier duda,
consultá a tu veterinario.
</x-slot:subcopy>
</x-mail::message>
