<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Claro' },
    { value: 'dark', Icon: Moon, label: 'Oscuro' },
    { value: 'system', Icon: Monitor, label: 'Sistema' },
] as const;
</script>

<template>
    <!--
        Los colores van por tokens del tema y no por la escala `neutral` del
        starter kit: el lienzo de la app está teñido, y un gris puro al lado
        de un menta se ve sucio, no neutro. `muted` es la pista y `card`
        (blanco en claro) la pastilla activa; en oscuro la activa va con
        `accent`, que es MÁS clara que la pista —al revés que `card`—, porque
        ahí lo que se eleva se aclara.
    -->
    <!--
        Grilla de tres y no `inline-flex`: con letra grande en 320px, las tres
        pastillas en una fila de ancho intrínseco se salían de la pantalla.
    -->
    <div class="grid w-full max-w-sm grid-cols-3 gap-1 rounded-lg bg-muted p-1">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex min-w-0 flex-wrap items-center justify-center gap-x-1.5 rounded-md px-2 py-1.5 transition-colors',
                appearance === value
                    ? 'bg-card text-foreground shadow-xs dark:bg-accent dark:text-accent-foreground'
                    : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="h-4 w-4 shrink-0" />
            <span class="min-w-0 text-sm wrap-anywhere">{{ label }}</span>
        </button>
    </div>
</template>
