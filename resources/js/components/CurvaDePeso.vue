<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js';
import { computed, ref } from 'vue';
import { Line } from 'vue-chartjs';
import { Button } from '@/components/ui/button';
import type { RegistroPeso } from '@/types/huella';

/*
 * Curva de evolución del peso.
 *
 * Decisiones de lectura, que en un gráfico de salud importan más que el gráfico:
 *
 * 1. **El eje Y no arranca en cero.** Un perro de 18 kg que engorda 2 se ve
 *    plano en una escala 0–20, y esos 2 kg son justamente el dato. Se usa el
 *    rango real con un margen, que es lo que hace cualquier app de peso. La
 *    contra conocida es que exagera la variación, así que el eje va etiquetado
 *    en kg y la variación numérica se muestra al lado, fuera del gráfico.
 * 2. **Los pesos de la veterinaria se dibujan distinto.** Su balanza y la de
 *    casa no coinciden; sin distinguirlos, un salto de balanza se lee como que
 *    la mascota engordó de un día para el otro.
 * 3. **El gráfico no es la única fuente.** Debajo va la lista con las fechas y
 *    los valores: con lector de pantalla, o con dos puntos encimados, el
 *    gráfico solo no alcanza.
 * 4. Sin animación de entrada: en el celular, sobre datos clínicos, distrae.
 */

ChartJS.register(
    CategoryScale,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
);

const props = defineProps<{
    pesos: RegistroPeso[];
}>();

type Rango = { etiqueta: string; meses: number | null };

const rangos: Rango[] = [
    { etiqueta: '3 meses', meses: 3 },
    { etiqueta: '1 año', meses: 12 },
    { etiqueta: 'Todo', meses: null },
];

// El rango más chico que tenga al menos dos puntos: con un solo punto no hay
// curva, y arrancar en "3 meses" sobre datos viejos muestra un gráfico vacío.
const rangoElegido = ref<Rango>(
    rangos.find((rango) => enRango(props.pesos, rango).length >= 2) ??
        rangos[rangos.length - 1],
);

function enRango(pesos: RegistroPeso[], rango: Rango): RegistroPeso[] {
    if (rango.meses === null) {
        return pesos;
    }

    const desde = new Date();
    desde.setMonth(desde.getMonth() - rango.meses);

    return pesos.filter((peso) => new Date(`${peso.fecha}T00:00:00`) >= desde);
}

const visibles = computed(() => enRango(props.pesos, rangoElegido.value));

/** Los kilos del punto, o null para que la línea no lo salte. */
const kilos = computed(() => visibles.value.map((peso) => peso.peso_kg));

/**
 * Margen del eje: medio kilo o el 5% del rango, el que sea mayor. Sin esto, el
 * primer y el último punto quedan pegados al borde.
 */
const limites = computed(() => {
    if (!kilos.value.length) {
        return { min: undefined, max: undefined };
    }

    const menor = Math.min(...kilos.value);
    const mayor = Math.max(...kilos.value);
    const margen = Math.max(0.5, (mayor - menor) * 0.05);

    return {
        min: Math.max(0, Number((menor - margen).toFixed(2))),
        max: Number((mayor + margen).toFixed(2)),
    };
});

const datos = computed<ChartData<'line'>>(() => ({
    labels: visibles.value.map((peso) =>
        new Date(`${peso.fecha}T00:00:00`).toLocaleDateString('es-AR', {
            day: 'numeric',
            month: 'short',
        }),
    ),
    datasets: [
        {
            label: 'Peso',
            data: kilos.value,
            borderColor: 'oklch(0.72 0.13 178)',
            backgroundColor: 'oklch(0.72 0.13 178 / 0.12)',
            borderWidth: 2,
            fill: true,
            tension: 0.25,
            // Los de veterinaria, más grandes y con el centro hueco.
            pointRadius: visibles.value.map((peso) =>
                peso.en_veterinaria ? 6 : 4,
            ),
            pointStyle: visibles.value.map((peso) =>
                peso.en_veterinaria ? 'rectRot' : 'circle',
            ),
            pointBackgroundColor: visibles.value.map((peso) =>
                peso.en_veterinaria
                    ? 'oklch(0.98 0 0)'
                    : 'oklch(0.72 0.13 178)',
            ),
            pointBorderColor: 'oklch(0.72 0.13 178)',
            pointBorderWidth: 2,
            pointHoverRadius: 7,
        },
    ],
}));

const opciones = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
        y: {
            min: limites.value.min,
            max: limites.value.max,
            // El eje va etiquetado porque no arranca en cero: sin la unidad, la
            // pendiente se lee más grande de lo que es.
            title: { display: true, text: 'Kilos' },
            ticks: { callback: (valor) => `${valor} kg` },
            grid: { color: 'oklch(0.6 0 0 / 0.15)' },
        },
        x: {
            grid: { display: false },
            ticks: { maxRotation: 0, autoSkipPadding: 12 },
        },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (contexto: TooltipItem<'line'>) => {
                    const peso = visibles.value[contexto.dataIndex];

                    return [
                        peso.peso_legible,
                        peso.origen_etiqueta,
                        peso.condicion_corporal
                            ? `Condición corporal ${peso.condicion_corporal}/9`
                            : '',
                    ].filter(Boolean);
                },
                title: (items: TooltipItem<'line'>[]) =>
                    visibles.value[items[0].dataIndex]?.fecha_legible ?? '',
            },
        },
    },
}));

const hayVeterinaria = computed(() =>
    visibles.value.some((peso) => peso.en_veterinaria),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <!-- Referencia de los dos tipos de punto, solo si hay de los dos. -->
            <p
                v-if="hayVeterinaria"
                class="flex items-center gap-3 text-xs text-muted-foreground"
            >
                <span class="inline-flex items-center gap-1.5">
                    <span
                        class="size-2 rounded-full bg-primary"
                        aria-hidden="true"
                    />
                    En casa
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span
                        class="size-2 rotate-45 border-2 border-primary bg-background"
                        aria-hidden="true"
                    />
                    En la veterinaria
                </span>
            </p>
            <span v-else />

            <div class="flex gap-1" role="group" aria-label="Rango del gráfico">
                <Button
                    v-for="rango in rangos"
                    :key="rango.etiqueta"
                    type="button"
                    size="sm"
                    :variant="
                        rango.etiqueta === rangoElegido.etiqueta
                            ? 'secondary'
                            : 'ghost'
                    "
                    class="touch-target"
                    :aria-pressed="rango.etiqueta === rangoElegido.etiqueta"
                    @click="rangoElegido = rango"
                >
                    {{ rango.etiqueta }}
                </Button>
            </div>
        </div>

        <div v-if="visibles.length >= 2" class="h-56 sm:h-64">
            <Line :data="datos" :options="opciones" />
        </div>

        <p
            v-else
            class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
        >
            <template v-if="pesos.length === 1">
                Con un solo peso todavía no hay curva. Cargá el próximo y
                aparece.
            </template>
            <template v-else>
                No hay pesos en este rango. Probá con «Todo».
            </template>
        </p>
    </div>
</template>
