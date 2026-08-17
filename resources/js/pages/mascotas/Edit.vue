<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FormularioMascota from '@/components/FormularioMascota.vue';
import { index, update } from '@/routes/mascotas';
import type { Mascota, OpcionEnum } from '@/types/huella';

const props = defineProps<{
    mascota: Mascota;
    especies: OpcionEnum[];
    sexos: OpcionEnum[];
    tiposPelaje: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mascotas', href: index() },
            { title: 'Editar ficha', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar a ${props.mascota.nombre}`" />

    <div class="mx-auto w-full max-w-xl p-4">
        <h1 class="mb-6 text-xl font-semibold">
            Editar la ficha de {{ props.mascota.nombre }}
        </h1>

        <FormularioMascota
            :action="update(props.mascota.id).url"
            :mascota="props.mascota"
            :especies="especies"
            :sexos="sexos"
            :tipos-pelaje="tiposPelaje"
            texto-enviar="Guardar los cambios"
        />
    </div>
</template>
