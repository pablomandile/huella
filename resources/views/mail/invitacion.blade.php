{{--
    Invitación a mirar la ficha de una mascota.

    Ni un dato clínico: el mail puede caer en la casilla equivocada, y esto es
    todo lo que se filtraría. Español rioplatense con voseo, como toda la app.
--}}
<x-mail::message>
# {{ $invita }} te compartió una ficha

Es la de **{{ $nombreMascota }}**{{ $especie ? ' ('.mb_strtolower($especie).')' : '' }}.
Vas a poder ver su historial completo: visitas, vacunas, tratamientos, peso y
todo lo que tenga cargado.

@if ($puedeEditar)
**Como cuidador.** Además de mirar, vas a poder registrar las tomas de
medicación, pesos, visitas y notas.
@else
**Solo lectura.** No vas a poder cargar ni modificar nada.
@endif

<x-mail::button :url="$enlace">
Ver la ficha de {{ $nombreMascota }}
</x-mail::button>

El enlace vence el {{ $vence }}. Si ya se te pasó, pedile a {{ $invita }} que te
lo mande de nuevo.

<x-slot:subcopy>
Si no esperabas esta invitación, ignorá el mensaje: no se creó ninguna cuenta ni
se compartió nada con vos hasta que abras el enlace.
</x-slot:subcopy>
</x-mail::message>
