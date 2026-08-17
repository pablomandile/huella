<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Bug, Plus, ShieldCheck, Syringe, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import TextareaNativo from '@/components/TextareaNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { store as storeMedicamento } from '@/routes/catalogos/medicamentos';
import { store as storeVacunaCatalogo } from '@/routes/catalogos/vacunas';
import { index as mascotasIndex } from '@/routes/mascotas';
import {
    destroy as destroyDesparasitacion,
    store as storeDesparasitacion,
} from '@/routes/mascotas/desparasitaciones';
import {
    destroy as destroyVacuna,
    store as storeVacuna,
} from '@/routes/mascotas/vacunas';
import type {
    AplicacionVacuna,
    Desparasitacion,
    EstadoVacunacion,
    Mascota,
    Medicamento,
    OpcionEnum,
    Vacuna,
    Veterinaria,
    Veterinario,
} from '@/types/huella';

/*
 * Vacunas y desparasitaciones juntas: para el usuario son lo mismo —cosas que
 * se aplican cada tanto y hay que volver a dar—, y las dos generan su
 * recordatorio solas al guardarse.
 *
 * La próxima fecha se precarga con los meses de refuerzo del catálogo pero
 * siempre queda editable (regla de negocio 6): cada veterinaria maneja su plan.
 */

const props = defineProps<{
    mascota: Mascota;
    estadoVacunacion: EstadoVacunacion;
    aplicaciones: AplicacionVacuna[];
    desparasitaciones: Desparasitacion[];
    puedeRegistrar: boolean;
    vacunas: Vacuna[];
    medicamentos: Medicamento[];
    veterinarias: Veterinaria[];
    veterinarios: Veterinario[];
    tiposDesparasitacion: OpcionEnum[];
    hoy: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});

const sheetVacuna = ref(false);
const sheetDesparasitacion = ref(false);

// Vacuna elegida del catálogo: sus meses de refuerzo precargan la próxima dosis.
const vacunaElegida = ref<number | null>(null);
const fechaAplicacion = ref(props.hoy);

const proximaSugerida = computed(() => {
    const vacuna = props.vacunas.find((v) => v.id === vacunaElegida.value);

    if (!vacuna?.meses_refuerzo || !fechaAplicacion.value) {
        return '';
    }

    const fecha = new Date(`${fechaAplicacion.value}T00:00:00`);
    fecha.setMonth(fecha.getMonth() + vacuna.meses_refuerzo);

    return fecha.toISOString().slice(0, 10);
});

const colorSemaforo: Record<EstadoVacunacion['estado'], string> = {
    al_dia: 'border-primary/40 bg-primary/5',
    proxima: 'border-amber-500/40 bg-amber-500/5',
    vencida: 'border-destructive/40 bg-destructive/5',
    sin_datos: 'border-border',
};

function eliminarVacuna(aplicacion: AplicacionVacuna) {
    if (confirm(`¿Eliminar la aplicación de ${aplicacion.nombre_vacuna}?`)) {
        router.delete(destroyVacuna([props.mascota.id, aplicacion.id]).url, {
            preserveScroll: true,
        });
    }
}

