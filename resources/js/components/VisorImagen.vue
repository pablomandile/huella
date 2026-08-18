<script setup lang="ts">
import { X } from '@lucide/vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

/*
 * Visor de una imagen a pantalla completa.
 *
 * Lo usan la galería y las tarjetas de documentación: una hoja de libreta
 * fotografiada no se lee en una miniatura de 48px, y abrirla en otra pestaña
 * saca al usuario de la app —y en el celular, de la sesión de scroll—.
 *
 * Decisiones que no son cosméticas:
 *
 * - **`object-contain`, nunca `cover`.** Recortar una imagen que el usuario abrió
 *   para leer es justamente perder lo que vino a ver.
 * - **X propia, no la del `DialogContent`.** La de serie va con `opacity-70` y sin
 *   área táctil: sobre un fondo oscuro casi no se ve, y en el celular no se
 *   acierta. Esta lleva fondo propio y 44px.
 * - **Fondo oscuro en los dos temas.** No es un descuido del modo claro: una foto
 *   se mira sobre negro, y el visor tapa la pantalla entera igual.
 * - Es un `Dialog` de reka-ui y no un div propio, así que trae gratis el foco
 *   atrapado, el cierre con Escape y el rol correcto para lector de pantalla.
 */

const abierto = defineModel<boolean>('abierto', { required: true });

const props = defineProps<{
    src: string;
    alt: string;
    /** Título accesible del visor. */
    titulo: string;
    /** Fecha, epígrafe, nombre del archivo: lo que ubique a la imagen. */
    descripcion?: string | null;
}>();
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent
            :show-close-button="false"
            class="flex h-[100dvh] max-h-none w-screen max-w-none flex-col gap-0 rounded-none border-0 bg-neutral-950 p-0 text-neutral-50"
        >
            <!-- El título es para el lector de pantalla; en pantalla manda la imagen. -->
            <DialogTitle class="sr-only">{{ props.titulo }}</DialogTitle>

            <div
                class="flex min-h-0 flex-1 items-center justify-center p-3 pt-16"
            >
                <img
                    :src="props.src"
                    :alt="props.alt"
                    class="max-h-full max-w-full object-contain"
                />
            </div>

            <div
                v-if="props.descripcion || $slots.acciones"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
            >
                <p
                    v-if="props.descripcion"
                    class="min-w-0 text-sm text-neutral-300"
                >
                    {{ props.descripcion }}
                </p>
                <div class="flex items-center gap-2">
                    <slot name="acciones" />
                </div>
            </div>

            <button
                type="button"
                class="absolute top-3 right-3 flex touch-target items-center justify-center rounded-full bg-black/60 text-neutral-50 ring-offset-2 ring-offset-neutral-950 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-hidden"
                @click="abierto = false"
            >
                <X class="size-5" aria-hidden="true" />
                <span class="sr-only">Cerrar la imagen</span>
            </button>
        </DialogContent>
    </Dialog>
</template>
