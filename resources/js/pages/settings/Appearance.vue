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

    <div class="space-y-8">
        <!--
            La letra va primero: es a lo que viene la mayoría de quien abre
            esta pantalla. Viaja con la cuenta; el tema queda en el dispositivo.
        -->
        <div class="space-y-3">
            <Heading
                variant="small"
                title="Tamaño de letra"
                description="Se guarda en tu cuenta: lo vas a ver igual en cualquier dispositivo"
            />
            <SelectorTamanoLetra />
        </div>

        <div class="space-y-3">
            <Heading
                variant="small"
                title="Tema"
                description="Claro u oscuro, en este dispositivo"
            />
            <AppearanceTabs />
        </div>
    </div>
</template>
