<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FormularioVisita from '@/components/FormularioVisita.vue';
import { index as mascotasIndex } from '@/routes/mascotas';
import { update } from '@/routes/mascotas/visitas';
import type {
    Mascota,
    Medicamento,
    OpcionEnum,
    Veterinaria,
    Veterinario,
    Visita,
} from '@/types/huella';

defineProps<{
    mascota: Mascota;
    visita: Visita;
    veterinarias: Veterinaria[];
    veterinarios: Veterinario[];
    medicamentos: Medicamento[];
    tiposVisita: OpcionEnum[];
    vias: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});
</script>

<template>
    <Head :title="`Editar visita de ${mascota.nombre}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Editar visita</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Los medicamentos y los archivos se manejan desde la ficha de la
                visita.
            </p>
        </div>

        <FormularioVisita
            :action="update([mascota.id, visita.id]).url"
            :visita="visita"
            :ahora="visita.fecha_hora_local ?? ''"
            :veterinarias="veterinarias"
            :veterinarios="veterinarios"
            :medicamentos="medicamentos"
            :tipos-visita="tiposVisita"
            :vias="vias"
            :con-tratamientos="false"
            texto-enviar="Guardar los cambios"
        />
    </div>
</template>
