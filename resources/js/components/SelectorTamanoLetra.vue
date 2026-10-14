<script setup lang="ts">
import { TAMANOS_LETRA, useTamanoLetra } from '@/composables/useTamanoLetra';

const { tamano, elegir } = useTamanoLetra();
</script>

<template>
    <!--
        Una grilla de tres y no una fila de pastillas como las del tema: con
        letra grande en 320px, "Pequeño · Mediano · Grande" con sus rellenos no
        entra en una fila de ancho intrínseco. `grid-cols-3` reparte el ancho
        disponible en partes iguales, y si una etiqueta no entra, baja de línea
        dentro de su celda en vez de empujar la pantalla hacia el costado.

        Es un radiogroup: se elige uno de tres, y así lo anuncia un lector.
    -->
    <div
        role="radiogroup"
        aria-label="Tamaño de letra"
        class="grid w-full max-w-sm grid-cols-3 gap-1 rounded-lg bg-muted p-1"
    >
        <button
            v-for="opcion in TAMANOS_LETRA"
            :key="opcion.valor"
            type="button"
            role="radio"
            :aria-checked="tamano === opcion.valor"
            class="min-w-0 rounded-md px-2 py-1.5 wrap-anywhere transition-colors"
            :class="
                tamano === opcion.valor
                    ? 'bg-card text-foreground shadow-xs dark:bg-accent dark:text-accent-foreground'
                    : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground'
            "
            @click="elegir(opcion.valor)"
        >
            <!--
                Cada opción se escribe en su propio tamaño: se elige viendo el
                resultado, no leyendo una palabra.
            -->
            <span
                :class="{
                    'text-xs': opcion.valor === 'pequeno',
                    'text-sm': opcion.valor === 'mediano',
                    'text-base': opcion.valor === 'grande',
                }"
                >{{ opcion.etiqueta }}</span
            >
        </button>
    </div>
</template>
