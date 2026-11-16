<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, PawPrint } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { Mascota } from '@/types/huella';

/*
 * La lista para elegir de qué mascota es algo, en las pantallas que se abren
 * desde el menú: el menú no tiene contexto de mascota y tiene que preguntar.
 *
 * La usan Visitas y Seguimiento. Cada una pone su línea de detalle; la forma
 * de la lista, la mascota que se estaba mirando y el «En memoria» son de acá,
 * para que las dos pregunten igual.
 */

export type MascotaParaElegir = Mascota & {
    url: string;
    /** Lo que se lee debajo del nombre, después de la especie. */
    detalle?: string | null;
};

defineProps<{
    mascotas: MascotaParaElegir[];
    /** La que se estaba mirando, para señalarla sin obligar a nada. */
    mascotaActivaId: number | null;
}>();
</script>

<template>
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
                                class="block text-sm wrap-anywhere text-muted-foreground"
                            >
                                {{ mascota.especie_etiqueta }}
                                <template v-if="mascota.detalle">
                                    · {{ mascota.detalle }}
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
</template>
