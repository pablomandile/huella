<script setup lang="ts">
import { useOnline } from '@vueuse/core';
import { WifiOff } from '@lucide/vue';

/*
 * Huella no guarda datos offline por decisión de diseño: son datos clínicos y
 * mostrar una dosis desactualizada es peor que decir "sin conexión". Por eso el
 * aviso es explícito en vez de fingir que la app sigue funcionando.
 */
const enLinea = useOnline();
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-200"
        enter-from-class="-translate-y-full"
        leave-active-class="transition-transform duration-200"
        leave-to-class="-translate-y-full"
    >
        <div
            v-if="!enLinea"
            role="status"
            aria-live="polite"
            class="fixed inset-x-0 top-0 z-50 flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 pt-safe text-sm font-medium text-amber-950"
        >
            <WifiOff class="size-4 shrink-0" aria-hidden="true" />
            <span>Sin conexión. Los cambios no se van a guardar.</span>
        </div>
    </Transition>
</template>
