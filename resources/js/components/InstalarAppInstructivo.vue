<script setup lang="ts">
import { Share } from '@lucide/vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/*
 * Pasos para instalar a mano. Hacen falta en dos casos:
 *  - iOS Safari, que nunca dispara `beforeinstallprompt`.
 *  - Cualquier navegador donde el prompt nativo ya se consumió: sirve una sola
 *    vez, incluso si el usuario lo descartó sin querer.
 */

defineProps<{ esIos: boolean }>();

const abierto = defineModel<boolean>('abierto', { required: true });
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Instalar Huella</DialogTitle>
                <DialogDescription>
                    Queda como una app más, con su ícono en la pantalla de
                    inicio.
                </DialogDescription>
            </DialogHeader>

            <ol
                v-if="esIos"
                class="list-inside list-decimal space-y-3 text-sm text-muted-foreground"
            >
                <li>
                    Tocá
                    <Share class="inline size-4 align-text-bottom" />
                    <strong class="text-foreground">Compartir</strong>, en la
                    barra de Safari.
                </li>
                <li>
                    Bajá y elegí
                    <strong class="text-foreground">Agregar a inicio</strong>.
                </li>
                <li>
                    Confirmá con
                    <strong class="text-foreground">Agregar</strong>.
                </li>
            </ol>

            <ol
                v-else
                class="list-inside list-decimal space-y-3 text-sm text-muted-foreground"
            >
                <li>Abrí el menú del navegador.</li>
                <li>
                    Elegí
                    <strong class="text-foreground">Instalar aplicación</strong>
                    o <strong class="text-foreground">Agregar a inicio</strong>.
                </li>
            </ol>
        </DialogContent>
    </Dialog>
</template>
