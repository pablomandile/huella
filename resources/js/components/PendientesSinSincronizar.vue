<script setup lang="ts">
import { CloudOff } from '@lucide/vue';
import type { Operacion } from '@/sinConexion/cola';

/*
 * Lo que se cargó sin conexión y todavía no llegó al servidor, arriba de la
 * lista a la que va a pertenecer. Borde punteado y "sin sincronizar" porque no
 * es un dato todavía: el servidor lo puede rechazar (un peso duplicado), y en
 * ese caso desaparece de acá y pasa a la franja de cambios sin guardar.
 */

defineProps<{
    ops: Operacion[];
    /** El texto de cada fila: "18,4 kg · 14/10". */
    describir: (op: Operacion) => string;
}>();
</script>

<template>
    <ul
        v-if="ops.length"
        class="flex flex-col gap-1 rounded-lg border border-dashed border-amber-500/60 bg-amber-500/5 p-2"
        aria-label="Cargado sin conexión, sin sincronizar"
    >
        <li
            v-for="op in ops"
            :key="op.op_id"
            class="flex items-center gap-2 px-1 py-1 text-sm"
        >
            <CloudOff
                class="size-4 shrink-0 text-amber-700 dark:text-amber-400"
                aria-hidden="true"
            />
            <span class="min-w-0 flex-1 wrap-anywhere">{{
                describir(op)
            }}</span>
            <span class="shrink-0 text-xs text-muted-foreground">
                sin sincronizar
            </span>
        </li>
    </ul>
</template>
