<script setup lang="ts">
import { ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/*
 * Checkbox que viaja dentro del <Form> de Inertia.
 *
 * El Checkbox de reka-ui es un botón, no un input, así que por sí solo no
 * entra en el FormData. El input oculto es el que realmente se envía; el
 * Checkbox solo lo maneja.
 */

const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        defaultValue?: boolean;
    }>(),
    { defaultValue: false },
);

const marcado = ref(props.defaultValue);
</script>

<template>
    <div>
        <input type="hidden" :name="name" :value="marcado ? 1 : 0" />
        <Label class="flex touch-target items-center gap-2 font-normal">
            <Checkbox
                :model-value="marcado"
                @update:model-value="marcado = $event === true"
            />
            {{ label }}
        </Label>
    </div>
</template>
