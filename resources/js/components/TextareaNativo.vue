<script setup lang="ts">
/*
 * Textarea con el mismo estilo que <Input>. shadcn-vue no trae uno y ya se
 * repetía a mano en varios formularios.
 */

withDefaults(
    defineProps<{
        name: string;
        defaultValue?: string | null;
        rows?: number;
        maxlength?: number;
        placeholder?: string;
    }>(),
    {
        defaultValue: '',
        rows: 3,
        maxlength: 2000,
        placeholder: undefined,
    },
);
</script>

<template>
    <textarea
        :id="name"
        :name="name"
        :rows="rows"
        :maxlength="maxlength"
        :placeholder="placeholder"
        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-input/30"
        :value="defaultValue ?? ''"
    ></textarea>
</template>
