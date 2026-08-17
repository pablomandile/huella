<script setup lang="ts">
import { Check, ChevronDown, Plus, Search, X } from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
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
import { ErrorDeValidacion, postJson } from '@/lib/http';
import type { ItemCatalogo } from '@/types/huella';

/*
 * Selector de un ítem de catálogo, con búsqueda y alta al vuelo.
 *
 * El caso que resuelve es el de la veterinaria: estás cargando una visita, la
 * veterinaria no está en la lista, y no podés perder lo que ya escribiste para
 * ir a darla de alta. Por eso el alta va por `fetch` contra el mismo endpoint
 * del catálogo, que contesta JSON: la opción aparece elegida y el formulario
 * de atrás queda intacto.
 *
 * El valor viaja en un input oculto, así funciona dentro del <Form> de Inertia
 * igual que un <select> nativo, sin estado extra en la página.
 *
 * Se abre en sheet y no en dropdown a propósito: en el celular una lista larga
 * dentro de un popover es imposible de manejar con el pulgar.
 */

type RespuestaAlta = { registro: ItemCatalogo; mensaje: string };

const props = withDefaults(
    defineProps<{
        /** Nombre del input oculto, tal como lo espera el FormRequest. */
        name: string;
        opciones: ItemCatalogo[];
        /** Cómo se llama esto para el usuario: "veterinaria", "medicamento". */
        etiqueta: string;
        placeholder?: string;
        /** Ofrece limpiar la selección (campo opcional). */
        permiteVacio?: boolean;
        /** Endpoint del catálogo. Sin esto no se ofrece crear. */
        urlCrear?: string;
        /** Campo donde va el nombre en el alta rápida. */
        campoCrear?: string;
        /** Valores fijos que el FormRequest exige y el alta rápida no pregunta. */
        extrasCrear?: Record<string, unknown>;
    }>(),
    {
        placeholder: undefined,
        permiteVacio: true,
        urlCrear: undefined,
        campoCrear: 'nombre',
        extrasCrear: () => ({}),
    },
);

const seleccionadoId = defineModel<number | null>({ default: null });

/** Lo que llegó del servidor más lo creado en esta pantalla. */
const items = ref<ItemCatalogo[]>([...props.opciones]);

watch(
    () => props.opciones,
    (nuevas) => {
        // Tras una recarga de Inertia mandan las del servidor.
        items.value = [...nuevas];
    },
);

const abierto = ref(false);
const modo = ref<'elegir' | 'crear'>('elegir');
const busqueda = ref('');
const nombreNuevo = ref('');
const guardando = ref(false);
const errorAlta = ref<string | null>(null);

const campoBusqueda = useTemplateRef<HTMLInputElement>('campoBusqueda');
const campoNombre = useTemplateRef<HTMLInputElement>('campoNombre');

const seleccionado = computed(
    () => items.value.find((i) => i.id === seleccionadoId.value) ?? null,
);

/** Sin acentos y en minúscula: "sextuple" tiene que encontrar "Séxtuple". */
const normalizar = (texto: string) =>
    texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

const filtradas = computed(() => {
    const termino = normalizar(busqueda.value);

    if (!termino) {
        return items.value;
    }

    return items.value.filter((i) =>
        normalizar(`${i.etiqueta} ${i.detalle ?? ''}`).includes(termino),
    );
});

/** Si ya existe tal cual, ofrecer crearlo de nuevo sería invitar al duplicado. */
const yaExiste = computed(() =>
    items.value.some(
        (i) => normalizar(i.etiqueta) === normalizar(busqueda.value),
    ),
);

const puedeCrear = computed(() => Boolean(props.urlCrear) && !yaExiste.value);

async function abrir() {
    abierto.value = true;
    modo.value = 'elegir';
    busqueda.value = '';
    errorAlta.value = null;

    // Con pocas opciones el teclado tapa la lista y estorba más de lo que ayuda.
    if (items.value.length > 8) {
        await nextTick();
        campoBusqueda.value?.focus();
    }
}

function elegir(id: number | null) {
    seleccionadoId.value = id;
    abierto.value = false;
}

async function irACrear() {
    modo.value = 'crear';
    nombreNuevo.value = busqueda.value.trim();
    errorAlta.value = null;

    await nextTick();
    campoNombre.value?.focus();
}

async function crear() {
    const nombre = nombreNuevo.value.trim();

    if (!props.urlCrear || nombre === '' || guardando.value) {
        return;
    }

    guardando.value = true;
    errorAlta.value = null;

    try {
        const { registro } = await postJson<RespuestaAlta>(props.urlCrear, {
            [props.campoCrear]: nombre,
            ...props.extrasCrear,
        });

        items.value = [...items.value, registro].sort((a, b) =>
            a.etiqueta.localeCompare(b.etiqueta, 'es'),
        );

        elegir(registro.id);
    } catch (error) {
        errorAlta.value =
            error instanceof ErrorDeValidacion
                ? (error.porCampo[props.campoCrear] ?? error.message)
                : (error as Error).message;
    } finally {
        guardando.value = false;
    }
}
</script>

