<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

/*
 * La pregunta de `useSalidaProtegida` antes de irse de un formulario a medio
 * cargar. Sheet desde abajo, como todo en el celular: los tres botones quedan
 * donde llega el pulgar.
 *
 * Cerrarlo sin elegir —tocar afuera, la X, Escape— es «seguir cargando»: ante
 * la duda, lo que no pierde nada.
 */

defineProps<{
    abierto: boolean;
    /** "la visita": completa "¿Salir sin terminar …?" */
    queSeCarga: string;
    /** Hay archivos elegidos, que no entran en el borrador. */
    conArchivos: boolean;
}>();

const emit = defineEmits<{
    borrador: [];
    salir: [];
    seguir: [];
}>();
</script>

<template>
    <Sheet
        :open="abierto"
        @update:open="(abrir: boolean) => !abrir && emit('seguir')"
    >
        <SheetContent
            side="bottom"
            class="mx-auto w-full max-w-lg gap-0 rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
        >
            <SheetHeader>
                <SheetTitle>¿Salir sin terminar {{ queSeCarga }}?</SheetTitle>
                <SheetDescription>
                    Lo que cargaste todavía no está guardado. Podés dejarlo como
                    borrador en este teléfono y seguir después.
                </SheetDescription>
                <p v-if="conArchivos" class="text-sm text-muted-foreground">
                    Los archivos que elegiste no entran en el borrador: al
                    volver vas a tener que elegirlos de nuevo.
                </p>
            </SheetHeader>

            <div class="flex flex-col gap-2 p-4">
                <Button
                    class="h-auto touch-target w-full whitespace-normal"
                    @click="emit('borrador')"
                >
                    Guardar borrador y salir
                </Button>
                <Button
                    variant="outline"
                    class="h-auto touch-target w-full whitespace-normal text-destructive"
                    @click="emit('salir')"
                >
                    Sí, salir sin guardar
                </Button>
                <Button
                    variant="ghost"
                    class="h-auto touch-target w-full whitespace-normal"
                    @click="emit('seguir')"
                >
                    No, seguir cargando
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
