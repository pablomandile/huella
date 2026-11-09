<script setup lang="ts">
import { Info } from '@lucide/vue';

/**
 * Reemplaza los formularios de la cuenta en la demo (APP_DEMO=true): la cuenta
 * es compartida por todos los que la prueban, y el backend (ProtegerDemo) ya
 * rechaza esos cambios. Mostrar el formulario para después decir que no sería
 * hacerle perder el tiempo a quien está mirando.
 */
defineProps<{
    texto: string;
}>();
</script>

<template>
    <div
        class="flex gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm"
        data-test="aviso-demo"
    >
        <Info class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
        <p class="wrap-anywhere text-muted-foreground">{{ texto }}</p>
    </div>
</template>
