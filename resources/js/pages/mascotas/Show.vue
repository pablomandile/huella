<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    Flower2,
    ImagePlus,
    PawPrint,
    Pencil,
    Pill,
    Play,
    Plus,
    Share2,
    ShieldCheck,
    Stethoscope,
    Trash2,
} from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import CampoFoto from '@/components/CampoFoto.vue';
import SheetCompartir from '@/components/SheetCompartir.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import PaseDeFotos from '@/components/PaseDeFotos.vue';
import TarjetaDocumento from '@/components/TarjetaDocumento.vue';
import VisorImagen from '@/components/VisorImagen.vue';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as destroyAlergia,
    store as storeAlergia,
} from '@/routes/mascotas/alergias';
import {
    destroy as destroyFoto,
    store as storeFoto,
    update as actualizarFoto,
} from '@/routes/mascotas/fotos';
// Con alias: la prop `vencimientoRabia` trae la fecha y la ruta se llama igual.
import {
    edit,
    index,
    vencimientoRabia as rutaVencimientoRabia,
} from '@/routes/mascotas';
import {
    create as createVisita,
    index as visitasIndex,
    show as showVisita,
} from '@/routes/mascotas/visitas';
import { index as diario } from '@/routes/mascotas/diario';
import { index as preventivo } from '@/routes/mascotas/preventivo';
import { index as seguimiento } from '@/routes/mascotas/seguimiento';
import { index as medicacionIndex } from '@/routes/medicacion';
import { destroy as quitarseElAcceso } from '@/routes/mascotas/accesos';
import type {
    AccesoCompartido,
    Alergia,
    DocumentosDeMascota,
    EstadoVacunacion,
    EnlaceCompartido as EnlaceCompartidoTipo,
    EstadoVencimiento,
    FotoGaleria,
    Mascota,
    OpcionDeRol,
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
    documentos: DocumentosDeMascota;
    vencimientoRabia: string | null;
    estadoRabia: EstadoVencimiento | null;
    puedeEditar: boolean;
    puedeRegistrar: boolean;
    puedeCompartir: boolean;
    accesos: AccesoCompartido[];
    enlaces: EnlaceCompartidoTipo[];
    rolesInvitables: OpcionDeRol[];
    vigencias: OpcionEnum[];
    vigenciaPorDefecto: string;
    tiposAlergia: OpcionEnum[];
}>();

const sheetCompartir = ref(false);

const page = usePage();
const usuarioActual = computed(() => page.props.auth.user.id);

/** Irse de una ficha que alguien compartió: sin esto se queda pegada al listado. */
function dejarDeVer() {
    if (!confirm(`¿Dejar de ver la ficha de ${props.mascota.nombre}?`)) {
        return;
    }

    router.delete(
        quitarseElAcceso([props.mascota.id, usuarioActual.value]).url,
    );
}

/** El color del semáforo: verde al día, ámbar por vencer, rojo vencida. */
const colorSemaforo: Record<EstadoVacunacion['estado'], string> = {
    al_dia: 'border-primary/40 bg-primary/5',
    proxima: 'border-amber-500/40 bg-amber-500/5',
    vencida: 'border-destructive/40 bg-destructive/5',
    sin_datos: 'border-border',
};

/** Mismo criterio de color que el semáforo, en formato de badge. */
const colorVencimiento: Record<
    EstadoVencimiento['estado'],
    'default' | 'secondary' | 'destructive'
> = {
    vigente: 'secondary',
    por_vencer: 'default',
    vencido: 'destructive',
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mascotas', href: index() }],
    },
});

const dialogoFoto = ref(false);
const dialogoAlergia = ref(false);
const hoy = new Date().toISOString().slice(0, 10);

const visorAbierto = ref(false);
const paseAbierto = ref(false);
const editandoFoto = ref(false);
const fotoAbierta = shallowRef<FotoGaleria | null>(null);

function abrirFoto(foto: FotoGaleria) {
    fotoAbierta.value = foto;
    editandoFoto.value = false;
    visorAbierto.value = true;
}

