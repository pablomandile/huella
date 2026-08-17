<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Download, Filter, NotebookPen, Plus, Search, X } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import EventoDelDiario from '@/components/EventoDelDiario.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as mascotasIndex } from '@/routes/mascotas';
import {
    index as diarioIndex,
    mas as diarioMas,
} from '@/routes/mascotas/diario';
import { store as storeEntrada } from '@/routes/mascotas/entradas';
import { historiaClinica } from '@/routes/mascotas';
import type {
    EventoTimeline,
    FiltrosTimeline,
    Mascota,
    OpcionEnum,
} from '@/types/huella';

/*
 * El diario: la línea de tiempo unificada de una mascota.
 *
 * El scroll infinito trae las páginas por `fetch` y las **suma** a la lista que
 * ya está en pantalla. Con una navegación Inertia se reemplazaría la página
 * entera y se perdería la posición del scroll, que es lo único que no puede
 * pasar en una lista que se lee scrolleando.
 */

const props = defineProps<{
    mascota: Mascota;
    eventos: EventoTimeline[];
    cursor: string | null;
    hay_mas: boolean;
    totales: Record<string, number>;
    filtros: FiltrosTimeline;
    tipos: string[];
    categorias: OpcionEnum[];
    animos: OpcionEnum[];
    puedeRegistrar: boolean;
    hoy: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});

const etiquetasDeTipo: Record<string, string> = {
    visita: 'Visitas',
    vacuna: 'Vacunas',
    desparasitacion: 'Desparasitaciones',
    tratamiento: 'Medicación',
    peso: 'Peso',
    dieta: 'Alimentación',
    celo: 'Celo',
    entrada: 'Notas',
};

// La lista crece con el scroll; el servidor solo manda la primera página.
const acumulados = ref<EventoTimeline[]>([...props.eventos]);
const cursor = ref(props.cursor);
const hayMas = ref(props.hay_mas);
const cargando = ref(false);

const sheetFiltros = ref(false);
const sheetNota = ref(false);
const sheetExportar = ref(false);

const busqueda = ref(props.filtros.busqueda ?? '');
const tiposElegidos = ref<string[]>([...props.filtros.tipos]);
const desde = ref(props.filtros.desde ?? '');
const hasta = ref(props.filtros.hasta ?? '');

const centinela = useTemplateRef<HTMLElement>('centinela');
let observador: IntersectionObserver | null = null;

const hayFiltros = computed(
    () =>
        tiposElegidos.value.length > 0 ||
        Boolean(desde.value) ||
        Boolean(hasta.value) ||
        Boolean(props.filtros.busqueda),
);

const totalVisible = computed(() =>
    Object.entries(props.totales)
        .filter(
            ([tipo]) =>
                !tiposElegidos.value.length ||
                tiposElegidos.value.includes(tipo),
        )
        .reduce((suma, [, cantidad]) => suma + cantidad, 0),
);

// Si el servidor manda una primera página nueva (cambió un filtro), se reemplaza.
watch(
    () => props.eventos,
    (nuevos) => {
        acumulados.value = [...nuevos];
        cursor.value = props.cursor;
        hayMas.value = props.hay_mas;
    },
);

async function traerMas() {
    if (cargando.value || !hayMas.value || !cursor.value) {
        return;
    }

    cargando.value = true;

    try {
        const url = new URL(
            diarioMas(props.mascota.id).url,
            window.location.origin,
        );
        url.searchParams.set('cursor', cursor.value);

        if (props.filtros.busqueda) {
            url.searchParams.set('busqueda', props.filtros.busqueda);
        }

        if (props.filtros.desde) {
            url.searchParams.set('desde', props.filtros.desde);
        }

        if (props.filtros.hasta) {
            url.searchParams.set('hasta', props.filtros.hasta);
        }

        props.filtros.tipos.forEach((tipo) =>
            url.searchParams.append('tipos[]', tipo),
        );

        const respuesta = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!respuesta.ok) {
            hayMas.value = false;

            return;
        }

        const pagina = (await respuesta.json()) as {
            eventos: EventoTimeline[];
            cursor: string | null;
            hay_mas: boolean;
        };

        // Por clave, no por índice: si algo entró mientras se scrolleaba, no se
        // duplica.
        const yaEstan = new Set(acumulados.value.map((evento) => evento.clave));
        acumulados.value.push(
            ...pagina.eventos.filter((evento) => !yaEstan.has(evento.clave)),
        );

        cursor.value = pagina.cursor;
        hayMas.value = pagina.hay_mas;
    } finally {
        cargando.value = false;
    }
}

