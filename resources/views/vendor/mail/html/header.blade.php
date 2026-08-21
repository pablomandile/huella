{{--
    Encabezado de todos los mails que manda la app.

    Pisa al del framework, que pone el nombre como texto salvo que diga
    "Laravel", único caso en el que muestra un logo. Publicar solo este archivo
    alcanza: `mail` es un namespace con dos rutas y Blade cae a la del paquete
    para los componentes que no estén acá.

    **PNG y no el WebP de la app.** Outlook de escritorio dibuja con el motor de
    Word y no entiende WebP: se vería el recuadro de imagen rota.

    **El `alt` dice "Huella" y no "logo de Huella"** porque la mayoría de los
    clientes bloquea las imágenes remotas hasta que el usuario las habilita, y
    ese texto es el encabezado que va a leer mucha gente.

    **`width` y `height` como atributos**, no solo en el style: sin ellos Outlook
    lo dibuja al tamaño del archivo, que es el doble para que no se vea borroso
    en pantallas retina.
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('img/huella-logo-email.png') }}"
     alt="{{ trim($slot) }}"
     width="200"
     height="65"
     style="width: 200px; max-width: 200px; height: auto; border: 0; display: block;">
</a>
</td>
</tr>
