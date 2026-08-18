<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    Download,
    FileText,
    Pencil,
    Pill,
    Plus,
    Stethoscope,
    Thermometer,
    Trash2,
} from '@lucide/vue';
import { ref, shallowRef } from 'vue';
import { Form } from '@inertiajs/vue3';
import CamposTratamiento from '@/components/CamposTratamiento.vue';
import VisorImagen from '@/components/VisorImagen.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { destroy as destroyAdjunto } from '@/routes/adjuntos';
import { index as mascotasIndex } from '@/routes/mascotas';
import { store as storeTratamiento } from '@/routes/mascotas/tratamientos';
import { edit, index as visitasIndex } from '@/routes/mascotas/visitas';
import type {
    Adjunto,
    Mascota,
    Medicamento,
    OpcionEnum,
    Tratamiento,
    Visita,
} from '@/types/huella';

defineProps<{
    mascota: Mascota;
    visita: Visita;
    puedeEditar: boolean;
    medicamentos: Medicamento[];
    vias: OpcionEnum[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});

const sheetTratamiento = ref(false);

const visorAbierto = ref(false);
const enElVisor = shallowRef<Adjunto | null>(null);

function abrirAdjunto(adjunto: Adjunto) {
    enElVisor.value = adjunto;
    visorAbierto.value = true;
}

function eliminarAdjunto(adjunto: Adjunto) {
    if (!confirm(`¿Eliminar ${adjunto.nombre_original ?? 'este archivo'}?`)) {
        return;
    }

    // Si se borra lo abierto, el visor queda con un `src` que ya da 404.
    if (enElVisor.value?.id === adjunto.id) {
        visorAbierto.value = false;
    }

    router.delete(destroyAdjunto(adjunto.id).url, { preserveScroll: true });
}

/** "12 de 21 dadas" — la adherencia al tratamiento de un vistazo. */
function progreso(tratamiento: Tratamiento): string | null {
    const a = tratamiento.adherencia;

    if (!a || a.total === 0) {
        return null;
    }

    const partes = [`${a.dadas} de ${a.total} dadas`];

    if (a.salteadas > 0) {
        partes.push(`${a.salteadas} salteadas`);
    }

    return partes.join(' · ');
}
</script>

