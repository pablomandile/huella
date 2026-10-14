<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import SelectorTamanoLetra from '@/components/SelectorTamanoLetra.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apariencia" />

    <h1 class="sr-only">Configuración de apariencia</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Apariencia"
            description="Elegí cómo se ve la app en este dispositivo"
        />
        <AppearanceTabs />

        <div class="space-y-3">
            <Heading
                variant="small"
                title="Tamaño de letra"
                description="Más grande se lee mejor con el celular en la mano; la pantalla crece hacia abajo, nunca hacia los costados"
            />
            <SelectorTamanoLetra />
        </div>
    </div>
</template>
