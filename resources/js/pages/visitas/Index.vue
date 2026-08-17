<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Pill, Plus, Stethoscope } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as mascotasIndex } from '@/routes/mascotas';
import { create, show } from '@/routes/mascotas/visitas';
import type { Mascota, Visita } from '@/types/huella';

defineProps<{
    mascota: Mascota;
    visitas: Visita[];
    puedeRegistrar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});
</script>

<template>
    <Head :title="`Visitas de ${mascota.nombre}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold">Visitas</h1>
                <p class="text-sm text-muted-foreground">
                    {{ mascota.nombre }} · {{ visitas.length }}
                    {{ visitas.length === 1 ? 'registrada' : 'registradas' }}
                </p>
            </div>
            <Button
                v-if="puedeRegistrar && visitas.length"
                size="sm"
                as-child
                class="touch-target shrink-0"
            >
                <Link :href="create(mascota.id)">
                    <Plus class="size-4" aria-hidden="true" />
                    Nueva
                </Link>
            </Button>
        </div>

        <div
            v-if="!visitas.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-accent"
            >
                <Stethoscope
                    class="size-7 text-accent-foreground"
                    aria-hidden="true"
                />
            </div>
            <div>
                <h2 class="font-medium">Todavía no cargaste ninguna visita</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Cada consulta, con lo que le recetaron y la receta
                    fotografiada, en un solo lugar.
                </p>
            </div>
            <Button v-if="puedeRegistrar" as-child class="touch-target">
                <Link :href="create(mascota.id)">
                    <Plus class="size-4" aria-hidden="true" />
                    Cargar la primera visita
                </Link>
            </Button>
        </div>

        <div v-else class="flex flex-col gap-2">
            <Link
                v-for="visita in visitas"
                :key="visita.id"
                :href="show([mascota.id, visita.id])"
                class="group rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Card class="py-0 transition-shadow group-hover:shadow-md">
                    <CardContent class="flex flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary" class="font-normal">
                                {{ visita.tipo_etiqueta }}
                            </Badge>
                            <span class="text-xs text-muted-foreground">
                                {{ visita.fecha_legible }}
                            </span>
                        </div>

                        <p class="font-medium">
                            {{ visita.motivo ?? 'Visita al veterinario' }}
                        </p>

                        <p
                            v-if="visita.diagnostico"
                            class="line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ visita.diagnostico }}
                        </p>

                        <div
                            v-if="
                                visita.tratamientos.length ||
                                visita.adjuntos.length
                            "
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="visita.tratamientos.length"
                                class="inline-flex items-center gap-1"
                            >
                                <Pill class="size-3.5" aria-hidden="true" />
                                {{ visita.tratamientos.length }}
                                {{
                                    visita.tratamientos.length === 1
                                        ? 'medicamento'
                                        : 'medicamentos'
                                }}
                            </span>
                            <span
                                v-if="visita.adjuntos.length"
                                class="inline-flex items-center gap-1"
                            >
                                <FileText class="size-3.5" aria-hidden="true" />
                                {{ visita.adjuntos.length }}
                                {{
                                    visita.adjuntos.length === 1
                                        ? 'archivo'
                                        : 'archivos'
                                }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
