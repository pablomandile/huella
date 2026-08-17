<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    BellRing,
    CalendarClock,
    Check,
    Clock,
    Plus,
    Trash2,
    Undo2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { store as storeRecordatorio } from '@/routes/mascotas/recordatorios';
import { destroy, reabrir, resolver } from '@/routes/recordatorios';
import type { Recordatorio } from '@/types/huella';

/*
 * La bandeja: todo lo que hay que agendar, de todas las mascotas juntas.
 *
 * Tres salidas por recordatorio y las tres a un tap: hecho, posponer o
 * descartar. Lo resuelto queda un rato a la vista para poder deshacer, porque
 * el error más frecuente es marcar el de al lado.
 */

const props = defineProps<{
    abiertos: Recordatorio[];
    resueltos: Recordatorio[];
    mascotas: { id: number; nombre: string }[];
    diasAPosponer: number;
}>();

const sheetAlta = ref(false);
const hoy = new Date().toISOString().slice(0, 10);

// A qué mascota se le carga el recordatorio nuevo. El endpoint cuelga de la
// mascota, así que el select tiene que mover el action del formulario.
const mascotaElegida = ref<number | null>(props.mascotas[0]?.id ?? null);
const urlAlta = computed(() =>
    mascotaElegida.value ? storeRecordatorio(mascotaElegida.value).url : '',
);

// Vencidos primero: son los que realmente importan.
const vencidos = computed(() => props.abiertos.filter((r) => r.vencido));
const proximos = computed(() => props.abiertos.filter((r) => !r.vencido));

function accionar(
    recordatorio: Recordatorio,
    accion: 'completar' | 'posponer' | 'descartar',
) {
    router.patch(
        resolver(recordatorio.id).url,
        { accion },
        { preserveScroll: true, preserveState: true },
    );
}

function volverAAbrir(recordatorio: Recordatorio) {
    router.patch(reabrir(recordatorio.id).url, {}, { preserveScroll: true });
}

