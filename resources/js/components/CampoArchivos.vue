<script setup lang="ts">
import { Camera, FileText, Paperclip, X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';

/*
 * Varios archivos a la vez: recetas, análisis, radiografías.
 *
 * Igual que CampoFoto, es UN solo input y el atributo `capture` se pone o se
 * saca según el botón — dos inputs con el mismo name meten una entrada vacía
 * en el FormData. Acepta PDF además de imágenes, porque la mitad de los
 * estudios llegan así por mail.
 *
 * Los archivos se acumulan en un DataTransfer propio: el input nativo pisa la
 * selección anterior cada vez que se abre, y acá se quiere sumar.
 */

defineProps<{
    /** Se envía como `name[]`. */
    name: string;
}>();

type Elegido = { archivo: File; vistaPrevia: string | null };

const input = ref<HTMLInputElement>();
const elegidos = ref<Elegido[]>([]);

function abrir(conCamara: boolean) {
    const elemento = input.value;

    if (!elemento) {
        return;
    }

    if (conCamara) {
        elemento.setAttribute('capture', 'environment');
        elemento.setAttribute('accept', 'image/*');
    } else {
        elemento.removeAttribute('capture');
        elemento.setAttribute('accept', 'image/*,application/pdf');
    }

    elemento.click();
}

function alElegir(evento: Event) {
    const nuevos = [...((evento.target as HTMLInputElement).files ?? [])];

    for (const archivo of nuevos) {
        elegidos.value.push({
            archivo,
            vistaPrevia: archivo.type.startsWith('image/')
                ? URL.createObjectURL(archivo)
                : null,
        });
    }

    sincronizar();
}

function quitar(indice: number) {
    const [fuera] = elegidos.value.splice(indice, 1);

    if (fuera?.vistaPrevia) {
        URL.revokeObjectURL(fuera.vistaPrevia);
    }

    sincronizar();
}

/** Reescribe el FileList del input con lo que quedó en la lista. */
function sincronizar() {
    if (!input.value) {
        return;
    }

    const datos = new DataTransfer();
    elegidos.value.forEach(({ archivo }) => datos.items.add(archivo));
    input.value.files = datos.files;
}

onBeforeUnmount(() => {
    elegidos.value.forEach(({ vistaPrevia }) => {
        if (vistaPrevia) {
            URL.revokeObjectURL(vistaPrevia);
        }
    });
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <input
            ref="input"
            type="file"
            :name="`${name}[]`"
            multiple
            accept="image/*,application/pdf"
            class="hidden"
            @change="alElegir"
        />

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="touch-target"
                @click="abrir(true)"
            >
                <Camera class="size-4" aria-hidden="true" />
                Sacar foto
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="touch-target"
                @click="abrir(false)"
            >
                <Paperclip class="size-4" aria-hidden="true" />
                Adjuntar archivo
            </Button>
        </div>

        <ul v-if="elegidos.length" class="flex flex-wrap gap-2">
            <li
                v-for="(elegido, indice) in elegidos"
                :key="`${elegido.archivo.name}-${indice}`"
                class="relative flex size-20 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted"
            >
                <img
                    v-if="elegido.vistaPrevia"
                    :src="elegido.vistaPrevia"
                    :alt="elegido.archivo.name"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex flex-col items-center gap-1 p-1 text-center"
                >
                    <FileText
                        class="size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span
                        class="w-full truncate text-[10px] text-muted-foreground"
                    >
                        {{ elegido.archivo.name }}
                    </span>
                </div>

                <button
                    type="button"
                    class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-background/90 text-foreground shadow"
                    :aria-label="`Quitar ${elegido.archivo.name}`"
                    @click="quitar(indice)"
                >
                    <X class="size-3.5" aria-hidden="true" />
                </button>
            </li>
        </ul>
    </div>
</template>
