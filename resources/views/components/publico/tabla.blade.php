{{-- Tabla de la ficha compartida.

     El `overflow-x-auto` es lo que importa: se lee en un celular en el mostrador
     de la veterinaria, y una tabla de cuatro columnas no entra. Scrollea la
     tabla, nunca la página. --}}
@props(['encabezados' => []])

<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-border border-b">
                @foreach ($encabezados as $encabezado)
                    <th scope="col" class="text-muted-foreground py-2 pr-3 font-medium whitespace-nowrap">
                        {{ $encabezado }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
