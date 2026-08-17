<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeMedicamento } from '@/routes/catalogos/medicamentos';
import type { Medicamento, OpcionEnum, Tratamiento } from '@/types/huella';

/*
 * Un medicamento con su posología. Se usa repetido dentro del alta de la visita
 * —el criterio de la fase pide dos remedios en la misma pantalla— y también
 * suelto, para agregar un tratamiento a una visita ya cargada.
 *
 * Los campos van con `name` prefijado, así viajan solos dentro del <Form> de
 * Inertia sin estado intermedio.
 */

const props = withDefaults(
    defineProps<{
        /** Prefijo del name: "tratamientos[0]" en el alta, vacío cuando va suelto. */
        prefijo?: string;
        medicamentos: Medicamento[];
        vias: OpcionEnum[];
        /** Errores del <Form>, ya resueltos para este bloque. */
        errores?: Record<string, string>;
        tratamiento?: Tratamiento;
        /** Fecha por defecto del inicio; normalmente hoy. */
        hoy: string;
        /** Se muestra el botón de quitar solo cuando hay más de uno. */
        quitable?: boolean;
    }>(),
    {
        prefijo: '',
        errores: () => ({}),
        tratamiento: undefined,
        quitable: false,
    },
);

const emit = defineEmits<{ quitar: [] }>();

/** `tratamientos[0][dosis]` cuando va anidado, `dosis` cuando va suelto. */
const campo = (nombre: string) =>
    props.prefijo ? `${props.prefijo}[${nombre}]` : nombre;

/** Los id de los inputs tienen que ser únicos con varios bloques en pantalla. */
const id = (nombre: string) =>
    props.prefijo ? `${props.prefijo.replace(/[[\]]/g, '-')}${nombre}` : nombre;
</script>

<template>
    <div class="flex flex-col gap-4 rounded-lg border border-border p-4">
        <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-medium">Medicamento</p>
            <Button
                v-if="quitable"
                type="button"
                variant="ghost"
                size="icon"
                class="-mt-2 -mr-2 touch-target shrink-0"
                aria-label="Quitar este medicamento"
                @click="emit('quitar')"
            >
                <Trash2 class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <div class="grid gap-2">
            <!--
                Del catálogo, con alta al vuelo: el remedio que recetaron puede
                no estar cargado y no se puede perder la visita a medio escribir
                por eso. El mismo combo de la fase 3.
            -->
            <ComboboxCatalogo
                :name="campo('medicamento_id')"
                etiqueta="medicamento"
                placeholder="Buscá en el catálogo"
                :opciones="medicamentos"
                :model-value="tratamiento?.medicamento_id ?? null"
                :url-crear="storeMedicamento().url"
                campo-crear="nombre_comercial"
                :extras-crear="{ categoria: 'otro' }"
            />
            <p class="text-xs text-muted-foreground">
                Si no lo encontrás, escribilo abajo y seguí.
            </p>
            <Input
                :id="id('medicamento_libre')"
                :name="campo('medicamento_libre')"
                maxlength="140"
                autocomplete="off"
                placeholder="O escribí el nombre"
                :default-value="tratamiento?.medicamento_libre ?? ''"
            />
            <InputError :message="errores.medicamento_libre" />
            <InputError :message="errores.medicamento_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="id('dosis')">Dosis *</Label>
                <Input
                    :id="id('dosis')"
                    :name="campo('dosis')"
                    required
                    maxlength="80"
                    autocomplete="off"
                    placeholder="1 comprimido"
                    :default-value="tratamiento?.dosis"
                />
                <InputError :message="errores.dosis" />
            </div>

            <div class="grid gap-2">
                <Label :for="id('via')">Vía *</Label>
                <SelectNativo
                    :name="campo('via')"
                    :opciones="vias"
                    :default-value="tratamiento?.via ?? 'oral'"
                />
                <InputError :message="errores.via" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label :for="id('frecuencia_horas')">Cada cuántas horas</Label>
                <Input
                    :id="id('frecuencia_horas')"
                    :name="campo('frecuencia_horas')"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="720"
                    placeholder="8"
                    :default-value="tratamiento?.frecuencia_horas ?? ''"
                />
                <InputError :message="errores.frecuencia_horas" />
            </div>

            <div class="grid gap-2">
                <Label :for="id('duracion_dias')">Por cuántos días</Label>
                <Input
                    :id="id('duracion_dias')"
                    :name="campo('duracion_dias')"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="365"
                    placeholder="7"
                    :default-value="tratamiento?.duracion_dias ?? ''"
                />
                <InputError :message="errores.duracion_dias" />
            </div>

            <div class="grid gap-2">
                <Label :for="id('hora_primera_toma')">Primera toma</Label>
                <Input
                    :id="id('hora_primera_toma')"
                    :name="campo('hora_primera_toma')"
                    type="time"
                    :default-value="tratamiento?.hora_primera_toma ?? '08:00'"
                />
                <InputError :message="errores.hora_primera_toma" />
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Con la frecuencia y los días alcanza: las tomas se arman solas y
            aparecen en «Medicación de hoy». Si no lleva horario fijo, dejalos
            vacíos.
        </p>

        <input
            type="hidden"
            :name="campo('fecha_inicio')"
            :value="tratamiento?.fecha_inicio ?? hoy"
        />

        <div class="grid gap-2">
            <Label :for="id('notas')">Indicaciones</Label>
            <TextareaNativo
                :name="campo('notas')"
                :rows="2"
                placeholder="Dar con comida"
                :default-value="tratamiento?.notas"
            />
            <InputError :message="errores.notas" />
        </div>
    </div>
</template>
