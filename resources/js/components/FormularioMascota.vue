<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import CampoFoto from '@/components/CampoFoto.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Mascota, OpcionEnum } from '@/types/huella';

/*
 * Formulario de alta y edición de la ficha. El criterio de la fase es que el
 * alta lleve menos de un minuto desde el celular: lo esencial (nombre, especie,
 * sexo, foto) va arriba y todo lo demás queda plegado en secciones opcionales.
 */

const props = defineProps<{
    /** URL del action; con mascota, edita (method spoofing a PUT). */
    action: string;
    mascota?: Mascota;
    especies: OpcionEnum[];
    sexos: OpcionEnum[];
    tiposPelaje: OpcionEnum[];
    textoEnviar: string;
}>();

const masDetalles = ref(false);
const castrado = ref(props.mascota?.castrado ?? false);
const nacimientoEstimado = ref(
    props.mascota?.fecha_nacimiento_estimada ?? false,
);
</script>

<template>
    <!--
        POST siempre: los archivos no viajan en un PUT multipart en PHP.
        Para editar va el _method oculto (method spoofing estándar de Laravel).
    -->
    <Form
        :action="action"
        method="post"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <input v-if="mascota" type="hidden" name="_method" value="put" />

        <div class="grid gap-2">
            <Label>Foto</Label>
            <CampoFoto name="foto">
                <template v-if="mascota?.foto_miniatura_url" #placeholder>
                    <img
                        :src="mascota.foto_miniatura_url"
                        alt="Foto actual"
                        class="size-full object-cover"
                    />
                </template>
            </CampoFoto>
            <InputError :message="errors.foto" />
        </div>

        <div class="grid gap-2">
            <Label for="nombre">Nombre *</Label>
            <Input
                id="nombre"
                name="nombre"
                required
                autocomplete="off"
                maxlength="80"
                :default-value="mascota?.nombre"
                placeholder="Greta"
            />
            <InputError :message="errors.nombre" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="especie">Especie *</Label>
                <SelectNativo
                    name="especie"
                    :opciones="especies"
                    :default-value="mascota?.especie ?? 'perro'"
                />
                <InputError :message="errors.especie" />
            </div>

            <div class="grid gap-2">
                <Label for="sexo">Sexo *</Label>
                <SelectNativo
                    name="sexo"
                    :opciones="sexos"
                    :default-value="mascota?.sexo ?? 'desconocido'"
                />
                <InputError :message="errors.sexo" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="raza">Raza</Label>
            <Input
                id="raza"
                name="raza"
                maxlength="120"
                :default-value="mascota?.raza ?? ''"
                placeholder="Mestizo"
            />
            <InputError :message="errors.raza" />
        </div>

        <div class="grid gap-2">
            <Label for="fecha_nacimiento">Fecha de nacimiento</Label>
            <Input
                id="fecha_nacimiento"
                type="date"
                name="fecha_nacimiento"
                :default-value="mascota?.fecha_nacimiento ?? ''"
            />
            <InputError :message="errors.fecha_nacimiento" />

            <!-- El checkbox de reka-ui no viaja en el form nativo: input espejo. -->
            <input
                type="hidden"
                name="fecha_nacimiento_estimada"
                :value="nacimientoEstimado ? 1 : 0"
            />
            <Label
                class="flex items-center gap-2 text-sm font-normal text-muted-foreground"
            >
                <Checkbox
                    :model-value="nacimientoEstimado"
                    @update:model-value="nacimientoEstimado = $event === true"
                />
                Es aproximada (típico si es adoptada)
            </Label>
        </div>

        <div class="grid gap-2">
            <input type="hidden" name="castrado" :value="castrado ? 1 : 0" />
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox
                    :model-value="castrado"
                    @update:model-value="castrado = $event === true"
                />
                Está castrado/a
            </Label>
            <InputError :message="errors.castrado" />
        </div>

        <div v-if="castrado" class="grid gap-2">
            <Label for="fecha_castracion">Fecha de castración</Label>
            <Input
                id="fecha_castracion"
                type="date"
                name="fecha_castracion"
                :default-value="mascota?.fecha_castracion ?? ''"
            />
            <InputError :message="errors.fecha_castracion" />
        </div>

        <Collapsible v-model:open="masDetalles">
            <CollapsibleTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    class="w-full justify-between"
                >
                    Más detalles (identificación, seguro, señas)
                    <ChevronDown
                        class="size-4 transition-transform"
                        :class="{ 'rotate-180': masDetalles }"
                        aria-hidden="true"
                    />
                </Button>
            </CollapsibleTrigger>

            <CollapsibleContent class="flex flex-col gap-6 pt-4">
                <div class="grid gap-2">
                    <Label for="color">Color</Label>
                    <Input
                        id="color"
                        name="color"
                        maxlength="80"
                        :default-value="mascota?.color ?? ''"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="tipo_pelaje">Pelaje</Label>
                    <SelectNativo
                        name="tipo_pelaje"
                        :opciones="tiposPelaje"
                        placeholder="Sin especificar"
                        :default-value="mascota?.tipo_pelaje"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="fecha_adopcion">Fecha de adopción</Label>
                    <Input
                        id="fecha_adopcion"
                        type="date"
                        name="fecha_adopcion"
                        :default-value="mascota?.fecha_adopcion ?? ''"
                    />
                    <InputError :message="errors.fecha_adopcion" />
                </div>

                <div class="grid gap-2">
                    <Label for="microchip">Microchip</Label>
                    <Input
                        id="microchip"
                        name="microchip"
                        maxlength="40"
                        :default-value="mascota?.microchip ?? ''"
                    />
                    <InputError :message="errors.microchip" />
                </div>

                <div class="grid gap-2">
                    <Label for="fecha_microchip"
                        >Fecha de implantación del chip</Label
                    >
                    <Input
                        id="fecha_microchip"
                        type="date"
                        name="fecha_microchip"
                        :default-value="mascota?.fecha_microchip ?? ''"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="libreta_sanitaria">Libreta sanitaria</Label>
                        <Input
                            id="libreta_sanitaria"
                            name="libreta_sanitaria"
                            maxlength="60"
                            :default-value="mascota?.libreta_sanitaria ?? ''"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pedigree">Pedigree</Label>
                        <Input
                            id="pedigree"
                            name="pedigree"
                            maxlength="60"
                            :default-value="mascota?.pedigree ?? ''"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="senias_particulares">Señas particulares</Label>
                    <textarea
                        id="senias_particulares"
                        name="senias_particulares"
                        rows="2"
                        maxlength="2000"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-input/30"
                        :value="mascota?.senias_particulares ?? ''"
                    ></textarea>
                </div>

                <div class="grid gap-2">
                    <Label for="descripcion">Descripción</Label>
                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="3"
                        maxlength="2000"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:bg-input/30"
                        :value="mascota?.descripcion ?? ''"
                    ></textarea>
                </div>

                <fieldset
                    class="grid gap-4 rounded-lg border border-border p-4"
                >
                    <legend class="px-1 text-sm font-medium">Seguro</legend>
                    <div class="grid gap-2">
                        <Label for="seguro_compania">Compañía</Label>
                        <Input
                            id="seguro_compania"
                            name="seguro_compania"
                            maxlength="120"
                            :default-value="mascota?.seguro_compania ?? ''"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="seguro_poliza">Póliza</Label>
                            <Input
                                id="seguro_poliza"
                                name="seguro_poliza"
                                maxlength="80"
                                :default-value="mascota?.seguro_poliza ?? ''"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="seguro_vencimiento">Vencimiento</Label>
                            <Input
                                id="seguro_vencimiento"
                                type="date"
                                name="seguro_vencimiento"
                                :default-value="
                                    mascota?.seguro_vencimiento ?? ''
                                "
                            />
                        </div>
                    </div>
                </fieldset>

                <div v-if="mascota" class="grid gap-2">
                    <Label for="fecha_fallecimiento"
                        >Fecha de fallecimiento</Label
                    >
                    <Input
                        id="fecha_fallecimiento"
                        type="date"
                        name="fecha_fallecimiento"
                        :default-value="mascota?.fecha_fallecimiento ?? ''"
                    />
                    <p class="text-xs text-muted-foreground">
                        Al cargarla, la ficha pasa a modo lectura: se conserva
                        todo el historial pero no se registran eventos nuevos.
                    </p>
                    <InputError :message="errors.fecha_fallecimiento" />
                </div>
            </CollapsibleContent>
        </Collapsible>

        <Button
            type="submit"
            class="touch-target w-full"
            :disabled="processing"
        >
            <Spinner v-if="processing" />
            {{ textoEnviar }}
        </Button>
    </Form>
</template>
