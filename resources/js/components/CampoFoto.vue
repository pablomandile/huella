<script setup lang="ts">
import { Camera, ImageUp, X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';

/*
 * Selector de foto pensado para el celular: "Sacar foto" abre la cámara
 * trasera directo (capture="environment") y "Elegir de la galería" abre el
 * selector normal. Es UN solo input —dos inputs con el mismo name meterían
 * una entrada vacía en el FormData que puede pisar al archivo real— y el
 * atributo capture se pone o se saca según qué botón se tocó.
 */

defineProps<{
    /** Nombre del campo en el form (name del input). */
    name: string;
}>();

const input = ref<HTMLInputElement>();
const vistaPrevia = ref<string | null>(null);

function abrir(conCamara: boolean) {
    const elemento = input.value;

    if (!elemento) {
        return;
    }

    if (conCamara) {
        elemento.setAttribute('capture', 'environment');
    } else {
        elemento.removeAttribute('capture');
    }

    elemento.click();
}

function alElegir(evento: Event) {
    const archivo = (evento.target as HTMLInputElement).files?.[0];

    if (!archivo) {
        return;
    }

    liberar();
    vistaPrevia.value = URL.createObjectURL(archivo);
}

function quitar() {
    if (input.value) {
        input.value.value = '';
    }

    liberar();
    vistaPrevia.value = null;
}

function liberar() {
    if (vistaPrevia.value) {
        URL.revokeObjectURL(vistaPrevia.value);
    }
}

onBeforeUnmount(liberar);
</script>

<template>
    <div class="flex items-center gap-4">
        <div
            class="relative flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-muted"
        >
            <img
                v-if="vistaPrevia"
                :src="vistaPrevia"
                alt="Vista previa de la foto"
                class="size-full object-cover"
            />
            <slot v-else name="placeholder">
                <Camera
                    class="size-8 text-muted-foreground"
                    aria-hidden="true"
                />
            </slot>

            <button
                v-if="vistaPrevia"
                type="button"
                class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-background/80 text-foreground shadow"
                aria-label="Quitar la foto elegida"
                @click="quitar"
            >
                <X class="size-3.5" aria-hidden="true" />
            </button>
        </div>

        <div class="flex flex-col gap-2">
            <input
                ref="input"
                type="file"
                :name="name"
                accept="image/*"
                class="hidden"
                @change="alElegir"
            />

            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="abrir(true)"
            >
                <Camera class="size-4" aria-hidden="true" />
                Sacar foto
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="abrir(false)"
            >
                <ImageUp class="size-4" aria-hidden="true" />
                Elegir de la galería
            </Button>
        </div>
    </div>
</template>
