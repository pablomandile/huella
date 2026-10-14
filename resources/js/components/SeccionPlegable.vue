<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

/*
 * Una sección de la ficha que en el inicio arranca cerrada y se despliega con
 * un toque. Con `plegable` en false no dibuja nada propio: muestra el contenido
 * tal cual, que es lo que hace la pantalla de la mascota.
 *
 * Recuerda si se abrió, por pestaña, en `sessionStorage`. No es un capricho:
 * subir una foto o una hoja de la libreta vuelve con `back()` y la página se
 * vuelve a montar, así que sin esto la sección se cerraría justo después de
 * usarla. Es una comodidad del que mira, y si el navegador no deja guardar
 * —ventana privada, datos bloqueados— simplemente arranca cerrada.
 *
 * El disparador es un botón real de ancho completo: se toca con el pulgar en
 * cualquier parte, y reka-ui le pone `aria-expanded` solo.
 */

const props = defineProps<{
    plegable: boolean;
    titulo: string;
    /** Lo que se ve con la sección cerrada: "3 fotos", "2 hojas cargadas". */
    resumen?: string;
    /** Clave con la que se recuerda abierta o cerrada. */
    clave: string;
}>();

const CLAVE = `huella:seccion:${props.clave}`;

function leer(): boolean {
    try {
        return sessionStorage.getItem(CLAVE) === '1';
    } catch {
        return false;
    }
}

const abierta = ref(leer());

watch(abierta, (valor) => {
    try {
        sessionStorage.setItem(CLAVE, valor ? '1' : '0');
    } catch {
        // Sin almacenamiento: la próxima vez arranca cerrada, nada más.
    }
});
</script>

<template>
    <slot v-if="!plegable" />

    <Collapsible v-else v-model:open="abierta" class="flex flex-col gap-4">
        <CollapsibleTrigger
            class="group flex min-h-14 w-full items-center gap-3 rounded-xl border bg-card px-6 py-3 text-left shadow-sm transition-colors hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span class="flex-1">
                <span class="block leading-none font-semibold">{{
                    titulo
                }}</span>
                <span
                    v-if="resumen"
                    class="mt-1 block text-sm text-muted-foreground"
                    >{{ resumen }}</span
                >
            </span>
            <ChevronDown
                class="size-5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                aria-hidden="true"
            />
        </CollapsibleTrigger>

        <CollapsibleContent class="flex flex-col gap-6">
            <slot />
        </CollapsibleContent>
    </Collapsible>
</template>
