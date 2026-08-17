<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Flower2,
    ImagePlus,
    PawPrint,
    Pencil,
    Pill,
    Plus,
    ShieldCheck,
    Stethoscope,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import CampoFoto from '@/components/CampoFoto.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as destroyAlergia,
    store as storeAlergia,
} from '@/routes/mascotas/alergias';
import {
    destroy as destroyFoto,
    store as storeFoto,
} from '@/routes/mascotas/fotos';
import { edit, index } from '@/routes/mascotas';
import {
    create as createVisita,
    index as visitasIndex,
    show as showVisita,
} from '@/routes/mascotas/visitas';
import { index as diario } from '@/routes/mascotas/diario';
import { index as preventivo } from '@/routes/mascotas/preventivo';
import { index as seguimiento } from '@/routes/mascotas/seguimiento';
import { index as medicacionIndex } from '@/routes/medicacion';
import type {
    Alergia,
    EstadoVacunacion,
    FotoGaleria,
    Mascota,
    OpcionEnum,
    Recordatorio,
    Tratamiento,
    Visita,
} from '@/types/huella';

const props = defineProps<{
    mascota: Mascota;
    fotos: FotoGaleria[];
    alergias: Alergia[];
    visitas: Visita[];
    totalVisitas: number;
    tratamientosEnCurso: Tratamiento[];
    recordatorios: Recordatorio[];
    estadoVacunacion: EstadoVacunacion;
    puedeEditar: boolean;
    puedeRegistrar: boolean;
    tiposAlergia: OpcionEnum[];
}>();

/** El color del semáforo: verde al día, ámbar por vencer, rojo vencida. */
const colorSemaforo: Record<EstadoVacunacion['estado'], string> = {
    al_dia: 'border-primary/40 bg-primary/5',
    proxima: 'border-amber-500/40 bg-amber-500/5',
    vencida: 'border-destructive/40 bg-destructive/5',
    sin_datos: 'border-border',
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: index() }],
    },
});

const dialogoFoto = ref(false);
const dialogoAlergia = ref(false);
const hoy = new Date().toISOString().slice(0, 10);

function eliminarFoto(foto: FotoGaleria) {
    if (confirm('¿Eliminar esta foto de la galería?')) {
        router.delete(destroyFoto([props.mascota.id, foto.id]).url, {
            preserveScroll: true,
        });
    }
}

function eliminarAlergia(alergia: Alergia) {
    if (confirm(`¿Eliminar la alergia a ${alergia.agente}?`)) {
        router.delete(destroyAlergia([props.mascota.id, alergia.id]).url, {
            preserveScroll: true,
        });
    }
}

const datosFicha = (mascota: Mascota): [string, string][] =>
    (
        [
            ['Especie', mascota.especie_etiqueta],
            ['Raza', mascota.raza],
            ['Sexo', mascota.sexo_etiqueta],
            ['Edad', mascota.edad],
            ['Nacimiento', mascota.fecha_nacimiento],
            ['Adopción', mascota.fecha_adopcion],
            ['Color', mascota.color],
            ['Pelaje', mascota.tipo_pelaje_etiqueta],
            ['Microchip', mascota.microchip],
            ['Libreta sanitaria', mascota.libreta_sanitaria],
            ['Pedigree', mascota.pedigree],
            [
                'Castración',
                mascota.castrado ? (mascota.fecha_castracion ?? 'Sí') : 'No',
            ],
            ['Seguro', mascota.seguro_compania],
            ['Vencimiento del seguro', mascota.seguro_vencimiento],
        ] as [string, string | null][]
    ).filter((par): par is [string, string] => Boolean(par[1]));
</script>

