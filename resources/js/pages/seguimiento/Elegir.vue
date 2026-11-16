<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity } from '@lucide/vue';
import ElegirMascota from '@/components/ElegirMascota.vue';
import type { MascotaParaElegir } from '@/components/ElegirMascota.vue';
import { elegir } from '@/routes/seguimiento';

/*
 * ¿De quién es el seguimiento?
 *
 * Solo aparece al entrar por el menú, que no tiene contexto de mascota. Desde la
 * ficha no se pasa por acá, y con una sola mascota el controlador redirige sin
 * mostrar nada: preguntar cuál cuando hay una es un click de más en cada uso.
 */

defineProps<{
    mascotas: MascotaParaElegir[];
    /** La que se estaba mirando, para señalarla sin obligar a nada. */
    mascotaActivaId: number | null;
}>();

defineOptions({
    layout: {
        // Solo «Seguimiento»: es un destino propio del menú, y con «Mascotas ›»
        // delante la miga empujaba al selector de mascota fuera de la pantalla
        // a 320 px con letra Grande.
        breadcrumbs: [{ title: 'Seguimiento', href: elegir() }],
    },
});
</script>

<template>
    <Head title="Seguimiento" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div class="flex items-center gap-3">
            <Activity class="size-6 text-primary" aria-hidden="true" />
            <div>
                <h1 class="text-xl font-semibold">
                    ¿De quién es el seguimiento?
                </h1>
                <p class="text-sm text-muted-foreground">
                    Peso, alimentación, vacunas, desparasitaciones, celo y
                    baños.
                </p>
            </div>
        </div>

        <ElegirMascota
            :mascotas="mascotas"
            :mascota-activa-id="mascotaActivaId"
        />
    </div>
</template>
