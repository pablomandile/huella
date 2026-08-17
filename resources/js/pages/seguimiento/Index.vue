<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Droplets,
    Info,
    Plus,
    Scale,
    Trash2,
    TrendingDown,
    TrendingUp,
    UtensilsCrossed,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CampoCheck from '@/components/CampoCheck.vue';
import ComboboxCatalogo from '@/components/ComboboxCatalogo.vue';
import CurvaDePeso from '@/components/CurvaDePeso.vue';
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
import { index as mascotasIndex } from '@/routes/mascotas';
import {
    destroy as destroyCiclo,
    store as storeCiclo,
} from '@/routes/mascotas/celos';
import {
    destroy as destroyDieta,
    store as storeDieta,
} from '@/routes/mascotas/dietas';
import {
    destroy as destroyPeso,
    store as storePeso,
} from '@/routes/mascotas/pesos';
import type {
    Alimento,
    CicloCelo,
    Dieta,
    EstimacionCelo,
    Mascota,
    OpcionEnum,
    RegistroPeso,
    VariacionPeso,
    Veterinario,
} from '@/types/huella';

const props = defineProps<{
    mascota: Mascota;
    pesos: RegistroPeso[];
    variacion: VariacionPeso | null;
    dietas: Dieta[];
    celoVisible: boolean;
    ciclos: CicloCelo[];
    estimacionCelo: EstimacionCelo | null;
    puedeRegistrar: boolean;
    alimentos: Alimento[];
    veterinarios: Veterinario[];
    origenesPeso: OpcionEnum[];
    intensidades: OpcionEnum[];
    hoy: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: mascotasIndex() }],
    },
});

const sheetPeso = ref(false);
const sheetDieta = ref(false);
const sheetCelo = ref(false);

const ultimoPeso = computed(() => props.pesos[props.pesos.length - 1] ?? null);
const dietaVigente = computed(
    () => props.dietas.find((d) => d.vigente) ?? null,
);
const dietasAnteriores = computed(() => props.dietas.filter((d) => !d.vigente));

// La curva se lee de más viejo a más nuevo; la lista, al revés.
const pesosRecientes = computed(() => [...props.pesos].reverse().slice(0, 8));

/** El color del cartel de confianza de la estimación de celo. */
const colorConfianza: Record<EstimacionCelo['confianza'], string> = {
    media: 'border-primary/40 bg-primary/5',
    baja: 'border-amber-500/40 bg-amber-500/5',
    muy_baja: 'border-border bg-muted/40',
};

function eliminarPeso(peso: RegistroPeso) {
    if (confirm(`¿Eliminar el peso del ${peso.fecha_legible}?`)) {
        router.delete(destroyPeso([props.mascota.id, peso.id]).url, {
            preserveScroll: true,
        });
    }
}

function eliminarDieta(dieta: Dieta) {
    if (confirm('¿Eliminar esta dieta del historial?')) {
        router.delete(destroyDieta([props.mascota.id, dieta.id]).url, {
            preserveScroll: true,
        });
    }
}

