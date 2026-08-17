<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    BellRing,
    CalendarClock,
    Check,
    NotebookPen,
    PawPrint,
    Pill,
    Plus,
    Scale,
    ShieldCheck,
    Stethoscope,
    UtensilsCrossed,
} from '@lucide/vue';
import { ref } from 'vue';
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
import { dashboard } from '@/routes';
import { create, show } from '@/routes/mascotas';
import { index as diario } from '@/routes/mascotas/diario';
import { store as storePeso } from '@/routes/mascotas/pesos';
import { index as preventivo } from '@/routes/mascotas/preventivo';
import { index as seguimiento } from '@/routes/mascotas/seguimiento';
import { index as medicacion, update as marcarToma } from '@/routes/medicacion';
import { index as recordatoriosIndex } from '@/routes/recordatorios';
import type {
    Dieta,
    EstadoVacunacion,
    Mascota,
    OpcionEnum,
    Recordatorio,
    RegistroPeso,
    TomaDelDia,
    VariacionPeso,
    Visita,
} from '@/types/huella';

/*
 * El dashboard de la especificación §5.
 *
 * El orden de los bloques no es decorativo: primero lo que hay que hacer hoy,
 * después el estado, al final el historial. Quien abre la app a la mañana quiere
 * saber qué le toca, no cuánto pesa.
 */

defineProps<{
    mascotaActiva: Mascota | null;
    totalMascotas: number;
    tomasDeHoy: TomaDelDia[];
    recordatorios: Recordatorio[];
    ultimoPeso: RegistroPeso | null;
    variacionPeso: VariacionPeso | null;
    dietaVigente: Dieta | null;
    estadoVacunacion: EstadoVacunacion | null;
    ultimaVisita: Visita | null;
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

const colorSemaforo: Record<EstadoVacunacion['estado'], string> = {
    al_dia: 'text-primary',
    proxima: 'text-amber-600 dark:text-amber-500',
    vencida: 'text-destructive',
    sin_datos: 'text-muted-foreground',
};

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
            <!-- La mascota activa -->
            <Link
                :href="show(mascotaActiva.id)"
                class="group focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Card
                    class="overflow-hidden py-0 transition-shadow group-hover:shadow-md"
                >
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-muted"
                        >
                            <img
                                v-if="mascotaActiva.foto_miniatura_url"
                                :src="mascotaActiva.foto_miniatura_url"
                                :alt="`Foto de ${mascotaActiva.nombre}`"
                                class="size-full object-cover"
                            />
                            <PawPrint
                                v-else
                                class="size-7 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="truncate text-lg font-semibold">
                                {{ mascotaActiva.nombre }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{ mascotaActiva.especie_etiqueta }}
                                <template v-if="mascotaActiva.edad">
                                    · {{ mascotaActiva.edad }}</template
                                >
                            </p>
                            <p
                                v-if="estadoVacunacion"
                                class="mt-0.5 inline-flex items-center gap-1 text-sm"
                                :class="colorSemaforo[estadoVacunacion.estado]"
                            >
                                <ShieldCheck
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Vacunación: {{ estadoVacunacion.etiqueta }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <!-- 1. Lo que hay que dar hoy -->
            <Card v-if="tomasDeHoy.length" class="py-0">
                <CardContent class="flex flex-col gap-3 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="inline-flex items-center gap-2 font-medium">
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
                                :class="toma.atrasada ? 'text-destructive' : ''"
                            >
                                <span v-if="toma.dia" class="block text-xs">
                                    {{ toma.dia }}
                                </span>
                                <span class="font-semibold">{{
                                    toma.hora
                                }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ toma.medicamento }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ toma.dosis }} · {{ toma.mascota }}
                                </p>
                            </div>
                            <Button
                                size="icon"
                                variant="outline"
                                class="touch-target shrink-0"
                                :aria-label="`Marcar ${toma.medicamento} de ${toma.mascota} como dada`"
                                @click="marcarDada(toma)"
                            >
                                <Check class="size-4" aria-hidden="true" />
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- 2. Lo que hay que agendar -->
            <Card v-if="recordatorios.length" class="py-0">
                <CardContent class="flex flex-col gap-3 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="inline-flex items-center gap-2 font-medium">
                            <BellRing class="size-4" aria-hidden="true" />
                            Próximos 30 días
                        </h2>
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="touch-target"
                        >
                            <Link :href="recordatoriosIndex()">Ver todo</Link>
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
                                <p class="truncate text-sm">
                                    {{ recordatorio.titulo }}
                                </p>
                                <p class="text-xs text-muted-foreground">
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
                        <div class="flex items-start justify-between gap-3">
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
                                <p v-else class="text-sm text-muted-foreground">
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
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Come
                                </p>
                                <p
                                    v-if="dietaVigente"
                                    class="truncate font-medium"
                                >
                                    {{ dietaVigente.alimento }}
                                </p>
                                <p v-else class="text-sm text-muted-foreground">
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

            <!-- 4. La última visita -->
            <Card v-if="ultimaVisita" class="py-0">
                <CardContent class="flex items-start gap-3 p-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                    >
                        <Stethoscope
                            class="size-4 text-accent-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-medium text-muted-foreground uppercase"
                        >
                            Última visita
                        </p>
                        <p class="font-medium">
                            {{
                                ultimaVisita.motivo ??
                                ultimaVisita.tipo_etiqueta
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ ultimaVisita.fecha_legible }}
                        </p>
                        <Badge
                            v-if="ultimaVisita.proximo_control"
                            variant="secondary"
                            class="mt-2 font-normal"
                        >
                            Control: {{ ultimaVisita.proximo_control }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- 5. El acceso al diario, que es la pantalla principal -->
            <div class="grid gap-2 sm:grid-cols-2">
                <Button
                    variant="outline"
                    as-child
                    class="touch-target justify-start"
                >
                    <Link :href="diario(mascotaActiva.id)">
                        <NotebookPen class="size-4" aria-hidden="true" />
                        Ver el diario completo
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    as-child
                    class="touch-target justify-start"
                >
                    <Link :href="preventivo(mascotaActiva.id)">
                        <ShieldCheck class="size-4" aria-hidden="true" />
                        Vacunas y desparasitaciones
                    </Link>
                </Button>
            </div>

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
