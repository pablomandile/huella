{{-- Un bloque de la ficha compartida. Existe solo para no repetir el h2 y el
     margen quince veces en la vista. --}}
@props(['titulo'])

<section class="mb-8">
    <h2 class="mb-3 text-lg font-semibold">{{ $titulo }}</h2>
    {{ $slot }}
</section>