function eliminarCiclo(ciclo: CicloCelo) {
    if (confirm(`¿Eliminar el ciclo del ${ciclo.fecha_inicio_legible}?`)) {
        router.delete(destroyCiclo([props.mascota.id, ciclo.id]).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="`Seguimiento de ${mascota.nombre}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Seguimiento</h1>
            <p class="text-sm text-muted-foreground">{{ mascota.nombre }}</p>
        </div>

        <!-- Peso -->
        <section class="flex flex-col gap-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-medium">Peso</h2>
                    <p v-if="ultimoPeso" class="text-sm">
                        <span class="text-lg font-semibold">
                            {{ ultimoPeso.peso_legible }}
                        </span>
                        <span class="text-muted-foreground">
                            · {{ ultimoPeso.fecha_legible }}
                        </span>
                    </p>
                    <p
                        v-if="variacion"
                        class="inline-flex items-center gap-1 text-sm"
                        :class="
                            variacion.kilos === 0
                                ? 'text-muted-foreground'
                                : variacion.sube
                                  ? 'text-amber-600 dark:text-amber-500'
                                  : 'text-primary'
                        "
                    >
                        <TrendingUp
                            v-if="variacion.sube && variacion.kilos !== 0"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <TrendingDown
                            v-else-if="variacion.kilos !== 0"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{ variacion.texto }}
                    </p>
                </div>
                <Button
                    v-if="puedeRegistrar"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="sheetPeso = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Pesar
                </Button>
            </div>

            <Card v-if="pesos.length" class="py-4">
                <CardContent>
                    <CurvaDePeso :pesos="pesos" />
                </CardContent>
            </Card>

            <p
                v-else
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Todavía no cargaste ningún peso. Con dos ya se ve la curva.
            </p>

            <!--
                La lista, además del gráfico: con lector de pantalla o con dos
                puntos encimados, el gráfico solo no alcanza.
            -->
            <ul
                v-if="pesos.length"
                class="flex flex-col divide-y divide-border"
            >
                <li
                    v-for="peso in pesosRecientes"
                    :key="peso.id"
                    class="flex items-center gap-3 py-2"
                >
                    <Scale
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="w-20 shrink-0 font-medium">
                        {{ peso.peso_legible }}
                    </span>
                    <span
                        class="min-w-0 flex-1 truncate text-sm text-muted-foreground"
                    >
                        {{ peso.fecha_legible }}
                        <template v-if="peso.en_veterinaria">
                            · {{ peso.origen_etiqueta }}</template
                        >
                    </span>
                    <button
                        v-if="puedeRegistrar"
                        type="button"
                        class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                        :aria-label="`Eliminar el peso del ${peso.fecha_legible}`"
                        @click="eliminarPeso(peso)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </li>
            </ul>
        </section>

        <!-- Dieta -->
        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-medium">Alimentación</h2>
                <Button
                    v-if="puedeRegistrar"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="sheetDieta = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Cambiar
                </Button>
            </div>

            <Card v-if="dietaVigente" class="border-primary/40 py-0">
                <CardContent class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                    >
                        <UtensilsCrossed
                            class="size-4 text-accent-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ dietaVigente.alimento }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ dietaVigente.periodo }}
                        </p>
                        <p
                            v-if="dietaVigente.racion_legible"
                            class="text-sm text-muted-foreground"
                        >
                            {{ dietaVigente.racion_legible }}
                        </p>
                        <p
                            v-if="dietaVigente.motivo"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ dietaVigente.motivo }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Badge variant="secondary" class="font-normal">
                                Come esto ahora
                            </Badge>
                            <Badge
                                v-if="dietaVigente.prescripta"
                                variant="outline"
                                class="font-normal"
                            >
                                Prescripta
                            </Badge>
                            <Badge
                                v-if="dietaVigente.alimento_medicado"
                                variant="outline"
                                class="font-normal"
                            >
                                Medicado
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <p
                v-else
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Todavía no cargaste qué come.
            </p>

            <details v-if="dietasAnteriores.length" class="text-sm">
                <summary
                    class="flex touch-target cursor-pointer items-center text-muted-foreground"
                >
                    Antes comía ({{ dietasAnteriores.length }})
                </summary>
                <ul class="mt-2 flex flex-col divide-y divide-border">
                    <li
                        v-for="dieta in dietasAnteriores"
                        :key="dieta.id"
                        class="flex items-center gap-3 py-2"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate">{{ dieta.alimento }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ dieta.periodo }}
                            </p>
                        </div>
                        <button
                            v-if="puedeRegistrar"
                            type="button"
                            class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                            aria-label="Eliminar del historial"
                            @click="eliminarDieta(dieta)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </details>
        </section>

        <!-- Celo: solo hembras no castradas y vivas (regla de negocio 2) -->
        <section v-if="celoVisible" class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-medium">Celo</h2>
                <Button
                    v-if="puedeRegistrar"
                    variant="outline"
                    size="sm"
                    class="touch-target shrink-0"
                    @click="sheetCelo = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Registrar
                </Button>
            </div>

            <!--
                La estimación va siempre con su nivel de confianza: una fecha
                sola se lee como un dato, y esto es un promedio.
            -->
            <Card
                v-if="estimacionCelo"
                :class="
                    estimacionCelo.vencida
                        ? 'border-destructive/40 bg-destructive/5'
                        : colorConfianza[estimacionCelo.confianza]
                "
            >
                <CardContent class="flex items-start gap-3">
                    <Info class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <div class="min-w-0">
                        <p class="font-medium">
                            <template v-if="!estimacionCelo.fecha_legible">
                                Todavía no se puede estimar
                            </template>
                            <!--
                                Una fecha ya pasada no se anuncia como "el
                                próximo": pasa cuando el celo ocurrió y no se
                                cargó, y decirlo así sería información falsa.
                            -->
                            <template v-else-if="estimacionCelo.vencida">
                                Se estimaba para el
                                {{ estimacionCelo.fecha_legible }}
                            </template>
                            <template v-else>
                                Se estima el próximo para el
                                {{ estimacionCelo.fecha_legible }}
                            </template>
                        </p>
                        <p
                            class="mt-0.5 text-sm font-medium text-muted-foreground"
                        >
                            {{ estimacionCelo.confianza_etiqueta }}
                        </p>
                        <p
                            class="mt-1 text-sm text-pretty text-muted-foreground"
                        >
                            {{ estimacionCelo.detalle }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card v-for="ciclo in ciclos" :key="ciclo.id" class="py-0">
                <CardContent class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                    >
                        <Droplets
                            class="size-4 text-accent-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ ciclo.fecha_inicio_legible }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="ciclo.en_curso">En curso</template>
                            <template v-else-if="ciclo.duracion_dias">
                                Duró {{ ciclo.duracion_dias }} días
                            </template>
                        </p>
                        <p
                            v-if="ciclo.sintomas"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ ciclo.sintomas }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-if="ciclo.intensidad_etiqueta"
                                variant="outline"
                                class="font-normal"
                            >
                                {{ ciclo.intensidad_etiqueta }}
                            </Badge>
                            <Badge
                                v-if="ciclo.hubo_monta"
                                variant="secondary"
                                class="font-normal"
                            >
                                Hubo monta
                            </Badge>
                        </div>
                    </div>
                    <button
                        v-if="puedeRegistrar"
                        type="button"
                        class="flex touch-target shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                        :aria-label="`Eliminar el ciclo del ${ciclo.fecha_inicio_legible}`"
                        @click="eliminarCiclo(ciclo)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </button>
                </CardContent>
            </Card>

            <p
                v-if="!ciclos.length"
                class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Todavía no cargaste ningún ciclo.
            </p>
        </section>

        <!-- Alta de peso -->
        <Sheet v-model:open="sheetPeso">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Registrar el peso</SheetTitle>
                    <SheetDescription>
                        Anotá con qué balanza fue: la de la veterinaria y la de
                        casa no coinciden.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storePeso(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetPeso = false"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="peso_kg">Peso en kg *</Label>
                            <Input
                                id="peso_kg"
                                name="peso_kg"
                                type="number"
                                inputmode="decimal"
                                step="0.01"
                                min="0.05"
                                max="200"
                                required
                                autofocus
                                class="touch-target"
                                placeholder="18.4"
                            />
                            <InputError :message="errors.peso_kg" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="fecha">Cuándo *</Label>
                            <Input
                                id="fecha"
                                name="fecha"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="hoy"
                            />
                            <InputError :message="errors.fecha" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="origen">Dónde lo pesaste *</Label>
                        <SelectNativo
                            name="origen"
                            :opciones="origenesPeso"
                            default-value="casa"
                        />
                        <InputError :message="errors.origen" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="condicion_corporal">
                            Condición corporal
                        </Label>
                        <Input
                            id="condicion_corporal"
                            name="condicion_corporal"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="9"
                        />
                        <p class="text-xs text-muted-foreground">
                            Escala del 1 al 9, si te la dijeron en la
                            veterinaria.
                        </p>
                        <InputError :message="errors.condicion_corporal" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetPeso = false"
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

        <!-- Cambio de dieta -->
        <Sheet v-model:open="sheetDieta">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Cambiar la alimentación</SheetTitle>
                    <SheetDescription>
                        La dieta anterior se cierra sola el día antes de que
                        empiece esta.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storeDieta(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetDieta = false"
                >
                    <div class="grid gap-2">
                        <Label>Qué va a comer *</Label>
                        <ComboboxCatalogo
                            name="alimento_id"
                            etiqueta="alimento"
                            :opciones="alimentos"
                            :permite-vacio="false"
                        />
                        <InputError :message="errors.alimento_id" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fecha_inicio">Desde cuándo *</Label>
                            <Input
                                id="fecha_inicio"
                                name="fecha_inicio"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="hoy"
                            />
                            <InputError :message="errors.fecha_inicio" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="racion_diaria_g"
                                >Ración por día (g)</Label
                            >
                            <Input
                                id="racion_diaria_g"
                                name="racion_diaria_g"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                max="5000"
                                placeholder="300"
                            />
                            <InputError :message="errors.racion_diaria_g" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="tomas_por_dia">Tomas por día</Label>
                        <Input
                            id="tomas_por_dia"
                            name="tomas_por_dia"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="10"
                            placeholder="2"
                        />
                        <InputError :message="errors.tomas_por_dia" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="motivo">Por qué el cambio</Label>
                        <Input
                            id="motivo"
                            name="motivo"
                            maxlength="255"
                            placeholder="Dieta renal post gastroenteritis"
                        />
                        <InputError :message="errors.motivo" />
                    </div>

                    <CampoCheck
                        name="prescripta"
                        label="La indicó un veterinario"
                    />

                    <div class="grid gap-2">
                        <Label>Quién la indicó</Label>
                        <ComboboxCatalogo
                            name="veterinario_id"
                            etiqueta="veterinario"
                            :opciones="veterinarios"
                        />
                        <InputError :message="errors.veterinario_id" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetDieta = false"
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

        <!-- Alta de ciclo de celo -->
        <Sheet v-model:open="sheetCelo">
            <SheetContent
                side="bottom"
                class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Registrar un ciclo</SheetTitle>
                    <SheetDescription>
                        Con dos ciclos cargados, la estimación del próximo
                        empieza a usar sus propios intervalos.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :action="storeCiclo(mascota.id).url"
                    method="post"
                    class="flex flex-col gap-4 p-4"
                    v-slot="{ errors, processing }"
                    @success="sheetCelo = false"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fecha_inicio_celo"
                                >Cuándo empezó *</Label
                            >
                            <Input
                                id="fecha_inicio_celo"
                                name="fecha_inicio"
                                type="date"
                                required
                                class="touch-target"
                                :default-value="hoy"
                            />
                            <InputError :message="errors.fecha_inicio" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="fecha_fin_celo">Cuándo terminó</Label>
                            <Input
                                id="fecha_fin_celo"
                                name="fecha_fin"
                                type="date"
                                class="touch-target"
                            />
                            <p class="text-xs text-muted-foreground">
                                Dejalo vacío si sigue en curso.
                            </p>
                            <InputError :message="errors.fecha_fin" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="intensidad">Intensidad</Label>
                        <SelectNativo
                            name="intensidad"
                            :opciones="intensidades"
                            placeholder="Sin especificar"
                        />
                        <InputError :message="errors.intensidad" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="sintomas">Qué observaste</Label>
                        <TextareaNativo
                            name="sintomas"
                            :rows="2"
                            placeholder="Sangrado leve, más inquieta"
                        />
                        <InputError :message="errors.sintomas" />
                    </div>

                    <CampoCheck name="hubo_monta" label="Hubo monta" />

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            class="touch-target"
                            @click="sheetCelo = false"
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
