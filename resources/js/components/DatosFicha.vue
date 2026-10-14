<script setup lang="ts">
import { computed } from 'vue';
import type { Mascota } from '@/types/huella';

/*
 * Los datos de la ficha de una mascota, como lista de definición. Lo usan la
 * pantalla de la mascota y el inicio: vive en un solo lugar para que un campo
 * nuevo aparezca en los dos sin tener que acordarse del otro.
 *
 * Los campos vacíos no se muestran. Una fila "Pedigree: —" no dice nada y en
 * el celular empuja hacia abajo lo que sí está cargado.
 */

const props = defineProps<{
    mascota: Mascota;
}>();

const datos = computed(() =>
    (
        [
            ['Especie', props.mascota.especie_etiqueta],
            ['Raza', props.mascota.raza],
            ['Sexo', props.mascota.sexo_etiqueta],
            ['Edad', props.mascota.edad],
            ['Nacimiento', props.mascota.fecha_nacimiento],
            ['Adopción', props.mascota.fecha_adopcion],
            ['Color', props.mascota.color],
            ['Pelaje', props.mascota.tipo_pelaje_etiqueta],
            ['Microchip', props.mascota.microchip],
            ['Libreta sanitaria', props.mascota.libreta_sanitaria],
            ['Pedigree', props.mascota.pedigree],
            [
                'Castración',
                props.mascota.castrado
                    ? (props.mascota.fecha_castracion ?? 'Sí')
                    : 'No',
            ],
            ['Seguro', props.mascota.seguro_compania],
            ['Vencimiento del seguro', props.mascota.seguro_vencimiento],
        ] as [string, string | null][]
    ).filter((par): par is [string, string] => Boolean(par[1])),
);
</script>

<template>
    <div>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            <div
                v-for="[etiqueta, valor] in datos"
                :key="etiqueta"
                class="flex justify-between gap-4 border-b border-border/50 py-1.5 text-sm"
            >
                <dt class="text-muted-foreground">{{ etiqueta }}</dt>
                <dd class="text-right font-medium">{{ valor }}</dd>
            </div>
        </dl>
        <p
            v-if="mascota.senias_particulares"
            class="mt-3 text-sm text-muted-foreground"
        >
            <span class="font-medium text-foreground">Señas particulares:</span>
            {{ mascota.senias_particulares }}
        </p>
    </div>
</template>
