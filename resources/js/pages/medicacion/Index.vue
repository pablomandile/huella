<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, PawPrint, PillBottle, Undo2, X } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { update } from '@/routes/medicacion';
import type { TomaHoy } from '@/types/huella';

/*
 * Lo que hay que dar ahora, de todas las mascotas juntas: a la mañana se
 * reparten los remedios de todos los que viven en la casa, y tener que cambiar
 * de mascota entre uno y otro es la forma más segura de saltearse una dosis.
 *
 * Marcar es un tap y sin confirmación: si se marcó mal, se toca de nuevo. Dos
 * taps por cada dosis, tres veces al día, no los hace nadie.
 */

const props = defineProps<{
    tomas: TomaHoy[];
    hoy: string;
}>();

const atrasadas = computed(() => props.tomas.filter((t) => t.atrasada));
const deHoy = computed(() => props.tomas.filter((t) => !t.atrasada));
const pendientesDeHoy = computed(
    () => deHoy.value.filter((t) => t.estado === 'pendiente').length,
);

function marcar(
    toma: TomaHoy,
    estado: 'administrada' | 'omitida' | 'pendiente',
) {
    router.patch(
        update(toma.id).url,
        { estado },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Medicación de hoy" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">Medicación</h1>
            <p
                class="mt-1 text-sm text-muted-foreground first-letter:uppercase"
            >
                {{ hoy }}
                <template v-if="pendientesDeHoy > 0">
                    · quedan {{ pendientesDeHoy }} por dar</template
                >
            </p>
        </div>

        <!-- Nada que dar -->
        <div
            v-if="!tomas.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-accent"
            >
                <PillBottle
                    class="size-7 text-accent-foreground"
                    aria-hidden="true"
                />
            </div>
            <div>
                <h2 class="font-medium">Hoy no hay nada que dar</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Cuando cargues un tratamiento con horarios, las tomas
                    aparecen acá solas.
                </p>
            </div>
        </div>

        <template v-else>
            <!-- Lo que quedó sin marcar de días anteriores -->
            <section v-if="atrasadas.length" class="flex flex-col gap-2">
                <h2 class="text-sm font-medium text-destructive">
                    Atrasadas ({{ atrasadas.length }})
                </h2>

                <Card
                    v-for="toma in atrasadas"
                    :key="toma.id"
                    class="border-destructive/40 py-0"
                >
                    <CardContent class="flex items-center gap-3 p-3">
                        <div class="w-14 shrink-0 text-center">
                            <p class="text-xs text-muted-foreground">
                                {{ toma.fecha_legible }}
                            </p>
                            <p class="text-sm font-semibold">{{ toma.hora }}</p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ toma.medicamento }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ toma.dosis }} · {{ toma.mascota_nombre }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-1">
                            <Button
                                size="icon"
                                variant="outline"
                                class="touch-target"
                                :aria-label="`Marcar ${toma.medicamento} de ${toma.mascota_nombre} como dada`"
                                @click="marcar(toma, 'administrada')"
                            >
                                <Check class="size-4" aria-hidden="true" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="touch-target"
                                :aria-label="`Marcar ${toma.medicamento} de ${toma.mascota_nombre} como salteada`"
                                @click="marcar(toma, 'omitida')"
                            >
                                <X class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <!-- Hoy -->
            <section v-if="deHoy.length" class="flex flex-col gap-2">
                <h2 v-if="atrasadas.length" class="text-sm font-medium">Hoy</h2>

                <Card
                    v-for="toma in deHoy"
                    :key="toma.id"
                    class="py-0"
                    :class="
                        toma.estado !== 'pendiente'
                            ? 'bg-muted/40 text-muted-foreground'
                            : ''
                    "
                >
                    <CardContent class="flex items-center gap-3 p-3">
                        <p
                            class="w-12 shrink-0 text-center text-sm font-semibold"
                        >
                            {{ toma.hora }}
                        </p>

                        <div
                            class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted"
                        >
                            <img
                                v-if="toma.mascota_foto_url"
                                :src="toma.mascota_foto_url"
                                :alt="toma.mascota_nombre"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <PawPrint
                                v-else
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium"
                                :class="
                                    toma.estado === 'administrada'
                                        ? 'line-through'
                                        : ''
                                "
                            >
                                {{ toma.medicamento }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ toma.dosis }} · {{ toma.mascota_nombre }}
                            </p>
                            <p
                                v-if="toma.notas_tratamiento"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ toma.notas_tratamiento }}
                            </p>
                        </div>

                        <!-- Ya marcada: se puede volver atrás de un tap -->
                        <div
                            v-if="toma.estado !== 'pendiente'"
                            class="flex shrink-0 items-center gap-1"
                        >
                            <Badge variant="secondary" class="font-normal">
                                {{ toma.estado_etiqueta }}
                            </Badge>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="touch-target"
                                :aria-label="`Desmarcar ${toma.medicamento} de ${toma.mascota_nombre}`"
                                @click="marcar(toma, 'pendiente')"
                            >
                                <Undo2 class="size-4" aria-hidden="true" />
                            </Button>
                        </div>

                        <div v-else class="flex shrink-0 gap-1">
                            <Button
                                size="icon"
                                class="touch-target"
                                :aria-label="`Marcar ${toma.medicamento} de ${toma.mascota_nombre} como dada`"
                                @click="marcar(toma, 'administrada')"
                            >
                                <Check class="size-4" aria-hidden="true" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="touch-target"
                                :aria-label="`Marcar ${toma.medicamento} de ${toma.mascota_nombre} como salteada`"
                                @click="marcar(toma, 'omitida')"
                            >
                                <X class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </template>
    </div>
</template>
