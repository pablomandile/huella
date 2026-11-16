<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Stethoscope } from '@lucide/vue';
import { computed } from 'vue';
import ElegirMascota from '@/components/ElegirMascota.vue';
import { index as mascotasIndex } from '@/routes/mascotas';
import { elegir } from '@/routes/visitas';
import type { Mascota } from '@/types/huella';

/*
 * ¿De quién es la visita?
 *
 * Solo aparece al entrar por el menú, que no tiene contexto de mascota. Desde la
 * ficha no se pasa por acá, y con una sola mascota el controlador redirige sin
 * mostrar nada: preguntar cuál cuando hay una es un click de más en cada uso.
 */

type MascotaConVisitas = Mascota & {
    visitas_count: number;
    ultima_visita: string | null;
    url: string;
};

const props = defineProps<{
    mascotas: MascotaConVisitas[];
    /** La que se estaba mirando, para señalarla sin obligar a nada. */
    mascotaActivaId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mascotas', href: mascotasIndex() },
            { title: 'Visitas', href: elegir() },
        ],
    },
});

/** "3 visitas · la última el 12/11/2026": ubica sin tener que entrar. */
const conDetalle = computed(() =>
    props.mascotas.map((mascota) => ({
        ...mascota,
        detalle: [
            mascota.visitas_count
                ? `${mascota.visitas_count} ${mascota.visitas_count === 1 ? 'visita' : 'visitas'}`
                : 'sin visitas cargadas',
            mascota.ultima_visita
                ? `la última el ${mascota.ultima_visita}`
                : null,
        ]
            .filter(Boolean)
            .join(' · '),
    })),
);
</script>

<template>
    <Head title="Visitas" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div class="flex items-center gap-3">
            <Stethoscope class="size-6 text-primary" aria-hidden="true" />
            <div>
                <h1 class="text-xl font-semibold">¿De quién es la visita?</h1>
                <p class="text-sm text-muted-foreground">
                    Elegí la mascota para ver sus visitas o anotar una nueva.
                </p>
            </div>
        </div>

        <ElegirMascota
            :mascotas="conDetalle"
            :mascota-activa-id="mascotaActivaId"
        />
    </div>
</template>
