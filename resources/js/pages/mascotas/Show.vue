<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FichaMascota from '@/components/FichaMascota.vue';
import { index } from '@/routes/mascotas';
import type { DatosFichaMascota } from '@/types/huella';

/*
 * La pantalla de una mascota. El contenido vive en `FichaMascota`, que también
 * usa el inicio: acá queda solo lo que es de la página.
 */

const props = defineProps<DatosFichaMascota>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: index() }],
    },
});
</script>

<template>
    <Head :title="mascota.nombre" />

    <div class="mx-auto w-full max-w-3xl p-4">
        <FichaMascota v-bind="props" />
    </div>
</template>
