<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Bug, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
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
import {
    destroy as destroyDesparasitacion,
    store as storeDesparasitacion,
} from '@/routes/mascotas/desparasitaciones';
import type {
    Desparasitacion,
    Mascota,
    Medicamento,
    OpcionEnum,
} from '@/types/huella';

/*
 * Las desparasitaciones de la pantalla de Seguimiento. Como las vacunas,
 * vivían en «Preventivo»; generan su recordatorio solas al guardarse.
 */

const props = defineProps<{
    mascota: Mascota;
    desparasitaciones: Desparasitacion[];
    medicamentos: Medicamento[];
    tiposDesparasitacion: OpcionEnum[];
    puedeRegistrar: boolean;
    hoy: string;
}>();

const sheetDesparasitacion = ref(false);

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
    <section id="desparasitaciones" class="flex scroll-mt-20 flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
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
    </section>
</template>