function eliminarFoto(foto: FotoGaleria) {
    if (!confirm('¿Eliminar esta foto de la galería?')) {
        return;
    }

    // Se cierra el visor antes de borrar: si no, queda abierto mostrando una
    // imagen que ya no existe y el `src` pasa a dar 404.
    visorAbierto.value = false;
    editandoFoto.value = false;

    router.delete(destroyFoto([props.mascota.id, foto.id]).url, {
        preserveScroll: true,
    });
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

            <div class="flex shrink-0 gap-2">
                <Button
                    v-if="puedeCompartir"
                    variant="outline"
                    size="sm"
                    class="touch-target"
                    @click="sheetCompartir = true"
                >
                    <Share2 class="size-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Compartir</span>
                </Button>

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
        </div>

        <!-- Una ficha compartida se queda pegada al listado para siempre si no
             hay forma de irse. -->
        <div
            v-if="!mascota.es_propia"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted px-4 py-3 text-sm"
        >
            <p class="text-muted-foreground">
                <template v-if="puedeRegistrar">
                    {{ mascota.rol_etiqueta }}: la ficha es de
                    {{ mascota.propietario_nombre }}, y podés registrar lo que
                    pase.
                </template>
                <template v-else>
                    {{ mascota.rol_etiqueta }}: podés ver todo el historial,
                    pero no cargar ni modificar nada.
                </template>
            </p>
            <Button
                variant="ghost"
                size="sm"
                class="touch-target"
                @click="dejarDeVer()"
            >
                Dejar de ver esta ficha
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

        <!--
            Documentación: los dos papeles que se piden afuera de casa. Van acá
            arriba, cerca de las alergias, porque el motivo para abrir la ficha en
            un veterinario nuevo o en una guardería es justamente mostrarlos.
        -->
        <TarjetaDocumento
            titulo="Libreta sanitaria"
            descripcion="Las hojas de la libreta, para tenerla siempre encima."
            tipo="libreta_sanitaria"
            :mascota-id="mascota.id"
            :archivos="documentos.libreta_sanitaria"
            :puede-registrar="puedeRegistrar"
        />

        <TarjetaDocumento
            titulo="Certificado de rabia"
            descripcion="El que piden para viajar o para dejarla en una guardería."
            tipo="certificado_rabia"
            :mascota-id="mascota.id"
            :archivos="documentos.certificado_rabia"
            :puede-registrar="puedeRegistrar"
        >
            <!--
                El vencimiento se carga acá, con el papel en la mano, y no en el
                formulario de la mascota. Es una fecha de `mascotas`, así que el
                recordatorio lo genera el observer al detectar el cambio.
            -->
            <div class="rounded-lg border border-border p-3">
                <div
                    v-if="estadoRabia"
                    class="mb-3 flex items-center gap-2 text-sm"
                >
                    <Badge :variant="colorVencimiento[estadoRabia.estado]">
                        {{ estadoRabia.texto }}
                    </Badge>
                </div>

                <Form
                    :action="rutaVencimientoRabia(mascota.id).url"
                    method="patch"
                    class="flex flex-wrap items-end gap-3"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid flex-1 gap-2">
                        <Label for="rabia_vencimiento">Vence el</Label>
                        <Input
                            id="rabia_vencimiento"
                            type="date"
                            name="rabia_vencimiento"
                            class="touch-target"
                            :default-value="vencimientoRabia ?? ''"
                            :disabled="!puedeRegistrar"
                        />
                        <InputError :message="errors.rabia_vencimiento" />
                    </div>

                    <Button
                        v-if="puedeRegistrar"
                        type="submit"
                        variant="outline"
                        class="touch-target"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Guardar
                    </Button>
                </Form>

                <p class="mt-2 text-xs text-muted-foreground">
                    Dejalo vacío para quitar el aviso. Este vencimiento es del
                    papel; la próxima dosis de la antirrábica se carga en
                    Preventivo.
                </p>
            </div>
        </TarjetaDocumento>

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
                <!--
                    El pase necesita al menos dos fotos: con una sola no hay nada
                    que pasar y el botón sería una promesa vacía.
                -->
                <Button
                    v-if="fotos.length > 1"
                    variant="ghost"
                    size="icon"
                    class="mr-2 ml-auto touch-target"
                    aria-label="Ver las fotos en pantalla completa, de la más vieja a la más nueva"
                    data-test="pase-play"
                    @click="paseAbierto = true"
                >
                    <Play class="size-4" aria-hidden="true" />
                </Button>
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
                <!--
                    Cada foto es un botón que abre el visor. El borrado vivía acá,
                    en un ícono que aparecía con `group-hover`: en el celular no
                    hay hover, así que era invisible justo donde se usa la app.
                    Ahora las acciones están adentro del visor, que se abre con un
                    toque y sirve igual con mouse, dedo y teclado.
                -->
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <figure v-for="foto in fotos" :key="foto.id">
                        <button
                            type="button"
                            class="w-full rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                            @click="abrirFoto(foto)"
                        >
                            <img
                                :src="foto.miniatura_url"
                                :alt="`Ver la foto${foto.epigrafe ? ` «${foto.epigrafe}»` : ''} del ${foto.fecha}`"
                                class="aspect-square w-full rounded-lg object-cover"
                                loading="lazy"
                            />
                        </button>
                        <figcaption class="mt-1 text-xs text-muted-foreground">
                            {{ foto.fecha }}
                            <template v-if="foto.epigrafe">
                                · {{ foto.epigrafe }}</template
                            >
                        </figcaption>
                    </figure>
                </div>
            </CardContent>
        </Card>

        <!-- Pase de fotos: uno solo, y solo si hay más de una. -->
        <PaseDeFotos
            v-if="fotos.length > 1"
            v-model:abierto="paseAbierto"
            :fotos="fotos"
            :nombre-mascota="mascota.nombre"
        />

        <!--
            Visor de la galería. Vive acá y no dentro del `v-for` para que haya
            **uno solo** en el DOM: uno por foto multiplicaría los overlays y los
            focus traps de reka-ui por la cantidad de fotos.
        -->
        <VisorImagen
            v-if="fotoAbierta"
            v-model:abierto="visorAbierto"
            :src="fotoAbierta.url"
            :alt="fotoAbierta.epigrafe ?? `Foto de ${mascota.nombre}`"
            :titulo="`Foto del ${fotoAbierta.fecha}`"
            :descripcion="
                fotoAbierta.epigrafe
                    ? `${fotoAbierta.fecha} · ${fotoAbierta.epigrafe}`
                    : fotoAbierta.fecha
            "
        >
            <template v-if="puedeRegistrar" #acciones>
                <Button
                    variant="secondary"
                    size="sm"
                    class="touch-target"
                    @click="editandoFoto = !editandoFoto"
                >
                    <Pencil class="size-4" aria-hidden="true" />
                    {{ editandoFoto ? 'Cancelar' : 'Editar' }}
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="touch-target"
                    @click="eliminarFoto(fotoAbierta)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Eliminar
                </Button>
            </template>
        </VisorImagen>

        <!--
            El formulario de edición va fuera del visor: adentro de un dialog a
            pantalla completa el teclado del celular tapa el campo, y el sheet ya
            resuelve eso. Se abre con «Editar» y cierra el visor.
        -->
        <Sheet v-if="fotoAbierta" v-model:open="editandoFoto">
            <SheetContent
                side="bottom"
                class="gap-0 rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>Editar la foto</SheetTitle>
                    <SheetDescription>
                        La imagen no se cambia: para eso subí una nueva y borrá
                        esta.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    :key="fotoAbierta.id"
                    :action="actualizarFoto([mascota.id, fotoAbierta.id]).url"
                    method="patch"
                    class="flex flex-col gap-4 p-4"
                    :options="{ preserveScroll: true }"
                    @success="editandoFoto = false"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="foto_fecha">Fecha *</Label>
                        <Input
                            id="foto_fecha"
                            type="date"
                            name="fecha"
                            required
                            class="touch-target"
                            :default-value="fotoAbierta.fecha"
                        />
                        <InputError :message="errors.fecha" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="foto_epigrafe">Epígrafe</Label>
                        <Input
                            id="foto_epigrafe"
                            name="epigrafe"
                            maxlength="255"
                            placeholder="Primer día en casa"
                            :default-value="fotoAbierta.epigrafe ?? ''"
                        />
                        <InputError :message="errors.epigrafe" />
                    </div>

                    <Button
                        type="submit"
                        class="touch-target w-full"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Guardar
                    </Button>
                </Form>
            </SheetContent>
        </Sheet>
    </div>

    <SheetCompartir
        v-if="puedeCompartir"
        v-model:open="sheetCompartir"
        :mascota-id="mascota.id"
        :mascota-nombre="mascota.nombre"
        :accesos="accesos"
        :enlaces="enlaces"
        :roles-invitables="rolesInvitables"
        :vigencias="vigencias"
        :vigencia-por-defecto="vigenciaPorDefecto"
    />
</template>
