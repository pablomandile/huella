<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FormularioMascota from '@/components/FormularioMascota.vue';
import { create, index, store } from '@/routes/mascotas';
import type { OpcionEnum } from '@/types/huella';

defineProps<{
    especies: OpcionEnum[];
    sexos: OpcionEnum[];
    tiposPelaje: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mascotas', href: index() },
            { title: 'Nueva', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Nueva mascota" />

    <div class="mx-auto w-full max-w-xl p-4">
        <h1 class="mb-6 text-xl font-semibold">Nueva mascota</h1>

        <FormularioMascota
            :action="store().url"
            :especies="especies"
            :sexos="sexos"
            :tipos-pelaje="tiposPelaje"
            texto-enviar="Crear la ficha"
        />
    </div>
</template>
