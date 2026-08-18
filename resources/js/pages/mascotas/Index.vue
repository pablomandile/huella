<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PawPrint, Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, index, show } from '@/routes/mascotas';
import type { Mascota } from '@/types/huella';

defineProps<{
    mascotas: Mascota[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: index() }],
    },
});
</script>

<template>
    <Head title="Mascotas" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Tus mascotas</h1>
            <Button v-if="mascotas.length" as-child size="sm">
                <Link :href="create()">
                    <Plus class="size-4" aria-hidden="true" />
                    Agregar
                </Link>
            </Button>
        </div>

        <!-- Estado vacío con llamado a la acción -->
        <div
            v-if="!mascotas.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-accent"
            >
                <PawPrint
                    class="size-7 text-accent-foreground"
                    aria-hidden="true"
                />
            </div>
            <div>
                <h2 class="font-medium">Todavía no cargaste ninguna mascota</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Creá su ficha y empezá a llevar su historial en un solo
                    lugar.
                </p>
            </div>
            <Button as-child class="touch-target">
                <Link :href="create()">
                    <Plus class="size-4" aria-hidden="true" />
                    Crear la primera ficha
                </Link>
            </Button>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="mascota in mascotas"
                :key="mascota.id"
                :href="show(mascota.id)"
                class="group focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Card
                    class="overflow-hidden py-0 transition-shadow group-hover:shadow-md"
                >
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted"
                        >
                            <img
                                v-if="mascota.foto_miniatura_url"
                                :src="mascota.foto_miniatura_url"
                                :alt="`Foto de ${mascota.nombre}`"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <PawPrint
                                v-else
                                class="size-7 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ mascota.nombre }}
                                <span
                                    v-if="mascota.fallecida"
                                    class="ml-1 align-middle text-xs text-muted-foreground"
                                    >✦</span
                                >
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ mascota.especie_etiqueta }}
                                <template v-if="mascota.raza">
                                    · {{ mascota.raza }}</template
                                >
                            </p>
                            <!-- Una ficha compartida se ve igual que una propia:
                                 sin esto no hay forma de saber cuál es cuál. -->
                            <Badge
                                v-if="!mascota.es_propia"
                                variant="secondary"
                                class="mt-1"
                                >{{ mascota.rol_etiqueta }}</Badge
                            >
                            <p
                                v-if="mascota.edad"
                                class="text-sm text-muted-foreground"
                            >
                                {{ mascota.edad }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