<template>
    <Head :title="mascota.nombre" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <!-- Cabecera de la ficha -->
        <div class="flex items-start gap-4">
            <div
                class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-muted sm:size-24"
            >
                <img
                    v-if="mascota.foto_miniatura_url"
                    :src="mascota.foto_miniatura_url"
                    :alt="`Foto de ${mascota.nombre}`"
                    class="size-full object-cover"
                />
                <PawPrint
                    v-else
                    class="size-8 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold">{{ mascota.nombre }}</h1>
                    <Badge v-if="mascota.fallecida" variant="secondary">
                        <Flower2 class="size-3" aria-hidden="true" />
                        En memoria
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ mascota.especie_etiqueta }}
                    <template v-if="mascota.raza">
                        · {{ mascota.raza }}</template
                    >
                    <template v-if="mascota.edad">
                        · {{ mascota.edad }}</template
                    >
                </p>
                <p
                    v-if="mascota.descripcion"
                    class="mt-1 text-sm text-pretty text-muted-foreground"
                >
                    {{ mascota.descripcion }}
                </p>
            </div>

            <Button
                v-if="puedeEditar"
                as-child
                variant="outline"
                size="sm"
                class="touch-target"
            >
                <Link :href="edit(mascota.id)">
                    <Pencil class="size-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Editar</span>
                </Link>
            </Button>
        </div>

        <p
            v-if="mascota.fallecida"
            class="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground"
        >
            Esta ficha está en modo lectura: el historial se conserva completo,
            pero ya no se registran eventos nuevos.
        </p>

        <!-- Alergias: el dato de urgencia, siempre visible arriba -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Alergias</CardTitle>
                <Dialog v-if="puedeRegistrar" v-model:open="dialogoAlergia">
                    <DialogTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="touch-target"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Agregar
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Registrar una alergia</DialogTitle>
                            <DialogDescription>
                                Queda a mano para cualquier urgencia y entra en
                                el PDF de la historia clínica.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            :action="storeAlergia(mascota.id).url"
                            method="post"
                            class="flex flex-col gap-4"
                            :options="{ preserveScroll: true }"
                            @success="dialogoAlergia = false"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <Label for="agente">Alergia a *</Label>
                                <Input
                                    id="agente"
                                    name="agente"
                                    required
                                    maxlength="140"
                                    placeholder="Pollo, penicilina, ácaros…"
                                />
                                <InputError :message="errors.agente" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="tipo">Tipo *</Label>
                                    <SelectNativo
                                        name="tipo"
                                        :opciones="tiposAlergia"
                                        default-value="otra"
                                    />
                                    <InputError :message="errors.tipo" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="severidad">Severidad</Label>
                                    <SelectNativo
                                        name="severidad"
                                        :opciones="[
                                            { value: 'leve', label: 'Leve' },
                                            {
                                                value: 'moderada',
                                                label: 'Moderada',
                                            },
                                            {
                                                value: 'severa',
                                                label: 'Severa',
                                            },
                                        ]"
                                        placeholder="No sé"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="sintomas">Síntomas</Label>
                                <Input
                                    id="sintomas"
                                    name="sintomas"
                                    maxlength="2000"
                                />
                            </div>

                            <Button type="submit" :disabled="processing">
                                <Spinner v-if="processing" />
                                Guardar
                            </Button>
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!alergias.length"
                    class="text-sm text-muted-foreground"
                >
                    Sin alergias conocidas.
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="alergia in alergias"
                        :key="alergia.id"
                        class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ alergia.agente }}
                                <Badge
                                    v-if="alergia.severidad"
                                    :variant="
                                        alergia.severidad === 'severa'
                                            ? 'destructive'
                                            : 'secondary'
                                    "
                                    class="ml-1"
                                >
                                    {{ alergia.severidad_etiqueta }}
                                </Badge>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ alergia.tipo_etiqueta }}
                                <template v-if="alergia.sintomas">
                                    · {{ alergia.sintomas }}</template
                                >
                            </p>
                        </div>
                        <button
                            v-if="puedeEditar"
                            type="button"
                            class="flex touch-target items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                            :aria-label="`Eliminar la alergia a ${alergia.agente}`"
                            @click="eliminarAlergia(alergia)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Semáforo de vacunación y lo que hay que agendar -->
        <Card :class="colorSemaforo[estadoVacunacion.estado]">
            <CardContent class="flex flex-col gap-3">
                <div class="flex items-start gap-3">
                    <ShieldCheck
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
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
                    <div class="flex shrink-0 flex-col gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            as-child
                            class="touch-target"
                        >
                            <Link :href="diario(mascota.id)">Diario</Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="touch-target"
                        >
                            <Link :href="preventivo(mascota.id)"
                                >Preventivo</Link
                            >
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="touch-target"
                        >
                            <Link :href="seguimiento(mascota.id)"
                                >Seguimiento</Link
                            >
                        </Button>
                    </div>
                </div>

                <ul
                    v-if="recordatorios.length"
                    class="flex flex-col gap-2 border-t border-border pt-3"
                >
                    <li
                        v-for="recordatorio in recordatorios"
                        :key="recordatorio.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <CalendarClock
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 truncate">
                            {{ recordatorio.titulo }}
                        </span>
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

        <!-- Lo que está tomando ahora: lo primero que se busca en la ficha -->
        <Card v-if="tratamientosEnCurso.length">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Medicación en curso</CardTitle>
                <Button variant="ghost" size="sm" as-child class="touch-target">
                    <Link :href="medicacionIndex()">Ver hoy</Link>
                </Button>
            </CardHeader>
            <CardContent>
                <ul class="flex flex-col gap-3">
                    <li
                        v-for="tratamiento in tratamientosEnCurso"
                        :key="tratamiento.id"
                        class="flex items-start gap-3"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                        >
                            <Pill
                                class="size-4 text-accent-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ tratamiento.nombre_medicamento }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ tratamiento.posologia }}
                            </p>
                            <p
                                v-if="tratamiento.adherencia?.total"
                                class="text-xs text-muted-foreground"
                            >
                                {{ tratamiento.adherencia.dadas }} de
                                {{ tratamiento.adherencia.total }} dadas
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Visitas -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Visitas</CardTitle>
                <Button
                    v-if="puedeRegistrar"
                    variant="outline"
                    size="sm"
                    as-child
                    class="touch-target"
                >
                    <Link :href="createVisita(mascota.id)">
                        <Plus class="size-4" aria-hidden="true" />
                        Nueva
                    </Link>
                </Button>
            </CardHeader>
            <CardContent>
                <p v-if="!visitas.length" class="text-sm text-muted-foreground">
                    Todavía no cargaste ninguna consulta.
                </p>

                <ul v-else class="flex flex-col gap-3">
                    <li v-for="visita in visitas" :key="visita.id">
                        <Link
                            :href="showVisita([mascota.id, visita.id])"
                            class="flex touch-target items-start gap-3 rounded-md px-1 py-1.5 hover:bg-accent/50"
                        >
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent"
                            >
                                <Stethoscope
                                    class="size-4 text-accent-foreground"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ visita.motivo ?? visita.tipo_etiqueta }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ visita.fecha_legible }}
                                </p>
                            </div>
                        </Link>
                    </li>
                </ul>

                <Link
                    v-if="totalVisitas > visitas.length"
                    :href="visitasIndex(mascota.id)"
                    class="mt-3 inline-block text-sm text-primary hover:underline"
                >
                    Ver las {{ totalVisitas }} visitas
                </Link>
            </CardContent>
        </Card>

        <!-- Datos de la ficha -->
        <Card>
            <CardHeader>
                <CardTitle>Ficha</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                    <div
                        v-for="[etiqueta, valor] in datosFicha(mascota)"
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
                    <span class="font-medium text-foreground"
                        >Señas particulares:</span
                    >
                    {{ mascota.senias_particulares }}
                </p>
            </CardContent>
        </Card>

        <!-- Galería -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Galería</CardTitle>
                <Dialog v-if="puedeRegistrar" v-model:open="dialogoFoto">
                    <DialogTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="touch-target"
                        >
                            <ImagePlus class="size-4" aria-hidden="true" />
                            Agregar foto
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Nueva foto</DialogTitle>
                            <DialogDescription>
                                Con fecha y epígrafe, para ver su evolución a lo
                                largo de los años.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            :action="storeFoto(mascota.id).url"
                            method="post"
                            class="flex flex-col gap-4"
                            :options="{ preserveScroll: true }"
                            @success="dialogoFoto = false"
                            v-slot="{ errors, processing }"
                        >
                            <CampoFoto name="foto" />
                            <InputError :message="errors.foto" />

                            <div class="grid gap-2">
                                <Label for="fecha">Fecha *</Label>
                                <Input
                                    id="fecha"
                                    type="date"
                                    name="fecha"
                                    required
                                    :default-value="hoy"
                                />
                                <InputError :message="errors.fecha" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="epigrafe">Epígrafe</Label>
                                <Input
                                    id="epigrafe"
                                    name="epigrafe"
                                    maxlength="255"
                                    placeholder="Primer día en casa"
                                />
                                <InputError :message="errors.epigrafe" />
                            </div>

                            <Button type="submit" :disabled="processing">
                                <Spinner v-if="processing" />
                                Guardar
                            </Button>
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardHeader>
            <CardContent>
                <p v-if="!fotos.length" class="text-sm text-muted-foreground">
                    Todavía no hay fotos en la galería.
                </p>
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <figure
                        v-for="foto in fotos"
                        :key="foto.id"
                        class="group relative"
                    >
                        <img
                            :src="foto.miniatura_url"
                            :alt="foto.epigrafe ?? `Foto de ${mascota.nombre}`"
                            class="aspect-square w-full rounded-lg object-cover"
                            loading="lazy"
                        />
                        <figcaption class="mt-1 text-xs text-muted-foreground">
                            {{ foto.fecha }}
                            <template v-if="foto.epigrafe">
                                · {{ foto.epigrafe }}</template
                            >
                        </figcaption>
                        <button
                            v-if="puedeEditar"
                            type="button"
                            class="absolute top-1 right-1 flex size-8 items-center justify-center rounded-full bg-background/80 text-muted-foreground opacity-0 shadow transition-opacity group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100"
                            aria-label="Eliminar esta foto"
                            @click="eliminarFoto(foto)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </figure>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
