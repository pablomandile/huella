<script setup lang="ts">
import { FilePlus, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

/*
 * Selector de varios archivos (fotos o PDF) para la documentación de la mascota.
 *
 * A diferencia de `CampoFoto` acá **no hay botón de cámara**. `capture` fuerza a
 * la cámara y en la práctica anula el `multiple`: se podría sacar una sola foto
 * por vez. Sin `capture`, el selector del celular ya ofrece cámara, galería y
 * archivos en la misma pantalla, que es lo que hace falta para una libreta de
 * varias hojas o un PDF que llegó por mail.
 *
 * Es UN solo input: dos con el mismo `name` meten una entrada vacía en el
 * FormData que puede pisar a los archivos reales.
 */

const props = defineProps<{
    /** Nombre del campo. Va con `[]` para que Laravel lo lea como array. */
    name: string;
}>();

const input = ref<HTMLInputElement>();
const elegidos = ref<string[]>([]);

function alElegir(evento: Event) {
    const archivos = (evento.target as HTMLInputElement).files;

    elegidos.value = archivos ? Array.from(archivos).map((a) => a.name) : [];
}

function limpiar() {
    if (input.value) {
        input.value.value = '';
    }

    elegidos.value = [];
}

defineExpose({ limpiar });
</script>

<template>
    <div class="flex flex-col gap-3">
        <input
            ref="input"
            type="file"
            :name="props.name"
            multiple
            accept="image/jpeg,image/png,image/webp,application/pdf"
            class="hidden"
            @change="alElegir"
        />

        <Button
            type="button"
            variant="outline"
            class="touch-target justify-start"
            @click="input?.click()"
        >
            <FilePlus class="size-4" aria-hidden="true" />
            {{
                elegidos.length ? 'Cambiar la selección' : 'Elegir fotos o PDF'
            }}
        </Button>

        <ul
            v-if="elegidos.length"
            class="flex flex-col gap-1 text-sm text-muted-foreground"
        >
            <li
                v-for="(nombre, i) in elegidos"
                :key="`${nombre}-${i}`"
                class="truncate"
            >
                {{ nombre }}
            </li>
        </ul>

        <Button
            v-if="elegidos.length"
            type="button"
            variant="ghost"
            size="sm"
            class="self-start"
            @click="limpiar"
        >
            <X class="size-4" aria-hidden="true" />
            Quitar {{ elegidos.length === 1 ? 'el archivo' : 'los archivos' }}
        </Button>
    </div>
</template>
