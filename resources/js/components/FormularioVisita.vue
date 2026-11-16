<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ChevronDown, Plus } from '@lucide/vue';
import dayjs from 'dayjs';
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import { toast } from 'vue-sonner';
import CampoArchivos from '@/components/CampoArchivos.vue';
import CamposTratamiento from '@/components/CamposTratamiento.vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import DialogoSalida from '@/components/DialogoSalida.vue';
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
import { useSalidaProtegida } from '@/composables/useSalidaProtegida';
import { borrarBorrador, leerBorrador } from '@/lib/borradores';
import type { Borrador } from '@/lib/borradores';
import { store as storeVeterinaria } from '@/routes/catalogos/veterinarias';
import { store as storeVeterinario } from '@/routes/catalogos/veterinarios';
import type {
    Medicamento,
    OpcionEnum,
    Tratamiento,
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
 *
 * Irse a medio cargar pregunta antes, y ofrece dejarlo como borrador en el
 * teléfono (`useSalidaProtegida`). El borrador vuelve como valores iniciales de
 * esos mismos inputs: no hay un segundo estado del formulario que mantener.
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
        /** Solo hace falta en el alta: en la edición esta sección no se ve. */
        tiposAdjunto?: OpcionEnum[];
        textoEnviar: string;
        /** En la edición los tratamientos se manejan desde la ficha. */
        conTratamientos?: boolean;
        /** Dónde se guarda el borrador: uno por mascota y por visita (o "nueva"). */
        claveBorrador: string;
        /** Adónde va "salir" si el formulario fue la primera pantalla abierta. */
        salida: string;
    }>(),
    { visita: undefined, tiposAdjunto: () => [], conTratamientos: true },
);

let borradorPrevio: Borrador | null = leerBorrador(props.claveBorrador);

/** Lo del borrador, por `name`: `motivo`, `tratamientos[0][dosis]`. */
const delBorrador = ref<Record<string, string>>(
    Object.fromEntries(borradorPrevio?.campos ?? []),
);

/** Valor inicial de un campo: el del borrador si hay, si no el de siempre. */
function inicial<T>(nombre: string, deSiempre: T): string | T {
    return delBorrador.value[nombre] ?? deSiempre;
}

function idInicial(nombre: string, deSiempre: number | null | undefined) {
    const valor = delBorrador.value[nombre];

    return valor === undefined ? (deSiempre ?? null) : Number(valor) || null;
}

/*
 * Los medicamentos del borrador, uno por índice. La fecha de inicio no se
 * trae: sigue a la de la visita, que puede cambiar al retomar.
 */
const tratamientosDelBorrador = computed(() => {
    const porIndice: Partial<Tratamiento>[] = [];

    for (const [nombre, valor] of Object.entries(delBorrador.value)) {
        const partes = nombre.match(/^tratamientos\[(\d+)]\[(\w+)]$/);

        if (!partes || partes[2] === 'fecha_inicio') {
            continue;
        }

        const campo = partes[2];
        const indice = Number(partes[1]);

        porIndice[indice] = {
            ...porIndice[indice],
            [campo]: campo === 'medicamento_id' ? Number(valor) || null : valor,
        };
    }

    return porIndice;
});

// Cuántos bloques de medicamento hay en pantalla. Solo se guarda la clave de
// cada bloque: los valores los lleva el formulario nativo.
let siguiente = 0;
const bloques = ref<number[]>(
    tratamientosDelBorrador.value.map(() => siguiente++),
);

// Fecha de la visita, para que el inicio de los tratamientos la siga.
const fechaHora = ref(
    inicial('fecha_hora', props.visita?.fecha_hora_local ?? props.ahora),
);
const diaDeLaVisita = computed(() => fechaHora.value.slice(0, 10));

/** Con algo cargado ahí, «Más datos» arranca abierto: si no, no se ve que vino. */
const masDatosEnElBorrador = [
    'temperatura',
    'costo',
    'proximo_control',
    'notas',
].some((nombre) => (delBorrador.value[nombre] ?? '').trim() !== '');

/** Cambia al descartar un borrador: rearma el formulario con los valores de siempre. */
const version = ref(0);

const contenedor = useTemplateRef<HTMLElement>('contenedor');

