<script setup lang="ts">
import { Download } from '@lucide/vue';
import { ref } from 'vue';
import InstalarAppInstructivo from '@/components/InstalarAppInstructivo.vue';
import { Button } from '@/components/ui/button';
import { usePwaInstall } from '@/composables/usePwaInstall';

withDefaults(
    defineProps<{
        /** 'menu' se integra al menú de usuario; 'boton' es autónomo. */
        variante?: 'menu' | 'boton';
    }>(),
    { variante: 'boton' },
);

const { sePuedeInstalar, esIos, instalar } = usePwaInstall();
const mostrarInstructivo = ref(false);
const ocupado = ref(false);

async function alTocar() {
    if (ocupado.value) {
        return;
    }

    ocupado.value = true;

    try {
        // 'manual' = iOS, o el prompt nativo ya se consumió: explicamos los pasos.
        if ((await instalar()) === 'manual') {
            mostrarInstructivo.value = true;
        }
    } finally {
        ocupado.value = false;
    }
}
</script>

<template>
    <Button
        v-if="sePuedeInstalar"
        type="button"
        :variant="variante === 'menu' ? 'ghost' : 'outline'"
        :class="
            variante === 'menu' ? 'w-full justify-start font-normal' : undefined
        "
        :disabled="ocupado"
        @click="alTocar"
    >
        <Download class="size-4" aria-hidden="true" />
        Instalar app
    </Button>

    <InstalarAppInstructivo
        v-model:abierto="mostrarInstructivo"
        :es-ios="esIos"
    />
</template>
