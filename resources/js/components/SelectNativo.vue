<script setup lang="ts">
import type { OpcionEnum } from '@/types/huella';

/*
 * Select nativo estilizado. En el celular abre la rueda del sistema operativo,
 * que es mejor experiencia táctil que cualquier dropdown dibujado — y al ser
 * un elemento de formulario real, viaja solo dentro del <Form> de Inertia.
 */

defineProps<{
    name: string;
    opciones: OpcionEnum[];
    /** Texto de la opción vacía. Si no se pasa, el campo es obligatorio sin vacío. */
    placeholder?: string;
    defaultValue?: string | null;
}>();
</script>

<template>
    <select
        :name="name"
        :id="name"
        class="flex h-9 touch-target w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-input/30"
        :value="defaultValue ?? ''"
    >
        <option v-if="placeholder" value="">{{ placeholder }}</option>
        <option
            v-for="opcion in opciones"
            :key="opcion.value"
            :value="opcion.value"
        >
            {{ opcion.label }}
        </option>
    </select>
</template>
