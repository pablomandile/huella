<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { useTamanoLetra } from '@/composables/useTamanoLetra';
import type { TamanoLetra } from '@/composables/useTamanoLetra';

const { tamano, opciones, elegir } = useTamanoLetra();

const escalaActual = computed(
    () => opciones.value.find((o) => o.valor === tamano.value)?.escala ?? 112.5,
);

/*
 * La muestra se dibuja al tamaño que tendría la app con esa opción. En `em`
 * contra la escala vigente y no en px: en px mentiría para quien cambió el
 * tamaño de su navegador, que es justo quien viene a esta pantalla.
 */
function escalaDeLaMuestra(escala: number): string {
    return `${escala / escalaActual.value}em`;
}

function esElegido(valor: TamanoLetra): boolean {
    return valor === tamano.value;
}
</script>

<template>
    <fieldset class="w-full max-w-md space-y-2">
        <legend class="sr-only">Tamaño de letra</legend>

        <label
            v-for="opcion in opciones"
            :key="opcion.valor"
            :class="[
                'flex cursor-pointer gap-3 rounded-lg border p-3 transition-colors',
                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2',
                esElegido(opcion.valor)
                    ? 'border-primary bg-card'
                    : 'border-border bg-card/60',
            ]"
        >
            <!--
                Un radio de verdad, no un botón con rol: flechas, barra
                espaciadora y lectura del estado vienen gratis. `sr-only` y no
                `display: none`, o deja de existir para el lector.
            -->
            <input
                type="radio"
                name="tamano_letra"
                class="sr-only"
                :value="opcion.valor"
                :checked="esElegido(opcion.valor)"
                @change="elegir(opcion.valor)"
            />

            <div
                :class="[
                    'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                    esElegido(opcion.valor)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-muted-foreground/40',
                ]"
                aria-hidden="true"
            >
                <Check v-if="esElegido(opcion.valor)" class="size-4" />
            </div>

            <div class="min-w-0 flex-1 space-y-1">
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-medium">{{ opcion.etiqueta }}</span>
                    <span
                        v-if="esElegido(opcion.valor)"
                        class="text-xs text-muted-foreground"
                        >(el que estás usando)</span
                    >
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ opcion.descripcion }}
                </p>

                <!--
                    La muestra, a tamaño real y con contenido de la app: se
                    elige viendo si se va a poder leer una indicación.
                -->
                <p
                    class="border-t border-border/60 pt-2 leading-snug wrap-anywhere"
                    :style="{ fontSize: escalaDeLaMuestra(opcion.escala) }"
                >
                    Antibiótico · 1 comprimido cada 12 horas
                </p>
            </div>
        </label>
    </fieldset>
</template>
