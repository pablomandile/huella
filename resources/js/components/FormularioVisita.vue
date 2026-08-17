<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ChevronDown, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoArchivos from '@/components/CampoArchivos.vue';
import CamposTratamiento from '@/components/CamposTratamiento.vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as storeVeterinaria } from '@/routes/catalogos/veterinarias';
import { store as storeVeterinario } from '@/routes/catalogos/veterinarios';
import type {
    Medicamento,
    OpcionEnum,
    Veterinaria,
    Veterinario,
    Visita,
} from '@/types/huella';

/*
 * El formulario de la visita, que es el criterio de esta fase: una consulta por
 * gastroenteritis con dos medicamentos y la receta adjunta tiene que entrar
 * completa sin salir de la pantalla.
 *
 * Todo lo que no es imprescindible está plegado. Lo que se ve al abrir es lo
 * que se contesta de memoria en el mostrador: cuándo, por qué y dónde.
 *
 * Los valores viajan en inputs nativos dentro del <Form> de Inertia: sin estado
 * intermedio, los combos y los archivos incluidos.
 */

const props = withDefaults(
    defineProps<{
        action: string;
        /** Con visita, edita (method spoofing a PUT por los archivos). */
        visita?: Visita;
        /** "Ahora" en el reloj del usuario, para precargar el alta. */
        ahora: string;
        veterinarias: Veterinaria[];
        veterinarios: Veterinario[];
        medicamentos: Medicamento[];
        tiposVisita: OpcionEnum[];
        vias: OpcionEnum[];
        textoEnviar: string;
        /** En la edición los tratamientos se manejan desde la ficha. */
        conTratamientos?: boolean;
    }>(),
    { visita: undefined, conTratamientos: true },
);

// Cuántos bloques de medicamento hay en pantalla. Solo se guarda la clave de
// cada bloque: los valores los lleva el formulario nativo.
let siguiente = 0;
const bloques = ref<number[]>([]);

// Fecha de la visita, para que el inicio de los tratamientos la siga.
const fechaHora = ref(props.visita?.fecha_hora_local ?? props.ahora);
const diaDeLaVisita = computed(() => fechaHora.value.slice(0, 10));

function agregarMedicamento() {
    bloques.value.push(siguiente++);
}

function quitarMedicamento(clave: number) {
    bloques.value = bloques.value.filter((b) => b !== clave);
}

/** Los errores de `tratamientos.0.dosis` para el bloque 0. */
function erroresDelBloque(
    errors: Record<string, string>,
    indice: number,
): Record<string, string> {
    const prefijo = `tratamientos.${indice}.`;

    return Object.fromEntries(
        Object.entries(errors)
            .filter(([clave]) => clave.startsWith(prefijo))
            .map(([clave, mensaje]) => [clave.slice(prefijo.length), mensaje]),
    );
}
</script>

