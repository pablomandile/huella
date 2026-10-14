<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

/*
 * El logo de la cabecera del menú.
 *
 * Con la sidebar abierta va el logo horizontal, que ya trae el nombre escrito:
 * por eso acá no hay ningún texto al lado. Cuando se colapsa a la tira de
 * íconos no entra, así que en ese estado se muestra el ícono cuadrado.
 *
 * Las clases `group-data-*` las resuelve el contenedor de la sidebar de
 * escritorio. En el celular la sidebar es un sheet y no tiene ese contenedor,
 * así que no matchea ninguna y queda el logo horizontal, que es lo que
 * corresponde: ahí el ancho sobra.
 */
</script>

<template>
    <!-- Sidebar colapsada: lo único que entra en la tira de íconos. -->
    <div
        class="hidden aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md group-data-[collapsible=icon]:flex"
    >
        <AppLogoIcon class="size-8" />
    </div>

    <!--
        El archivo tiene fondo blanco opaco, sin transparencia, así que se le da
        una base blanca redondeada en vez de invertirlo, que le arruinaría los
        colores.

        La base va en los **dos** modos y no solo en oscuro: el lienzo de la app
        es un menta claro, no blanco puro, y sin ella el rectángulo blanco del
        archivo se recorta contra el fondo. Con la base, ese blanco se lee como
        una pastilla a propósito.

        El relleno va en este contenedor y no en la imagen: aplicado a la imagen
        le comía 8px de alto y el logo quedaba más chico.
    -->
    <div class="rounded-md bg-white p-1 group-data-[collapsible=icon]:hidden">
        <img
            src="/img/huella-logo-horizontal.webp"
            alt="Huella"
            width="681"
            height="222"
            class="h-8 w-auto"
        />
    </div>
</template>
