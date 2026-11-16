<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FormularioVisita from '@/components/FormularioVisita.vue';
import { index as mascotasIndex, show } from '@/routes/mascotas';
import { store } from '@/routes/mascotas/visitas';
import type {
    Mascota,
    Medicamento,
    OpcionEnum,
    Veterinaria,
    Veterinario,
} from '@/types/huella';

defineProps<{
    mascota: Mascota;
    ahora: string;
    veterinarias: Veterinaria[];
    veterinarios: Veterinario[];
    medicamentos: Medicamento[];
    tiposVisita: OpcionEnum[];
    vias: OpcionEnum[];
    tiposAdjunto: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});
</script>

<template>
    <Head :title="`Nueva visita de ${mascota.nombre}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Nueva visita</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ mascota.nombre }} ·
                <!--
                    Link y no <a>: una carga completa se saltearía la pregunta
                    antes de salir del formulario a medio cargar.
                -->
                <Link
                    :href="show(mascota.id)"
                    class="text-primary hover:underline"
                >
                    ver la ficha
                </Link>
            </p>
        </div>

        <FormularioVisita
            :action="store(mascota.id).url"
            :ahora="ahora"
            :veterinarias="veterinarias"
            :veterinarios="veterinarios"
            :medicamentos="medicamentos"
            :tipos-visita="tiposVisita"
            :vias="vias"
            :tipos-adjunto="tiposAdjunto"
            texto-enviar="Guardar la visita"
            :clave-borrador="`visita:${mascota.id}:nueva`"
            :salida="show(mascota.id).url"
        />
    </div>
</template>