<template>
    <Head :title="visita.motivo ?? 'Visita'" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <!-- Cabecera -->
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" class="font-normal">
                        {{ visita.tipo_etiqueta }}
                    </Badge>
                    <span class="text-sm text-muted-foreground">
                        {{ visita.fecha_legible }}
                    </span>
                </div>
                <h1 class="mt-2 text-xl font-semibold">
                    {{ visita.motivo ?? 'Visita al veterinario' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ mascota.nombre }}
                </p>
            </div>

            <Button
                v-if="puedeEditar"
                variant="outline"
                size="sm"
                as-child
                class="touch-target shrink-0"
            >
                <Link :href="edit([mascota.id, visita.id])">
                    <Pencil class="size-4" aria-hidden="true" />
                    Editar
                </Link>
            </Button>
        </div>

        <!-- Dónde y con quién -->
        <div
            v-if="
                visita.veterinaria_nombre ||
                visita.veterinario_nombre ||
                visita.temperatura
            "
            class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground"
        >
            <span
                v-if="visita.veterinaria_nombre"
                class="inline-flex items-center gap-1.5"
            >
                <Building2 class="size-4" aria-hidden="true" />
                {{ visita.veterinaria_nombre }}
            </span>
            <span
                v-if="visita.veterinario_nombre"
                class="inline-flex items-center gap-1.5"
            >
                <Stethoscope class="size-4" aria-hidden="true" />
                {{ visita.veterinario_nombre }}
            </span>
            <span
                v-if="visita.temperatura"
                class="inline-flex items-center gap-1.5"
            >
                <Thermometer class="size-4" aria-hidden="true" />
                {{ visita.temperatura }} °C
            </span>
        </div>

        <!-- Diagnóstico e indicaciones -->
        <Card v-if="visita.diagnostico || visita.indicaciones" class="gap-3">
            <CardContent class="flex flex-col gap-4">
                <div v-if="visita.diagnostico">
                    <p
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Diagnóstico
                    </p>
                    <p class="mt-1 text-sm whitespace-pre-line">
                        {{ visita.diagnostico }}
                    </p>
                </div>
                <div v-if="visita.indicaciones">
                    <p
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Indicaciones
                    </p>
                    <p class="mt-1 text-sm whitespace-pre-line">
                        {{ visita.indicaciones }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Medicación -->
        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-medium">Medicación</h2>
                <Button
                    v-if="puedeEditar"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="sheetTratamiento = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Agregar
                </Button>
            </div>

            <p
                v-if="!visita.tratamientos.length"
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                De esta visita no salió medicación.
            </p>

            <Card
                v-for="tratamiento in visita.tratamientos"
                :key="tratamiento.id"
                class="py-0"
            >
                <CardContent class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                    >
                        <Pill
                            class="size-4 text-accent-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ tratamiento.nombre_medicamento }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ tratamiento.posologia }}
                        </p>
                        <p
                            v-if="tratamiento.notas"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ tratamiento.notas }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class="font-normal">
                                {{ tratamiento.estado_etiqueta }}
                            </Badge>
                            <span
                                v-if="progreso(tratamiento)"
                                class="text-xs text-muted-foreground"
                            >
                                {{ progreso(tratamiento) }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Adjuntos -->
        <section class="flex flex-col gap-3">
            <h2 class="font-medium">Recetas y estudios</h2>

            <p
                v-if="!visita.adjuntos.length"
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No hay archivos adjuntos.
            </p>

            <ul v-else class="grid gap-3 sm:grid-cols-2">
                <li
                    v-for="adjunto in visita.adjuntos"
                    :key="adjunto.id"
                    class="flex items-center gap-3 rounded-xl border border-border p-3"
                >
                    <!--
                        Una radiografía o un análisis se miran en grande: abren el
                        visor. Un PDF no se puede mostrar en un `img`, así que
                        sigue abriéndose aparte.
                    -->
                    <component
                        :is="adjunto.es_imagen ? 'button' : 'a'"
                        :type="adjunto.es_imagen ? 'button' : undefined"
                        :href="adjunto.es_imagen ? undefined : adjunto.url"
                        :target="adjunto.es_imagen ? undefined : '_blank'"
                        :rel="adjunto.es_imagen ? undefined : 'noopener'"
                        class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                        @click="adjunto.es_imagen && abrirAdjunto(adjunto)"
                    >
                        <img
                            v-if="adjunto.miniatura_url"
                            :src="adjunto.miniatura_url"
                            :alt="
                                adjunto.es_imagen
                                    ? `Ver ${adjunto.nombre_original ?? 'el adjunto'} en grande`
                                    : (adjunto.nombre_original ?? 'Adjunto')
                            "
                            class="size-full object-cover"
                            loading="lazy"
                        />
                        <FileText
                            v-else
                            class="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </component>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ adjunto.tipo_etiqueta }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ adjunto.nombre_original }}
                            <template v-if="adjunto.tamanio_legible">
                                · {{ adjunto.tamanio_legible }}</template
                            >
                        </p>
                    </div>

                    <a
                        :href="adjunto.descarga_url"
                        class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
                        :aria-label="`Descargar ${adjunto.nombre_original ?? 'el archivo'}`"
                    >
                        <Download class="size-4" aria-hidden="true" />
                    </a>
                    <button
                        v-if="puedeEditar"
                        type="button"
                        class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                        :aria-label="`Eliminar ${adjunto.nombre_original ?? 'el archivo'}`"
                        @click="eliminarAdjunto(adjunto)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </li>
            </ul>
        </section>

        <div
            v-if="visita.notas || visita.proximo_control"
            class="flex flex-col gap-2 text-sm"
        >
            <p v-if="visita.proximo_control" class="text-muted-foreground">
                Próximo control: {{ visita.proximo_control }}
            </p>
            <p
                v-if="visita.notas"
                class="whitespace-pre-line text-muted-foreground"
            >
                {{ visita.notas }}
            </p>
        </div>

        <Link
            :href="visitasIndex(mascota.id)"
            class="text-sm text-primary hover:underline"
        >
            Ver todas las visitas de {{ mascota.nombre }}
        </Link>

        <!-- Agregar un tratamiento a una visita ya cargada -->
        <Sheet v-model:open="sheetTratamiento">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Agregar medicación</SheetTitle>
                    <SheetDescription>
                        Las tomas se programan solas y aparecen en «Medicación
                        de hoy».
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storeTratamiento(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetTratamiento = false"
                >
                    <input type="hidden" name="visita_id" :value="visita.id" />

                    <CamposTratamiento
                        :medicamentos="medicamentos"
                        :vias="vias"
                        :errores="errors"
                        :hoy="visita.fecha_hora_local?.slice(0, 10) ?? ''"
                    />

                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetTratamiento = false"
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

    <!-- Uno solo para la pantalla, fuera del v-for de los adjuntos. -->
    <VisorImagen
        v-if="enElVisor"
        v-model:abierto="visorAbierto"
        :src="enElVisor.url"
        :alt="enElVisor.nombre_original ?? 'Adjunto de la visita'"
        :titulo="`${enElVisor.tipo_etiqueta}: ${enElVisor.nombre_original ?? 'imagen'}`"
        :descripcion="
            [enElVisor.tipo_etiqueta, enElVisor.descripcion]
                .filter(Boolean)
                .join(' · ')
        "
    >
        <template #acciones>
            <Button variant="secondary" size="sm" as-child class="touch-target">
                <a :href="enElVisor.descarga_url">
                    <Download class="size-4" aria-hidden="true" />
                    Descargar
                </a>
            </Button>
        </template>
    </VisorImagen>
</template>