const salida = useSalidaProtegida({
    contenedor,
    clave: () => props.claveBorrador,
    borradorPrevio: () => borradorPrevio,
    salidaSinHistorial: () => props.salida,
});

const { preguntando, conArchivos, guardia } = salida;

function descartarBorrador() {
    borrarBorrador(props.claveBorrador);
    borradorPrevio = null;
    delBorrador.value = {};
    bloques.value = [];
    fechaHora.value = props.visita?.fecha_hora_local ?? props.ahora;
    version.value++;
    salida.tomarPuntoDePartida();
}

onMounted(() => {
    if (!borradorPrevio) {
        return;
    }

    const cuando = dayjs(borradorPrevio.guardado).format('D/M [a las] HH:mm');

    toast.info(`Retomaste el borrador del ${cuando}.`, {
        duration: 10_000,
        action: { label: 'Descartar', onClick: descartarBorrador },
    });
});

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
    <div ref="contenedor">
        <!-- POST siempre: los archivos no viajan en un PUT multipart en PHP. -->
        <Form
            :key="version"
            :action="action"
            method="post"
            class="flex flex-col gap-6"
            :options="{ replace: guardia }"
            @success="salida.alGuardar"
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
                            fechaHora = ($event.target as HTMLInputElement)
                                .value
                        "
                    />
                    <InputError :message="errors.fecha_hora" />
                </div>

                <div class="grid gap-2">
                    <Label for="tipo">Tipo de visita *</Label>
                    <SelectNativo
                        name="tipo"
                        :opciones="tiposVisita"
                        :default-value="
                            inicial('tipo', visita?.tipo ?? 'rutina')
                        "
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
                    :default-value="inicial('motivo', visita?.motivo ?? '')"
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
                        :model-value="
                            idInicial('veterinaria_id', visita?.veterinaria_id)
                        "
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
                        :model-value="
                            idInicial('veterinario_id', visita?.veterinario_id)
                        "
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
                    :default-value="inicial('diagnostico', visita?.diagnostico)"
                />
                <InputError :message="errors.diagnostico" />
            </div>

            <div class="grid gap-2">
                <Label for="indicaciones">Indicaciones</Label>
                <TextareaNativo
                    name="indicaciones"
                    :rows="3"
                    placeholder="Dieta blanda por 3 días, volver si sigue con vómitos"
                    :default-value="
                        inicial('indicaciones', visita?.indicaciones)
                    "
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
                    :tratamiento="tratamientosDelBorrador[indice]"
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
                        Sacale una foto a la receta antes de guardarla en un
                        cajón.
                    </p>
                </div>

                <div class="grid gap-2 sm:max-w-xs">
                    <Label for="tipo_adjunto">Qué son</Label>
                    <SelectNativo
                        name="tipo_adjunto"
                        :opciones="tiposAdjunto"
                        :default-value="inicial('tipo_adjunto', 'receta')"
                    />
                </div>

                <CampoArchivos name="adjuntos" />
                <InputError :message="errors.adjuntos" />
                <InputError :message="errors['adjuntos.0']" />
            </section>

            <!-- Lo que casi nunca se carga en el momento -->
            <!--
            Sin desmontar al plegar: plegado, lo escrito adentro se perdía, y
            tampoco entraba en el borrador.
        -->
            <Collapsible
                :unmount-on-hide="false"
                :default-open="masDatosEnElBorrador"
            >
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
                                :default-value="
                                    inicial(
                                        'temperatura',
                                        visita?.temperatura ?? '',
                                    )
                                "
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
                                :default-value="
                                    inicial('costo', visita?.costo ?? '')
                                "
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
                            :default-value="
                                inicial(
                                    'proximo_control',
                                    visita?.proximo_control ?? '',
                                )
                            "
                        />
                        <InputError :message="errors.proximo_control" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notas">Notas</Label>
                        <TextareaNativo
                            name="notas"
                            :rows="3"
                            :default-value="inicial('notas', visita?.notas)"
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

        <DialogoSalida
            :abierto="preguntando"
            que-se-carga="la visita"
            :con-archivos="conArchivos"
            @borrador="salida.guardarYSalir"
            @salir="salida.salirSinGuardar"
            @seguir="salida.seguirCargando"
        />
    </div>
</template>