function aplicarFiltros() {
    router.get(
        diarioIndex(props.mascota.id).url,
        {
            tipos: tiposElegidos.value,
            desde: desde.value || undefined,
            hasta: hasta.value || undefined,
            busqueda: busqueda.value || undefined,
        },
        { preserveScroll: false, preserveState: true, replace: true },
    );

    sheetFiltros.value = false;
}

function limpiarFiltros() {
    tiposElegidos.value = [];
    desde.value = '';
    hasta.value = '';
    busqueda.value = '';
    aplicarFiltros();
}

function alternarTipo(tipo: string) {
    tiposElegidos.value = tiposElegidos.value.includes(tipo)
        ? tiposElegidos.value.filter((t) => t !== tipo)
        : [...tiposElegidos.value, tipo];
}

onMounted(() => {
    if (!centinela.value || !('IntersectionObserver' in window)) {
        return;
    }

    // Se dispara antes de llegar al final: la página siguiente ya está cuando
    // el usuario termina de leer la actual.
    observador = new IntersectionObserver(
        (entradas) => {
            if (entradas.some((entrada) => entrada.isIntersecting)) {
                void traerMas();
            }
        },
        { rootMargin: '400px' },
    );

    observador.observe(centinela.value);
});

onBeforeUnmount(() => observador?.disconnect());
</script>

