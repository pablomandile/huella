<script setup lang="ts">
import { ChevronLeft, ChevronRight, Pause, Play, X } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import type { FotoGaleria } from '@/types/huella';

/*
 * Pase de fotos de la galería, a pantalla completa.
 *
 * Va en orden **cronológico ascendente por el campo `fecha`**, no por orden de
 * carga: la grilla se ve al revés —la más nueva primero, porque ahí interesa lo
 * último— pero un pase de fotos cuenta la vida de la mascota, y para eso tiene
 * que ir de lo más viejo a lo más nuevo. Alguien que cargó ayer una foto de hace
 * dos años no vería su historia en el orden en que pasó.
 *
 * El botón de pausa no es un adorno: WCAG 2.2.2 pide que todo lo que se mueve o
 * se actualiza solo por más de cinco segundos se pueda pausar. También se
 * reinicia la cuenta al avanzar a mano, porque quien toca «siguiente» quiere
 * mirar esa foto, no que se le vaya en lo que quedaba del intervalo.
 */

const SEGUNDOS = 4;

const abierto = defineModel<boolean>('abierto', { required: true });

const props = defineProps<{
    fotos: FotoGaleria[];
    nombreMascota: string;
}>();

/**
 * `fecha` llega como `YYYY-MM-DD`, así que el orden alfabético **es** el
 * cronológico. El desempate por id deja estable el orden de dos fotos del mismo
 * día, que si no se movería entre renders.
 */
const enOrden = computed(() =>
    [...props.fotos].sort(
        (a, b) => a.fecha.localeCompare(b.fecha) || a.id - b.id,
    ),
);

const indice = ref(0);
const pausado = ref(false);

const actual = computed(() => enOrden.value[indice.value] ?? null);

/** La que sigue, precargada, para que la transición no muestre un hueco. */
const siguiente = computed(() => {
    const total = enOrden.value.length;

    return total > 1 ? enOrden.value[(indice.value + 1) % total] : null;
});

function avanzar(paso: number) {
    const total = enOrden.value.length;

    if (total === 0) {
        return;
    }

    indice.value = (indice.value + paso + total) % total;
}

const { pause, resume } = useIntervalFn(() => avanzar(1), SEGUNDOS * 1000, {
    immediate: false,
});

function alternarPausa() {
    pausado.value = !pausado.value;

    if (pausado.value) {
        pause();
    } else {
        resume();
    }
}

function irA(paso: number) {
    avanzar(paso);

    if (!pausado.value) {
        pause();
        resume();
    }
}

// El reloj corre solo con el pase abierto: si no, sigue latiendo de fondo.
watch(abierto, (esta) => {
    if (esta) {
        indice.value = 0;
        pausado.value = false;
        resume();
    } else {
        pause();
    }
});

function alTeclado(evento: KeyboardEvent) {
    if (evento.key === 'ArrowRight') {
        irA(1);
    } else if (evento.key === 'ArrowLeft') {
        irA(-1);
    } else if (evento.key === ' ') {
        evento.preventDefault();
        alternarPausa();
    }
}
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent
            :show-close-button="false"
            class="flex h-[100dvh] max-h-none w-screen max-w-none flex-col gap-0 rounded-none border-0 bg-neutral-950 p-0 text-neutral-50"
            @keydown="alTeclado"
        >
            <DialogTitle class="sr-only">
                Pase de fotos de {{ props.nombreMascota }}
            </DialogTitle>

            <div
                class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden p-3 pt-16"
            >
                <!--
                    Las dos imágenes se encinan y se cruzan por opacidad. Con
                    `mode="out-in"` habría un instante en negro entre una y otra,
                    que es justo lo que hace que un pase se sienta brusco.
                -->
                <Transition
                    enter-active-class="transition-opacity duration-700 ease-out"
                    leave-active-class="absolute transition-opacity duration-700 ease-in"
                    enter-from-class="opacity-0"
                    leave-to-class="opacity-0"
                >
                    <img
                        v-if="actual"
                        :key="actual.id"
                        :src="actual.url"
                        :alt="
                            actual.epigrafe ??
                            `Foto de ${props.nombreMascota} del ${actual.fecha}`
                        "
                        class="max-h-full max-w-full object-contain"
                    />
                </Transition>

                <!-- Precarga silenciosa de la que viene. -->
                <img
                    v-if="siguiente"
                    :src="siguiente.url"
                    alt=""
                    aria-hidden="true"
                    class="pointer-events-none absolute size-px opacity-0"
                />
            </div>

            <div
                class="flex items-center justify-between gap-3 border-t border-white/10 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
            >
                <div class="min-w-0">
                    <p v-if="actual" class="truncate text-sm text-neutral-200">
                        {{ actual.fecha }}
                        <template v-if="actual.epigrafe">
                            · {{ actual.epigrafe }}</template
                        >
                    </p>
                    <p class="text-xs text-neutral-400">
                        {{ indice + 1 }} de {{ enOrden.length }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="touch-target text-neutral-50 hover:bg-white/10 hover:text-neutral-50"
                        aria-label="Foto anterior"
                        @click="irA(-1)"
                    >
                        <ChevronLeft class="size-5" aria-hidden="true" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="touch-target text-neutral-50 hover:bg-white/10 hover:text-neutral-50"
                        :aria-label="
                            pausado ? 'Reanudar el pase' : 'Pausar el pase'
                        "
                        data-test="pase-pausa"
                        @click="alternarPausa"
                    >
                        <Play
                            v-if="pausado"
                            class="size-5"
                            aria-hidden="true"
                        />
                        <Pause v-else class="size-5" aria-hidden="true" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="touch-target text-neutral-50 hover:bg-white/10 hover:text-neutral-50"
                        aria-label="Foto siguiente"
                        @click="irA(1)"
                    >
                        <ChevronRight class="size-5" aria-hidden="true" />
                    </Button>
                </div>
            </div>

            <button
                type="button"
                class="absolute top-3 right-3 flex touch-target items-center justify-center rounded-full bg-black/60 text-neutral-50 ring-offset-2 ring-offset-neutral-950 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-hidden"
                @click="abierto = false"
            >
                <X class="size-5" aria-hidden="true" />
                <span class="sr-only">Cerrar el pase de fotos</span>
            </button>
        </DialogContent>
    </Dialog>
</template>
