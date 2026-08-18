<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, PawPrint, Stethoscope } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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

defineProps<{
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

        <ul class="flex flex-col gap-2">
            <li v-for="mascota in mascotas" :key="mascota.id">
                <Card class="py-0">
                    <CardContent class="p-0">
                        <Link
                            :href="mascota.url"
                            class="flex touch-target items-center gap-3 p-3"
                        >
                            <span
                                class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted"
                            >
                                <img
                                    v-if="mascota.foto_miniatura_url"
                                    :src="mascota.foto_miniatura_url"
                                    :alt="`Foto de ${mascota.nombre}`"
                                    width="48"
                                    height="48"
                                    loading="lazy"
                                    class="size-full object-cover"
                                />
                                <PawPrint
                                    v-else
                                    class="size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex flex-wrap items-center gap-2 font-medium"
                                >
                                    {{ mascota.nombre }}
                                    <Badge
                                        v-if="mascota.id === mascotaActivaId"
                                        variant="secondary"
                                        class="font-normal"
                                    >
                                        La que estabas viendo
                                    </Badge>
                                    <Badge
                                        v-if="mascota.fallecida"
                                        variant="outline"
                                        class="font-normal"
                                    >
                                        En memoria
                                    </Badge>
                                </span>
                                <span
                                    class="block truncate text-sm text-muted-foreground"
                                >
                                    {{ mascota.especie_etiqueta }}
                                    <template v-if="mascota.visitas_count">
                                        ·
                                        {{ mascota.visitas_count }}
                                        {{
                                            mascota.visitas_count === 1
                                                ? 'visita'
                                                : 'visitas'
                                        }}
                                    </template>
                                    <template v-else>
                                        · sin visitas cargadas
                                    </template>
                                    <template v-if="mascota.ultima_visita">
                                        · la última el
                                        {{ mascota.ultima_visita }}
                                    </template>
                                </span>
                            </span>

                            <ChevronRight
                                class="size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </Link>
                    </CardContent>
                </Card>
            </li>
        </ul>
    </div>
</template>