<template>
    <Head :title="`Diario de ${mascota.nombre}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">Diario</h1>
                <p class="text-sm text-muted-foreground">
                    {{ mascota.nombre }} · {{ totalVisible }}
                    {{ totalVisible === 1 ? 'evento' : 'eventos' }}
                </p>
            </div>
            <div class="flex shrink-0 gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    class="touch-target"
                    aria-label="Descargar la historia clínica"
                    @click="sheetExportar = true"
                >
                    <Download class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    :variant="hayFiltros ? 'secondary' : 'ghost'"
                    size="icon"
                    class="touch-target"
                    aria-label="Filtrar el diario"
                    @click="sheetFiltros = true"
                >
                    <Filter class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    v-if="puedeRegistrar"
                    size="sm"
                    class="touch-target"
                    @click="sheetNota = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Nota
                </Button>
            </div>
        </div>

        <!-- Búsqueda siempre a la vista: es la forma más rápida de encontrar algo -->
        <form class="relative" @submit.prevent="aplicarFiltros">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="busqueda"
                type="search"
                class="touch-target pl-9"
                placeholder="Buscar en motivos, diagnósticos y notas"
                aria-label="Buscar en el diario"
            />
        </form>

        <!-- Qué filtros están puestos, y cómo sacarlos -->
        <div v-if="hayFiltros" class="flex flex-wrap items-center gap-2">
            <Badge
                v-for="tipo in tiposElegidos"
                :key="tipo"
                variant="secondary"
                class="gap-1 font-normal"
            >
                {{ etiquetasDeTipo[tipo] ?? tipo }}
                <button
                    type="button"
                    :aria-label="`Quitar el filtro ${etiquetasDeTipo[tipo] ?? tipo}`"
                    @click="
                        alternarTipo(tipo);
                        aplicarFiltros();
                    "
                >
                    <X class="size-3" aria-hidden="true" />
                </button>
            </Badge>
            <Badge
                v-if="filtros.desde || filtros.hasta"
                variant="secondary"
                class="font-normal"
            >
                {{ filtros.desde ?? '…' }} — {{ filtros.hasta ?? '…' }}
            </Badge>
            <Badge
                v-if="filtros.busqueda"
                variant="secondary"
                class="font-normal"
            >
                «{{ filtros.busqueda }}»
            </Badge>
            <Button
                variant="ghost"
                size="sm"
                class="touch-target"
                @click="limpiarFiltros"
            >
                Limpiar
            </Button>
        </div>

        <!-- Vacío -->
        <div
            v-if="!acumulados.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-accent"
            >
                <NotebookPen
                    class="size-7 text-accent-foreground"
                    aria-hidden="true"
                />
            </div>
            <div>
                <h2 class="font-medium">
                    <template v-if="hayFiltros">Nada con esos filtros</template>
                    <template v-else>El diario está vacío</template>
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="hayFiltros">
                        Probá quitando alguno.
                    </template>
                    <template v-else>
                        Todo lo que cargues —visitas, vacunas, pesos, notas— va
                        a aparecer acá en una sola línea de tiempo.
                    </template>
                </p>
            </div>
            <Button
                v-if="hayFiltros"
                class="touch-target"
                @click="limpiarFiltros"
            >
                Limpiar los filtros
            </Button>
        </div>

        <!-- La línea de tiempo -->
        <ul v-else class="flex flex-col">
            <EventoDelDiario
                v-for="(evento, indice) in acumulados"
                :key="evento.clave"
                :evento="evento"
                :ultimo="indice === acumulados.length - 1 && !hayMas"
            />
        </ul>

        <!-- El centinela del scroll infinito -->
        <div ref="centinela" class="flex justify-center py-2">
            <Spinner v-if="cargando" class="size-5" />
            <Button
                v-else-if="hayMas"
                variant="outline"
                class="touch-target"
                @click="traerMas"
            >
                Ver más
            </Button>
            <p
                v-else-if="acumulados.length > 10"
                class="text-sm text-muted-foreground"
            >
                Eso es todo lo que hay cargado.
            </p>
        </div>

        <!-- Filtros -->
        <Sheet v-model:open="sheetFiltros">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Filtrar el diario</SheetTitle>
                    <SheetDescription>
                        Los números dicen cuántos hay de cada tipo.
                    </SheetDescription>
                </SheetHeader>

                <div class="flex flex-col gap-4 p-4">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="tipo in tipos"
                            :key="tipo"
                            type="button"
                            size="sm"
                            :variant="
                                tiposElegidos.includes(tipo)
                                    ? 'secondary'
                                    : 'outline'
                            "
                            class="touch-target"
                            :aria-pressed="tiposElegidos.includes(tipo)"
                            :disabled="!totales[tipo]"
                            @click="alternarTipo(tipo)"
                        >
                            {{ etiquetasDeTipo[tipo] ?? tipo }}
                            <span class="ml-1 text-muted-foreground">
                                {{ totales[tipo] ?? 0 }}
                            </span>
                        </Button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="desde">Desde</Label>
                            <Input
                                id="desde"
                                v-model="desde"
                                type="date"
                                class="touch-target"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="hasta">Hasta</Label>
                            <Input
                                id="hasta"
                                v-model="hasta"
                                type="date"
                                class="touch-target"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="limpiarFiltros"
                        >
                            Limpiar
                        </Button>
                        <Button
                            type="button"
                            class="touch-target flex-1"
                            @click="aplicarFiltros"
                        >
                            Aplicar
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Nota nueva -->
        <Sheet v-model:open="sheetNota">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Agregar una nota</SheetTitle>
                    <SheetDescription>
                        Para lo que no entra en ningún módulo: «hoy vomitó dos
                        veces», «primera vez que sube solo al auto».
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storeEntrada(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetNota = false"
                >
                    <div class="grid gap-2">
                        <Label for="contenido">Qué pasó *</Label>
                        <TextareaNativo
                            name="contenido"
                            :rows="4"
                            placeholder="Hoy vomitó dos veces, después comió normal."
                        />
                        <InputError :message="errors.contenido" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fecha">Cuándo *</Label>
                            <Input
                                id="fecha"
                                name="fecha"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="hoy"
                            />
                            <InputError :message="errors.fecha" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="categoria">Categoría *</Label>
                            <SelectNativo
                                name="categoria"
                                :opciones="categorias"
                                default-value="general"
                            />
                            <InputError :message="errors.categoria" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="titulo">Título</Label>
                        <Input
                            id="titulo"
                            name="titulo"
                            maxlength="160"
                            placeholder="Opcional"
                        />
                        <InputError :message="errors.titulo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="animo">Cómo estaba de ánimo</Label>
                        <SelectNativo
                            name="animo"
                            :opciones="animos"
                            placeholder="Sin especificar"
                        />
                        <InputError :message="errors.animo" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetNota = false"
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

        <!-- Exportar -->
        <Sheet v-model:open="sheetExportar">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Historia clínica en PDF</SheetTitle>
                    <SheetDescription>
                        Para llevar a un veterinario nuevo o a un viaje. Las
                        alergias van primero.
                    </SheetDescription>
                </SheetHeader>

                <form
                    method="get"
                    :action="historiaClinica(mascota.id).url"
                    class="flex flex-col gap-4 p-4"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="pdf_desde">Desde</Label>
                            <Input
                                id="pdf_desde"
                                name="desde"
                                type="date"
                                class="touch-target"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pdf_hasta">Hasta</Label>
                            <Input
                                id="pdf_hasta"
                                name="hasta"
                                type="date"
                                class="touch-target"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Dejalos vacíos para llevar el historial completo.
                    </p>

                    <Button type="submit" class="touch-target w-full">
                        <Download class="size-4" aria-hidden="true" />
                        Descargar el PDF
                    </Button>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