function eliminar(recordatorio: Recordatorio) {
    if (confirm(`¿Eliminar «${recordatorio.titulo}»?`)) {
        router.delete(destroy(recordatorio.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Recordatorios" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Recordatorios</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="abiertos.length">
                        {{ abiertos.length }}
                        {{ abiertos.length === 1 ? 'pendiente' : 'pendientes' }}
                    </template>
                    <template v-else>No te falta nada por agendar.</template>
                </p>
            </div>
            <Button
                v-if="mascotas.length"
                size="sm"
                class="touch-target shrink-0"
                @click="sheetAlta = true"
            >
                <Plus class="size-4" aria-hidden="true" />
                Agregar
            </Button>
        </div>

        <!-- Nada pendiente -->
        <div
            v-if="!abiertos.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-accent"
            >
                <BellRing
                    class="size-7 text-accent-foreground"
                    aria-hidden="true"
                />
            </div>
            <div>
                <h2 class="font-medium">Todo al día</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Cuando cargues una vacuna con refuerzo, una desparasitación
                    o un próximo control, el recordatorio aparece acá solo.
                </p>
            </div>
        </div>

        <!-- Vencidos -->
        <section v-if="vencidos.length" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-destructive">
                Vencidos ({{ vencidos.length }})
            </h2>

            <Card
                v-for="recordatorio in vencidos"
                :key="recordatorio.id"
                class="border-destructive/40 py-0"
            >
                <CardContent class="flex flex-col gap-3 p-4">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ recordatorio.titulo }}</p>
                            <p class="text-sm text-destructive">
                                {{ recordatorio.fecha_legible }} ·
                                {{ recordatorio.cuando }}
                            </p>
                            <p
                                v-if="recordatorio.descripcion"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ recordatorio.descripcion }}
                            </p>
                        </div>
                        <Badge variant="outline" class="shrink-0 font-normal">
                            {{ recordatorio.tipo_etiqueta }}
                        </Badge>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            class="touch-target"
                            @click="accionar(recordatorio, 'completar')"
                        >
                            <Check class="size-4" aria-hidden="true" />
                            Ya está
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="touch-target"
                            @click="accionar(recordatorio, 'posponer')"
                        >
                            <Clock class="size-4" aria-hidden="true" />
                            {{ diasAPosponer }} días
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="touch-target"
                            @click="accionar(recordatorio, 'descartar')"
                        >
                            <X class="size-4" aria-hidden="true" />
                            Descartar
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Lo que viene -->
        <section v-if="proximos.length" class="flex flex-col gap-2">
            <h2 v-if="vencidos.length" class="text-sm font-medium">
                Lo que viene
            </h2>

            <Card
                v-for="recordatorio in proximos"
                :key="recordatorio.id"
                class="py-0"
            >
                <CardContent class="flex flex-col gap-3 p-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                        >
                            <CalendarClock
                                class="size-4 text-accent-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ recordatorio.titulo }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ recordatorio.fecha_legible }} ·
                                {{ recordatorio.cuando }}
                            </p>
                            <p
                                v-if="recordatorio.descripcion"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ recordatorio.descripcion }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <Badge variant="outline" class="font-normal">
                                {{ recordatorio.tipo_etiqueta }}
                            </Badge>
                            <Badge
                                v-if="recordatorio.estado === 'notificado'"
                                variant="secondary"
                                class="font-normal"
                            >
                                Avisado
                            </Badge>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="touch-target"
                            @click="accionar(recordatorio, 'completar')"
                        >
                            <Check class="size-4" aria-hidden="true" />
                            Ya está
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="touch-target"
                            @click="accionar(recordatorio, 'posponer')"
                        >
                            <Clock class="size-4" aria-hidden="true" />
                            {{ diasAPosponer }} días
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="touch-target"
                            @click="accionar(recordatorio, 'descartar')"
                        >
                            <X class="size-4" aria-hidden="true" />
                            Descartar
                        </Button>
                        <!-- Los automáticos no se borran: volverían a nacer de
                             su origen. Para esos está descartar. -->
                        <Button
                            v-if="!recordatorio.es_automatico"
                            size="sm"
                            variant="ghost"
                            class="ml-auto touch-target"
                            :aria-label="`Eliminar ${recordatorio.titulo}`"
                            @click="eliminar(recordatorio)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Resueltos hace poco, para poder deshacer -->
        <section v-if="resueltos.length" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                Resueltos hace poco
            </h2>

            <Card
                v-for="recordatorio in resueltos"
                :key="recordatorio.id"
                class="bg-muted/40 py-0"
            >
                <CardContent class="flex items-center gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-muted-foreground">
                            {{ recordatorio.titulo }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ recordatorio.estado_etiqueta }}
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="touch-target shrink-0"
                        :aria-label="`Volver a abrir ${recordatorio.titulo}`"
                        @click="volverAAbrir(recordatorio)"
                    >
                        <Undo2 class="size-4" aria-hidden="true" />
                    </Button>
                </CardContent>
            </Card>
        </section>

        <!-- Alta de un recordatorio propio -->
        <Sheet v-model:open="sheetAlta">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Agregar un recordatorio</SheetTitle>
                    <SheetDescription>
                        Para lo que no sale de ninguna vacuna ni consulta:
                        cortarle las uñas, renovar la libreta.
                    </SheetDescription>
                </SheetHeader>

                <!-- Fuera del <Form>: elige el destino, no viaja como dato. -->
                <div v-if="mascotas.length > 1" class="grid gap-2 px-4 pt-4">
                    <Label for="mascota-destino">Para</Label>
                    <select
                        id="mascota-destino"
                        v-model="mascotaElegida"
                        class="flex h-9 touch-target w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-input/30"
                    >
                        <option
                            v-for="mascota in mascotas"
                            :key="mascota.id"
                            :value="mascota.id"
                        >
                            {{ mascota.nombre }}
                        </option>
                    </select>
                </div>

                <Form
                    v-if="urlAlta"
                    :key="urlAlta"
                    :action="urlAlta"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetAlta = false"
                >
                    <div class="grid gap-2">
                        <Label for="titulo">Qué hay que hacer *</Label>
                        <Input
                            id="titulo"
                            name="titulo"
                            required
                            maxlength="160"
                            autocomplete="off"
                            placeholder="Cortarle las uñas"
                        />
                        <InputError :message="errors.titulo" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fecha_objetivo">Cuándo *</Label>
                            <Input
                                id="fecha_objetivo"
                                name="fecha_objetivo"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="hoy"
                            />
                            <InputError :message="errors.fecha_objetivo" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="dias_anticipacion">Avisar con</Label>
                            <Input
                                id="dias_anticipacion"
                                name="dias_anticipacion"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="365"
                                default-value="3"
                            />
                            <p class="text-xs text-muted-foreground">
                                días de anticipación
                            </p>
                            <InputError :message="errors.dias_anticipacion" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="descripcion">Detalle</Label>
                        <TextareaNativo name="descripcion" :rows="2" />
                        <InputError :message="errors.descripcion" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetAlta = false"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            class="touch-target flex-1"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="size-4" />
                            Guardar
                        </Button>
                    </div>
                </Form>
            </SheetContent>
        </Sheet>
    </div>
</template>