function eliminarDesparasitacion(desparasitacion: Desparasitacion) {
    if (confirm('¿Eliminar esta desparasitación?')) {
        router.delete(
            destroyDesparasitacion([props.mascota.id, desparasitacion.id]).url,
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="`Preventivo de ${mascota.nombre}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Preventivo</h1>
            <p class="text-sm text-muted-foreground">{{ mascota.nombre }}</p>
        </div>

        <!--
            El semáforo mira solo las fechas cargadas. No decide qué vacunas le
            corresponden a la mascota: eso sería aconsejar, y el sistema registra.
        -->
        <Card :class="colorSemaforo[estadoVacunacion.estado]">
            <CardContent class="flex items-start gap-3">
                <ShieldCheck
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="font-medium">
                        Vacunación: {{ estadoVacunacion.etiqueta }}
                    </p>
                    <p
                        v-if="estadoVacunacion.detalle"
                        class="mt-0.5 text-sm text-muted-foreground"
                    >
                        {{ estadoVacunacion.detalle }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Vacunas -->
        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-medium">Vacunas</h2>
                <Button
                    v-if="puedeRegistrar"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="sheetVacuna = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Registrar
                </Button>
            </div>

            <p
                v-if="!aplicaciones.length"
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Todavía no cargaste ninguna vacuna.
            </p>

            <Card
                v-for="aplicacion in aplicaciones"
                :key="aplicacion.id"
                class="py-0"
            >
                <CardContent class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                    >
                        <Syringe
                            class="size-4 text-accent-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ aplicacion.nombre_vacuna }}
                            <span
                                v-if="aplicacion.dosis_nro"
                                class="text-sm font-normal text-muted-foreground"
                            >
                                · {{ aplicacion.dosis_nro }}ª dosis
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ aplicacion.fecha_legible }}
                        </p>
                        <p
                            v-if="aplicacion.marca || aplicacion.lote"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                [aplicacion.marca, aplicacion.lote]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                        <Badge
                            v-if="aplicacion.proxima_dosis"
                            variant="secondary"
                            class="mt-2 font-normal"
                        >
                            Próxima: {{ aplicacion.proxima_dosis }}
                        </Badge>
                        <p
                            v-if="aplicacion.reacciones"
                            class="mt-2 text-sm text-destructive"
                        >
                            Reacción: {{ aplicacion.reacciones }}
                        </p>
                    </div>
                    <button
                        v-if="puedeRegistrar"
                        type="button"
                        class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                        :aria-label="`Eliminar ${aplicacion.nombre_vacuna}`"
                        @click="eliminarVacuna(aplicacion)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </CardContent>
            </Card>
        </section>

        <!-- Desparasitaciones -->
        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-medium">Desparasitaciones</h2>
                <Button
                    v-if="puedeRegistrar"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="sheetDesparasitacion = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Registrar
                </Button>
            </div>

            <p
                v-if="!desparasitaciones.length"
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Todavía no cargaste ninguna desparasitación.
            </p>

            <Card
                v-for="desparasitacion in desparasitaciones"
                :key="desparasitacion.id"
                class="py-0"
            >
                <CardContent class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                    >
                        <Bug
                            class="size-4 text-accent-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ desparasitacion.nombre_medicamento }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ desparasitacion.fecha_legible }} ·
                            {{ desparasitacion.tipo_etiqueta }}
                        </p>
                        <p
                            v-if="
                                desparasitacion.dosis ||
                                desparasitacion.peso_legible
                            "
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                [
                                    desparasitacion.dosis,
                                    desparasitacion.peso_legible,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                        <Badge
                            v-if="desparasitacion.proxima_fecha"
                            variant="secondary"
                            class="mt-2 font-normal"
                        >
                            Próxima: {{ desparasitacion.proxima_fecha }}
                        </Badge>
                    </div>
                    <button
                        v-if="puedeRegistrar"
                        type="button"
                        class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                        aria-label="Eliminar desparasitación"
                        @click="eliminarDesparasitacion(desparasitacion)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </CardContent>
            </Card>
        </section>

        <!-- Alta de vacuna -->
        <Sheet v-model:open="sheetVacuna">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Registrar una vacuna</SheetTitle>
                    <SheetDescription>
                        Si anotás la próxima dosis, el recordatorio aparece
                        solo.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storeVacuna(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetVacuna = false"
                >
                    <div class="grid gap-2">
                        <Label>Vacuna *</Label>
                        <ComboboxCatalogo
                            v-model="vacunaElegida"
                            name="vacuna_id"
                            etiqueta="vacuna"
                            :opciones="vacunas"
                            :url-crear="storeVacunaCatalogo().url"
                            :extras-crear="{ especie: mascota.especie }"
                        />
                        <Input
                            id="vacuna_libre"
                            name="vacuna_libre"
                            maxlength="120"
                            placeholder="O escribí el nombre"
                        />
                        <InputError :message="errors.vacuna_libre" />
                        <InputError :message="errors.vacuna_id" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fecha">Cuándo se la dieron *</Label>
                            <Input
                                id="fecha"
                                name="fecha"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="fechaAplicacion"
                                @input="
                                    fechaAplicacion = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            />
                            <InputError :message="errors.fecha" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="proxima_dosis">Próxima dosis</Label>
                            <Input
                                :key="proximaSugerida"
                                id="proxima_dosis"
                                name="proxima_dosis"
                                type="date"
                                class="touch-target"
                                :default-value="proximaSugerida"
                            />
                            <p class="text-xs text-muted-foreground">
                                Se sugiere según el catálogo. Cambiala si tu
                                veterinaria maneja otro plan.
                            </p>
                            <InputError :message="errors.proxima_dosis" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="dosis_nro">Nº de dosis</Label>
                            <Input
                                id="dosis_nro"
                                name="dosis_nro"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                max="20"
                            />
                            <InputError :message="errors.dosis_nro" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="marca">Marca</Label>
                            <Input id="marca" name="marca" maxlength="120" />
                            <InputError :message="errors.marca" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="lote">Lote</Label>
                            <Input id="lote" name="lote" maxlength="60" />
                            <InputError :message="errors.lote" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Veterinaria</Label>
                            <ComboboxCatalogo
                                name="veterinaria_id"
                                etiqueta="veterinaria"
                                :opciones="veterinarias"
                            />
                            <InputError :message="errors.veterinaria_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Veterinario</Label>
                            <ComboboxCatalogo
                                name="veterinario_id"
                                etiqueta="veterinario"
                                :opciones="veterinarios"
                            />
                            <InputError :message="errors.veterinario_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="reacciones">Reacciones</Label>
                        <TextareaNativo
                            name="reacciones"
                            :rows="2"
                            placeholder="Quedó dolorida el resto del día"
                        />
                        <InputError :message="errors.reacciones" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetVacuna = false"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            class="touch-target flex-1"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="size-4" />
                            Guardar
                        </Button>
                    </div>
                </Form>
            </SheetContent>
        </Sheet>

        <!-- Alta de desparasitación -->
        <Sheet v-model:open="sheetDesparasitacion">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Registrar una desparasitación</SheetTitle>
                    <SheetDescription>
                        La dosis depende del peso, así que conviene anotarlo.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storeDesparasitacion(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetDesparasitacion = false"
                >
                    <div class="grid gap-2">
                        <Label>Antiparasitario</Label>
                        <ComboboxCatalogo
                            name="medicamento_id"
                            etiqueta="antiparasitario"
                            :opciones="medicamentos"
                            :url-crear="storeMedicamento().url"
                            campo-crear="nombre_comercial"
                            :extras-crear="{
                                categoria: 'antiparasitario_interno',
                            }"
                        />
                        <Input
                            id="medicamento_libre"
                            name="medicamento_libre"
                            maxlength="140"
                            placeholder="O escribí el nombre"
                        />
                        <InputError :message="errors.medicamento_libre" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="tipo">Tipo *</Label>
                        <SelectNativo
                            name="tipo"
                            :opciones="tiposDesparasitacion"
                            default-value="interna"
                        />
                        <InputError :message="errors.tipo" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fecha_d">Cuándo *</Label>
                            <Input
                                id="fecha_d"
                                name="fecha"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="hoy"
                            />
                            <InputError :message="errors.fecha" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="proxima_fecha">Próxima</Label>
                            <Input
                                id="proxima_fecha"
                                name="proxima_fecha"
                                type="date"
                                class="touch-target"
                            />
                            <InputError :message="errors.proxima_fecha" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="dosis_d">Dosis</Label>
                            <Input
                                id="dosis_d"
                                name="dosis"
                                maxlength="80"
                                placeholder="1 comprimido"
                            />
                            <InputError :message="errors.dosis" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="peso_al_momento">Peso (kg)</Label>
                            <Input
                                id="peso_al_momento"
                                name="peso_al_momento"
                                type="number"
                                inputmode="decimal"
                                step="0.01"
                                min="0.1"
                                max="200"
                            />
                            <InputError :message="errors.peso_al_momento" />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetDesparasitacion = false"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            class="touch-target flex-1"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="size-4" />
                            Guardar
                        </Button>
                    </div>
                </Form>
            </SheetContent>
        </Sheet>
    </div>
</template>
