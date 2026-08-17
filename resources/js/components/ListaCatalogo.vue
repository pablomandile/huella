<script setup lang="ts" generic="T extends ItemCatalogo">
import { Form, router } from '@inertiajs/vue3';
import {
    Copy,
    Lock,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import type { ItemCatalogo } from '@/types/huella';

/*
 * Andamiaje común de los cinco catálogos: listar, buscar, dar de alta, editar,
 * duplicar y quitar. Cada página pone solo sus campos y sus líneas de tarjeta.
 *
 * El alta y la edición se resuelven en un sheet sobre el propio listado: en el
 * celular eso es una pantalla menos, una navegación menos y no se pierde el
 * lugar de la lista.
 */

const props = defineProps<{
    titulo: string;
    /** Cómo se llama un registro para el usuario: "veterinaria", "vacuna". */
    singular: string;
    registros: T[];
    urlAlta: string;
    urlEdicion: (registro: T) => string;
    urlBaja: (registro: T) => string;
    /** Solo los catálogos con semilla del sistema la necesitan. */
    urlDuplicar?: (registro: T) => string;
}>();

const abierto = ref(false);
// shallowRef y no ref: con un genérico, el unwrap profundo de ref rompe el tipo.
const editando = shallowRef<T | null>(null);
const busqueda = ref('');

const normalizar = (texto: string) =>
    texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

const conBuscador = computed(() => props.registros.length > 8);

const filtrados = computed(() => {
    const termino = normalizar(busqueda.value);

    if (!termino) {
        return props.registros;
    }

    return props.registros.filter((r) =>
        normalizar(`${r.etiqueta} ${r.detalle ?? ''}`).includes(termino),
    );
});

const propios = computed(() => props.registros.filter((r) => !r.es_semilla));
const deSistema = computed(() => props.registros.length - propios.value.length);

/**
 * El origen solo se marca cuando hay de los dos tipos. Si todo es del sistema
 * —que es como arranca cualquier cuenta— el cartel se repite en cada tarjeta
 * sin distinguir nada y se come una línea por fila, que en el celular es
 * justo lo que no sobra.
 */
const marcarOrigen = computed(
    () => propios.value.length > 0 && deSistema.value > 0,
);

function abrirAlta() {
    editando.value = null;
    abierto.value = true;
}

function abrirEdicion(registro: T) {
    editando.value = registro;
    abierto.value = true;
}

function duplicar(registro: T) {
    if (!props.urlDuplicar) {
        return;
    }

    router.post(props.urlDuplicar(registro), {}, { preserveScroll: true });
}

function eliminar(registro: T) {
    if (confirm(`¿Quitar «${registro.etiqueta}» del catálogo?`)) {
        router.delete(props.urlBaja(registro), { preserveScroll: true });
    }
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold">{{ titulo }}</h1>
                <!-- Sin género: sirve igual para veterinarias y medicamentos. -->
                <p class="text-sm text-muted-foreground">
                    {{ propios.length }} que cargaste
                    <template v-if="deSistema">
                        · {{ deSistema }} del sistema</template
                    >
                </p>
            </div>
            <Button
                v-if="registros.length"
                size="sm"
                class="touch-target shrink-0"
                @click="abrirAlta"
            >
                <Plus class="size-4" aria-hidden="true" />
                Agregar
            </Button>
        </div>

        <div v-if="conBuscador" class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="busqueda"
                type="search"
                class="touch-target pl-9"
                :placeholder="`Buscar en ${titulo.toLowerCase()}`"
                :aria-label="`Buscar en ${titulo.toLowerCase()}`"
            />
        </div>

        <!-- Estado vacío -->
        <div
            v-if="!registros.length"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div>
                <h2 class="font-medium">Todavía no cargaste nada acá</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Lo que cargues una vez lo vas a poder elegir después sin
                    volver a tipearlo.
                </p>
            </div>
            <Button class="touch-target" @click="abrirAlta">
                <Plus class="size-4" aria-hidden="true" />
                Agregar {{ singular }}
            </Button>
        </div>

        <p
            v-else-if="!filtrados.length"
            class="rounded-xl border border-dashed border-border px-6 py-12 text-center text-sm text-muted-foreground"
        >
            No hay resultados para «{{ busqueda }}».
        </p>

        <div v-else class="flex flex-col gap-2">
            <Card v-for="registro in filtrados" :key="registro.id" class="py-0">
                <CardContent class="flex items-start gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <slot name="item" :registro="registro">
                            <p class="truncate font-medium">
                                {{ registro.etiqueta }}
                            </p>
                            <p
                                v-if="registro.detalle"
                                class="truncate text-sm text-muted-foreground"
                            >
                                {{ registro.detalle }}
                            </p>
                        </slot>

                        <Badge
                            v-if="registro.es_semilla && marcarOrigen"
                            variant="secondary"
                            class="mt-2 gap-1 font-normal"
                        >
                            <Lock class="size-3" aria-hidden="true" />
                            Del sistema
                        </Badge>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="touch-target shrink-0"
                                :aria-label="`Acciones de ${registro.etiqueta}`"
                            >
                                <MoreVertical
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <!--
                                Regla de negocio 4: un registro del sistema no
                                se edita ni se borra. La salida es duplicarlo y
                                trabajar sobre la copia.
                            -->
                            <template v-if="registro.es_semilla">
                                <DropdownMenuItem
                                    v-if="urlDuplicar"
                                    @select="duplicar(registro)"
                                >
                                    <Copy class="size-4" aria-hidden="true" />
                                    Duplicar para editar
                                </DropdownMenuItem>
                            </template>
                            <template v-else>
                                <DropdownMenuItem
                                    @select="abrirEdicion(registro)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                    Editar
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="urlDuplicar"
                                    @select="duplicar(registro)"
                                >
                                    <Copy class="size-4" aria-hidden="true" />
                                    Duplicar
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @select="eliminar(registro)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    Quitar
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </CardContent>
            </Card>
        </div>

        <!-- Alta y edición -->
        <Sheet v-model:open="abierto">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{ editando ? 'Editar' : 'Agregar' }}
                        {{ singular }}
                    </SheetTitle>
                    <SheetDescription>
                        Lo que cargues acá lo vas a poder elegir después sin
                        volver a escribirlo.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :key="editando?.id ?? 'nuevo'"
                    :action="editando ? urlEdicion(editando) : urlAlta"
                    :method="editando ? 'put' : 'post'"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="abierto = false"
                >
                    <slot name="campos" :registro="editando" :errors="errors" />

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="abierto = false"
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
