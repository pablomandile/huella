<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    BellRing,
    CalendarClock,
    Check,
    PawPrint,
    Pill,
    Plus,
    Scale,
    UtensilsCrossed,
} from '@lucide/vue';
import { ref } from 'vue';
import FichaMascota from '@/components/FichaMascota.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
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
import { dashboard } from '@/routes';
import { create } from '@/routes/mascotas';
import { store as storePeso } from '@/routes/mascotas/pesos';
import { index as seguimiento } from '@/routes/mascotas/seguimiento';
import { index as medicacion, update as marcarToma } from '@/routes/medicacion';
import { index as recordatoriosIndex } from '@/routes/recordatorios';
import type {
    DatosFichaMascota,
    Dieta,
    Mascota,
    OpcionEnum,
    Recordatorio,
    RegistroPeso,
    TomaDelDia,
    VariacionPeso,
} from '@/types/huella';

/*
 * El dashboard de la especificación §5.
 *
 * El orden de los bloques no es decorativo: arriba la ficha de la mascota hasta
 * sus alergias, después lo que hay que hacer hoy y el estado, y al final el
 * resto de la ficha. Quien abre la app a la mañana quiere
 * saber qué le toca, no cuánto pesa.
 */

defineProps<{
    mascotaActiva: Mascota | null;
    ficha: DatosFichaMascota | null;
    totalMascotas: number;
    tomasDeHoy: TomaDelDia[];
    recordatorios: Recordatorio[];
    ultimoPeso: RegistroPeso | null;
    variacionPeso: VariacionPeso | null;
    dietaVigente: Dieta | null;
    origenesPeso: OpcionEnum[];
    hoy: string;
    puedeRegistrar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
    },
});

const sheetPeso = ref(false);

