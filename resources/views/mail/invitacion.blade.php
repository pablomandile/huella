{{--
    Invitación a mirar la ficha de una mascota.

    Ni un dato clínico: el mail puede caer en la casilla equivocada, y esto es
    todo lo que se filtraría. Español rioplatense con voseo, como toda la app.
--}}
<x-mail::message>
# {{ $invita }} te compartió una ficha

Es la de **{{ $nombreMascota }}**{{ $especie ? ' ('.mb_strtolower($especie).')' : '' }}.

{{--
    La foto va incrustada en el mensaje (`cid:`), no por URL: las imágenes de la
    app se sirven tras verificar propiedad, y quien recibe esto todavía no tiene
    acceso a nada. Es opcional —la mascota puede no tener foto, o la miniatura
    puede fallar— y el mail se manda igual sin ella.

    El `border-radius` lo ignora Outlook de escritorio y la foto se ve cuadrada.
    Es la degradación aceptable: se sigue viendo de quién te hablan.
--}}
@if ($fotoMascota)
<img src="{{ $message->embedData($fotoMascota, 'mascota.jpg', 'image/jpeg') }}"
     alt="{{ $nombreMascota }}"
     width="120"
     height="120"
     style="width: 120px; height: 120px; border-radius: 60px; border: 0; display: block; margin: 20px auto;">
@endif

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
