<script setup lang="ts">
import { CloudOff, RefreshCw, TriangleAlert, WifiOff } from '@lucide/vue';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import relativeTime from 'dayjs/plugin/relativeTime';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    descartar,
    fallidas,
    pendientes,
    reintentar,
} from '@/sinConexion/cola';
import { datosDel, sinConexion } from '@/sinConexion/conexion';
import { sincronizarAhora, sincronizando } from '@/sinConexion/sincronizar';

dayjs.extend(relativeTime);
dayjs.locale('es');

/*
 * La franja de arriba cuando algo no está al día. Tres casos, en orden de
 * importancia:
 *
 * 1. Sin conexión. Lo que se ve es lo último que se guardó, y **siempre dice de
 *    cuándo**: son datos clínicos, y una dosis vieja es peligrosa cuando parece
 *    actual, no cuando dice "de hace 3 horas". Es la condición con la que se
 *    aceptó guardar datos sin conexión.
 * 2. Hay cambios que el servidor rechazó (un peso duplicado, una ficha que pasó
 *    a modo lectura). No se muestran como hechos: se listan acá para reintentar
 *    o descartar.
 * 3. Con conexión y cambios en camino: "sincronizando".
 */

const hojaAbierta = ref(false);

const antiguedad = computed(() =>
    datosDel.value ? dayjs(datosDel.value).fromNow() : null,
);

const textoPendientes = computed(() => {
    const n = pendientes.value.length;

    return n === 1
        ? '1 cambio sin sincronizar'
        : `${n} cambios sin sincronizar`;
});

const visible = computed(
    () =>
        sinConexion.value ||
        fallidas.value.length > 0 ||
        pendientes.value.length > 0,
);

/** El primer mensaje de error, que es el que explica el rechazo. */
const motivo = (errores: Record<string, string[]> | null) =>
    errores
        ? (Object.values(errores)[0]?.[0] ?? 'No se pudo guardar.')
        : 'No se pudo guardar.';
</script>

<template>
    <!--
        `sticky` y no `fixed`: flotando encima tapaba el encabezado —el menú y el
        selector de mascota— justo mientras se está sin conexión, que ahora
        puede ser un buen rato. Así ocupa su lugar y empuja el contenido.
    -->
    <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            role="status"
            aria-live="polite"
            class="letra-con-tope sticky top-0 z-50 pt-safe"
            :class="
                fallidas.length
                    ? 'bg-destructive text-destructive-foreground'
                    : 'bg-amber-500 text-amber-950'
            "
        >
            <div
                class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-sm font-medium"
            >
                <template v-if="sinConexion">
                    <WifiOff class="size-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 text-center wrap-anywhere">
                        Sin conexión<template v-if="antiguedad">
                            · datos de {{ antiguedad }}</template
                        ><template v-else>
                            · mostrando lo último guardado</template
                        >.
                        <template v-if="pendientes.length">
                            {{ textoPendientes }}.</template
                        >
                    </span>
                </template>

                <template v-else-if="pendientes.length">
                    <RefreshCw
                        class="size-4 shrink-0"
                        :class="{ 'animate-spin': sincronizando }"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 wrap-anywhere">
                        {{ sincronizando ? 'Sincronizando' : textoPendientes }}…
                    </span>
                    <button
                        v-if="!sincronizando"
                        type="button"
                        class="touch-target rounded-md px-2 underline underline-offset-2"
                        @click="sincronizarAhora()"
                    >
                        Reintentar ahora
                    </button>
                </template>

                <button
                    v-if="fallidas.length"
                    type="button"
                    class="inline-flex touch-target items-center gap-1.5 rounded-md px-2 underline underline-offset-2"
                    @click="hojaAbierta = true"
                >
                    <TriangleAlert class="size-4 shrink-0" aria-hidden="true" />
                    {{
                        fallidas.length === 1
                            ? '1 cambio no se pudo guardar'
                            : `${fallidas.length} cambios no se pudieron guardar`
                    }}
                </button>
            </div>
        </div>
    </Transition>

    <Sheet v-model:open="hojaAbierta">
        <SheetContent
            side="bottom"
            class="max-h-[85dvh] overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
        >
            <SheetHeader>
                <SheetTitle>Cambios sin guardar</SheetTitle>
                <SheetDescription>
                    Los cargaste sin conexión y el servidor no los aceptó.
                    Corregí lo que haga falta y reintentá, o descartalos.
                </SheetDescription>
            </SheetHeader>

            <ul class="flex flex-col gap-3 p-4">
                <li
                    v-for="op in fallidas"
                    :key="op.op_id"
                    class="flex flex-col gap-2 rounded-lg border p-3"
                >
                    <div class="flex items-start gap-2">
                        <CloudOff
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium wrap-anywhere">
                                {{ op.resumen }}
                            </p>
                            <p class="text-sm wrap-anywhere text-destructive">
                                {{ motivo(op.errores) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="touch-target"
                            @click="
                                reintentar(op.op_id).then(() =>
                                    sincronizarAhora(),
                                )
                            "
                        >
                            Reintentar
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="touch-target text-destructive"
                            @click="descartar(op.op_id)"
                        >
                            Descartar
                        </Button>
                    </div>
                </li>
            </ul>
        </SheetContent>
    </Sheet>
</template>