function marcarDada(toma: TomaDelDia) {
    router.patch(
        marcarToma(toma.id).url,
        { estado: 'administrada' },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Inicio" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <!-- Sin mascotas: el primer paso es crear la ficha -->
        <div
            v-if="!mascotaActiva"
            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-accent"
            >
                <PawPrint
                    class="size-7 text-accent-foreground"
                    aria-hidden="true"
                />
            </div>
            <div>
                <h1 class="font-medium">Bienvenido a Huella</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Empezá creando la ficha de tu mascota: lleva menos de un
                    minuto.
                </p>
            </div>
            <Button as-child class="touch-target">
                <Link :href="create()">
                    <Plus class="size-4" aria-hidden="true" />
                    Crear la primera ficha
                </Link>
            </Button>
        </div>

        <template v-else>
            <!--
                La ficha completa de la mascota activa: la misma que su pantalla.
                Lo de hoy —tomas, lo que hay que agendar, peso y dieta— entra en
                el hueco que deja después de las alergias, que son lo único que
                no puede quedar abajo de nada.

                Los recordatorios de acá son los de todas las mascotas a cargo,
                y la ficha muestra después los de esta sola: a la mañana se
                reparte lo de toda la casa, pero la ficha es de una.
            -->
            <FichaMascota v-if="ficha" v-bind="ficha" plegable ficha-arriba>
                <template #tras-alergias>
                    <!-- 1. Lo que hay que dar hoy -->
                    <Card v-if="tomasDeHoy.length" class="py-0">
                        <CardContent class="flex flex-col gap-3 p-4">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <h2
                                    class="inline-flex items-center gap-2 font-medium"
                                >
                                    <Pill class="size-4" aria-hidden="true" />
                                    Para dar hoy
                                </h2>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="touch-target"
                                >
                                    <Link :href="medicacion()">Ver todo</Link>
                                </Button>
                            </div>

                            <ul class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="toma in tomasDeHoy"
                                    :key="toma.id"
                                    class="flex items-center gap-3 py-2"
                                >
                                    <span
                                        class="w-14 shrink-0 text-sm"
                                        :class="
                                            toma.atrasada
                                                ? 'text-destructive'
                                                : ''
                                        "
                                    >
                                        <span
                                            v-if="toma.dia"
                                            class="block text-xs"
                                        >
                                            {{ toma.dia }}
                                        </span>
                                        <span class="font-semibold">{{
                                            toma.hora
                                        }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-sm font-medium wrap-anywhere"
                                        >
                                            {{ toma.medicamento }}
                                        </p>
                                        <p
                                            class="text-xs wrap-anywhere text-muted-foreground"
                                        >
                                            {{ toma.dosis }} ·
                                            {{ toma.mascota }}
                                        </p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        class="touch-target shrink-0"
                                        :aria-label="`Marcar ${toma.medicamento} de ${toma.mascota} como dada`"
                                        @click="marcarDada(toma)"
                                    >
                                        <Check
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- 2. Lo que hay que agendar -->
                    <Card v-if="recordatorios.length" class="py-0">
                        <CardContent class="flex flex-col gap-3 p-4">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <h2
                                    class="inline-flex items-center gap-2 font-medium"
                                >
                                    <BellRing
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Próximos 30 días
                                </h2>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="touch-target"
                                >
                                    <Link :href="recordatoriosIndex()"
                                        >Ver todo</Link
                                    >
                                </Button>
                            </div>

                            <ul class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="recordatorio in recordatorios"
                                    :key="recordatorio.id"
                                    class="flex items-center gap-3 py-2"
                                >
                                    <CalendarClock
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm wrap-anywhere">
                                            {{ recordatorio.titulo }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ recordatorio.mascota_nombre }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 text-xs"
                                        :class="
                                            recordatorio.vencido
                                                ? 'text-destructive'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ recordatorio.cuando }}
                                    </span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- 3. El estado: peso y dieta -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Card class="py-0">
                            <CardContent class="flex flex-col gap-3 p-4">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-medium text-muted-foreground uppercase"
                                        >
                                            Peso
                                        </p>
                                        <p
                                            v-if="ultimoPeso"
                                            class="text-lg font-semibold"
                                        >
                                            {{ ultimoPeso.peso_legible }}
                                            <span
                                                v-if="variacionPeso"
                                                class="text-sm font-normal"
                                                :class="
                                                    variacionPeso.kilos === 0
                                                        ? 'text-muted-foreground'
                                                        : variacionPeso.sube
                                                          ? 'text-amber-600 dark:text-amber-500'
                                                          : 'text-primary'
                                                "
                                            >
                                                {{ variacionPeso.texto }}
                                            </span>
                                        </p>
                                        <p
                                            v-else
                                            class="text-sm text-muted-foreground"
                                        >
                                            Sin registrar
                                        </p>
                                        <p
                                            v-if="ultimoPeso"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ ultimoPeso.fecha_legible }}
                                        </p>
                                    </div>
                                    <Scale
                                        class="size-5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </div>

                                <Button
                                    v-if="puedeRegistrar"
                                    variant="outline"
                                    size="sm"
                                    class="touch-target"
                                    @click="sheetPeso = true"
                                >
                                    <Plus class="size-4" aria-hidden="true" />
                                    Pesar ahora
                                </Button>
                            </CardContent>
                        </Card>

                        <Card class="py-0">
                            <CardContent class="flex flex-col gap-3 p-4">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-medium text-muted-foreground uppercase"
                                        >
                                            Come
                                        </p>
                                        <p
                                            v-if="dietaVigente"
                                            class="font-medium wrap-anywhere"
                                        >
                                            {{ dietaVigente.alimento }}
                                        </p>
                                        <p
                                            v-else
                                            class="text-sm text-muted-foreground"
                                        >
                                            Sin cargar
                                        </p>
                                        <p
                                            v-if="dietaVigente?.racion_legible"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ dietaVigente.racion_legible }}
                                        </p>
                                    </div>
                                    <UtensilsCrossed
                                        class="size-5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </div>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="touch-target"
                                >
                                    <Link :href="seguimiento(mascotaActiva.id)">
                                        Ver seguimiento
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </template>
            </FichaMascota>

            <!-- Pesar sin salir del inicio -->
            <Sheet v-model:open="sheetPeso">
                <SheetContent
                    side="bottom"
                    class="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
                >
                    <SheetHeader>
                        <SheetTitle>
                            Pesar a {{ mascotaActiva.nombre }}
                        </SheetTitle>
                        <SheetDescription>
                            Anotá con qué balanza fue: la de la veterinaria y la
                            de casa no coinciden.
                        </SheetDescription>
                    </SheetHeader>

                    <Form
                        :action="storePeso(mascotaActiva.id).url"
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
        </template>
    </div>
</template>