<template>
    <div>
        <!-- Lo que realmente se envía con el formulario. -->
        <input type="hidden" :name="name" :value="seleccionadoId ?? ''" />

        <button
            type="button"
            class="flex touch-target w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-left text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-input/30"
            :aria-label="`Elegir ${etiqueta}`"
            @click="abrir"
        >
            <span v-if="seleccionado" class="min-w-0">
                <span class="block truncate">{{ seleccionado.etiqueta }}</span>
                <span
                    v-if="seleccionado.detalle"
                    class="block truncate text-xs text-muted-foreground"
                >
                    {{ seleccionado.detalle }}
                </span>
            </span>
            <span v-else class="truncate text-muted-foreground">
                {{ placeholder ?? `Elegí ${etiqueta}` }}
            </span>

            <ChevronDown
                class="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </button>

        <Sheet v-model:open="abierto">
            <SheetContent
                side="bottom"
                class="max-h-[85dvh] gap-0 rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <!-- Paso 1: elegir de la lista -->
                <template v-if="modo === 'elegir'">
                    <SheetHeader class="pb-2">
                        <SheetTitle class="capitalize">{{
                            etiqueta
                        }}</SheetTitle>
                        <SheetDescription class="sr-only">
                            Buscá en la lista o agregá una opción nueva.
                        </SheetDescription>
                    </SheetHeader>

                    <div class="px-4 pb-3">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                ref="campoBusqueda"
                                v-model="busqueda"
                                type="search"
                                class="touch-target pl-9"
                                :placeholder="`Buscar ${etiqueta}`"
                                :aria-label="`Buscar ${etiqueta}`"
                            />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-2">
                        <button
                            v-if="permiteVacio && !busqueda"
                            type="button"
                            class="flex touch-target w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm text-muted-foreground hover:bg-accent"
                            @click="elegir(null)"
                        >
                            Sin {{ etiqueta }}
                            <X
                                v-if="seleccionadoId === null"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </button>

                        <button
                            v-for="item in filtradas"
                            :key="item.id"
                            type="button"
                            class="flex touch-target w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left hover:bg-accent"
                            :aria-pressed="item.id === seleccionadoId"
                            @click="elegir(item.id)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm">{{
                                    item.etiqueta
                                }}</span>
                                <span
                                    v-if="item.detalle"
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    {{ item.detalle }}
                                </span>
                            </span>
                            <Check
                                v-if="item.id === seleccionadoId"
                                class="size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                        </button>

                        <p
                            v-if="!filtradas.length"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            No hay resultados para «{{ busqueda }}».
                        </p>
                    </div>

                    <div v-if="puedeCrear" class="border-t border-border p-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="touch-target w-full"
                            @click="irACrear"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            <span v-if="busqueda.trim()" class="truncate">
                                Agregar «{{ busqueda.trim() }}»
                            </span>
                            <span v-else>No está en la lista</span>
                        </Button>
                    </div>
                </template>

                <!-- Paso 2: alta rápida, sin salir del formulario de atrás -->
                <template v-else>
                    <SheetHeader class="pb-2">
                        <SheetTitle>Agregar {{ etiqueta }}</SheetTitle>
                        <SheetDescription>
                            Con el nombre alcanza. El resto de los datos se
                            completan después desde Catálogos.
                        </SheetDescription>
                    </SheetHeader>

                    <form
                        class="flex flex-col gap-3 p-4"
                        @submit.prevent="crear"
                    >
                        <div class="grid gap-2">
                            <Label
                                for="nombre-catalogo-nuevo"
                                class="capitalize"
                            >
                                {{ etiqueta }}
                            </Label>
                            <Input
                                id="nombre-catalogo-nuevo"
                                ref="campoNombre"
                                v-model="nombreNuevo"
                                class="touch-target"
                                required
                                :aria-invalid="Boolean(errorAlta)"
                            />
                            <p
                                v-if="errorAlta"
                                class="text-sm text-destructive"
                            >
                                {{ errorAlta }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                class="touch-target"
                                @click="modo = 'elegir'"
                            >
                                Volver
                            </Button>
                            <Button
                                type="submit"
                                class="touch-target flex-1"
                                :disabled="guardando || !nombreNuevo.trim()"
                            >
                                <Spinner v-if="guardando" class="size-4" />
                                Guardar y elegir
                            </Button>
                        </div>
                    </form>
                </template>
            </SheetContent>
        </Sheet>
    </div>
</template>