<template>
    <!-- POST siempre: los archivos no viajan en un PUT multipart en PHP. -->
    <Form
        :action="action"
        method="post"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <input v-if="visita" type="hidden" name="_method" value="put" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="fecha_hora">Cuándo fue *</Label>
                <Input
                    id="fecha_hora"
                    name="fecha_hora"
                    type="datetime-local"
                    required
                    class="touch-target"
                    :default-value="fechaHora"
                    @input="
                        fechaHora = ($event.target as HTMLInputElement).value
                    "
                />
                <InputError :message="errors.fecha_hora" />
            </div>

            <div class="grid gap-2">
                <Label for="tipo">Tipo de visita *</Label>
                <SelectNativo
                    name="tipo"
                    :opciones="tiposVisita"
                    :default-value="visita?.tipo ?? 'rutina'"
                />
                <InputError :message="errors.tipo" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="motivo">Motivo</Label>
            <Input
                id="motivo"
                name="motivo"
                maxlength="255"
                autocomplete="off"
                placeholder="Gastroenteritis"
                :default-value="visita?.motivo ?? ''"
            />
            <InputError :message="errors.motivo" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label>Veterinaria</Label>
                <ComboboxCatalogo
                    name="veterinaria_id"
                    etiqueta="veterinaria"
                    :opciones="veterinarias"
                    :model-value="visita?.veterinaria_id ?? null"
                    :url-crear="storeVeterinaria().url"
                />
                <InputError :message="errors.veterinaria_id" />
            </div>

            <div class="grid gap-2">
                <Label>Veterinario</Label>
                <ComboboxCatalogo
                    name="veterinario_id"
                    etiqueta="veterinario"
                    :opciones="veterinarios"
                    :model-value="visita?.veterinario_id ?? null"
                    :url-crear="storeVeterinario().url"
                />
                <InputError :message="errors.veterinario_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="diagnostico">Diagnóstico</Label>
            <TextareaNativo
                name="diagnostico"
                :rows="3"
                placeholder="Lo que dijo el veterinario"
                :default-value="visita?.diagnostico"
            />
            <InputError :message="errors.diagnostico" />
        </div>

        <div class="grid gap-2">
            <Label for="indicaciones">Indicaciones</Label>
            <TextareaNativo
                name="indicaciones"
                :rows="3"
                placeholder="Dieta blanda por 3 días, volver si sigue con vómitos"
                :default-value="visita?.indicaciones"
            />
            <InputError :message="errors.indicaciones" />
        </div>

        <!-- Medicación: el corazón del criterio de aceptación -->
        <section v-if="conTratamientos" class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-medium">Medicación</h2>
                    <p class="text-sm text-muted-foreground">
                        Lo que le recetaron en esta visita.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="agregarMedicamento"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Agregar
                </Button>
            </div>

            <CamposTratamiento
                v-for="(clave, indice) in bloques"
                :key="clave"
                :prefijo="`tratamientos[${indice}]`"
                :medicamentos="medicamentos"
                :vias="vias"
                :errores="erroresDelBloque(errors, indice)"
                :hoy="diaDeLaVisita"
                :quitable="bloques.length > 0"
                @quitar="quitarMedicamento(clave)"
            />
        </section>

        <!-- Recetas y estudios -->
        <section v-if="conTratamientos" class="flex flex-col gap-3">
            <div>
                <h2 class="font-medium">Recetas y estudios</h2>
                <p class="text-sm text-muted-foreground">
                    Sacale una foto a la receta antes de guardarla en un cajón.
                </p>
            </div>

            <div class="grid gap-2 sm:max-w-xs">
                <Label for="tipo_adjunto">Qué son</Label>
                <SelectNativo
                    name="tipo_adjunto"
                    :opciones="[
                        { value: 'receta', label: 'Receta' },
                        { value: 'analisis', label: 'Análisis' },
                        { value: 'radiografia', label: 'Radiografía' },
                        { value: 'ecografia', label: 'Ecografía' },
                        { value: 'factura', label: 'Factura' },
                        { value: 'otro', label: 'Otro' },
                    ]"
                    default-value="receta"
                />
            </div>

            <CampoArchivos name="adjuntos" />
            <InputError :message="errors.adjuntos" />
            <InputError :message="errors['adjuntos.0']" />
        </section>

        <!-- Lo que casi nunca se carga en el momento -->
        <Collapsible>
            <CollapsibleTrigger
                class="flex touch-target w-full items-center justify-between rounded-md border border-border px-4 py-3 text-sm font-medium"
            >
                Más datos
                <ChevronDown class="size-4" aria-hidden="true" />
            </CollapsibleTrigger>
            <CollapsibleContent class="flex flex-col gap-4 pt-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="temperatura">Temperatura (°C)</Label>
                        <Input
                            id="temperatura"
                            name="temperatura"
                            type="number"
                            inputmode="decimal"
                            step="0.1"
                            min="20"
                            max="45"
                            placeholder="38.5"
                            :default-value="visita?.temperatura ?? ''"
                        />
                        <InputError :message="errors.temperatura" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="costo">Costo</Label>
                        <Input
                            id="costo"
                            name="costo"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0"
                            :default-value="visita?.costo ?? ''"
                        />
                        <InputError :message="errors.costo" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="proximo_control">Próximo control</Label>
                    <Input
                        id="proximo_control"
                        name="proximo_control"
                        type="date"
                        class="touch-target"
                        :default-value="visita?.proximo_control ?? ''"
                    />
                    <InputError :message="errors.proximo_control" />
                </div>

                <div class="grid gap-2">
                    <Label for="notas">Notas</Label>
                    <TextareaNativo
                        name="notas"
                        :rows="3"
                        :default-value="visita?.notas"
                    />
                    <InputError :message="errors.notas" />
                </div>
            </CollapsibleContent>
        </Collapsible>

        <Button
            type="submit"
            size="lg"
            class="touch-target w-full"
            :disabled="processing"
        >
            <Spinner v-if="processing" class="size-4" />
            {{ textoEnviar }}
        </Button>
    </Form>
</template>
