<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bug,
    Droplets,
    FileText,
    NotebookPen,
    Pill,
    Scale,
    Stethoscope,
    Syringe,
    UtensilsCrossed,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { EventoTimeline } from '@/types/huella';

/*
 * Una fila de la línea de tiempo.
 *
 * El ícono y el color salen del tipo, acá y no en el servidor: son decisiones de
 * presentación. Cada tipo tiene su color para que la línea se lea de un barrido
 * —lo clínico en un tono, lo cotidiano en otro— sin tener que leer las etiquetas.
 */

const props = defineProps<{
    evento: EventoTimeline;
    /** Se oculta en el último para no dejar la línea colgando. */
    ultimo?: boolean;
}>();

const presentacion: Record<
    string,
    { icono: unknown; color: string; etiqueta: string }
> = {
    visita: {
        icono: Stethoscope,
        color: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        etiqueta: 'Visita',
    },
    vacuna: {
        icono: Syringe,
        color: 'bg-violet-500/15 text-violet-700 dark:text-violet-300',
        etiqueta: 'Vacuna',
    },
    desparasitacion: {
        icono: Bug,
        color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        etiqueta: 'Desparasitación',
    },
    tratamiento: {
        icono: Pill,
        color: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
        etiqueta: 'Medicación',
    },
    peso: {
        icono: Scale,
        color: 'bg-primary/15 text-primary',
        etiqueta: 'Peso',
    },
    dieta: {
        icono: UtensilsCrossed,
        color: 'bg-lime-500/15 text-lime-700 dark:text-lime-300',
        etiqueta: 'Alimentación',
    },
    celo: {
        icono: Droplets,
        color: 'bg-pink-500/15 text-pink-700 dark:text-pink-300',
        etiqueta: 'Celo',
    },
    entrada: {
        icono: NotebookPen,
        color: 'bg-muted text-muted-foreground',
        etiqueta: 'Nota',
    },
};

const estilo = computed(
    () =>
        presentacion[props.evento.tipo] ?? {
            icono: FileText,
            color: 'bg-muted text-muted-foreground',
            etiqueta: props.evento.tipo,
        },
);
</script>

<template>
    <li :data-clave="evento.clave" class="flex gap-3">
        <!-- El riel de la línea de tiempo -->
        <div class="flex flex-col items-center">
            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                :class="estilo.color"
            >
                <component
                    :is="estilo.icono"
                    class="size-4"
                    aria-hidden="true"
                />
            </div>
            <div
                v-if="!ultimo"
                class="mt-1 w-px flex-1 bg-border"
                aria-hidden="true"
            />
        </div>

        <div class="min-w-0 flex-1 pb-5">
            <div class="flex flex-wrap items-baseline gap-x-2">
                <p class="text-xs text-muted-foreground">
                    {{ evento.fecha_legible }}
                </p>
                <Badge variant="outline" class="text-[10px] font-normal">
                    {{ evento.etiqueta_tipo ?? estilo.etiqueta }}
                </Badge>
                <Badge
                    v-if="evento.vigente"
                    variant="secondary"
                    class="text-[10px] font-normal"
                >
                    Come esto ahora
                </Badge>
            </div>

            <!--
                El título es el punto de entrada al evento y en el celular medía
                24px de alto. `py-2.5 -my-2.5` lo lleva a 44 sin correr nada:
                el padding agranda el área táctil y el margen negativo lo
                devuelve a su lugar. Solo cuando es enlace, para no dejar áreas
                tocables que no llevan a ninguna parte.
            -->
            <component
                :is="evento.url ? Link : 'div'"
                v-bind="evento.url ? { href: evento.url } : {}"
                class="mt-0.5 block"
                :class="evento.url ? '-my-2.5 py-2.5 hover:underline' : ''"
            >
                <p class="font-medium text-pretty">{{ evento.titulo }}</p>
            </component>

            <p
                v-if="evento.detalle"
                class="mt-0.5 line-clamp-3 text-sm text-pretty text-muted-foreground"
            >
                {{ evento.detalle }}
            </p>

            <!-- Lo propio de cada tipo -->
            <div
                v-if="
                    evento.veterinaria ||
                    evento.medicamentos ||
                    evento.adjuntos ||
                    evento.proxima_dosis ||
                    evento.categoria_etiqueta ||
                    evento.animo_etiqueta
                "
                class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <span v-if="evento.veterinaria">{{ evento.veterinaria }}</span>
                <span v-if="evento.medicamentos">
                    {{ evento.medicamentos }}
                    {{
                        evento.medicamentos === 1
                            ? 'medicamento'
                            : 'medicamentos'
                    }}
                </span>
                <span
                    v-if="evento.adjuntos"
                    class="inline-flex items-center gap-1"
                >
                    <FileText class="size-3.5" aria-hidden="true" />
                    {{ evento.adjuntos }}
                </span>
                <span v-if="evento.proxima_dosis">
                    Próxima: {{ evento.proxima_dosis }}
                </span>
                <span v-if="evento.categoria_etiqueta">
                    {{ evento.categoria_etiqueta }}
                </span>
                <span v-if="evento.animo_etiqueta">
                    {{ evento.animo_etiqueta }}
                </span>
            </div>
        </div>
    </li>
</template>
