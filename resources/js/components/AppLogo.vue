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
        El archivo tiene fondo blanco opaco, sin transparencia. En modo oscuro
        se le da una base clara redondeada en vez de invertirlo, que le
        arruinaría los colores.

        El relleno va en este contenedor y no en la imagen: aplicado a la imagen
        le comía 8px de alto y el logo quedaba más chico en oscuro que en claro.
    -->
    <div
        class="rounded-md group-data-[collapsible=icon]:hidden dark:bg-white dark:p-1"
    >
        <img
            src="/img/huella-logo-horizontal.webp"
            alt="Huella"
            width="681"
            height="222"
            class="h-8 w-auto"
        />
    </div>
</template>
