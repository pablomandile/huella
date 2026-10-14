<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { home } from '@/routes';

/*
 * Qué guarda Huella en el navegador y para qué. Es el inventario completo:
 * `AvisoCookiesTest` busca en el código cada clave que se escribe en el
 * navegador y exige que aparezca acá, así que una clave nueva sin declarar
 * rompe la suite.
 *
 * Se abre con sesión y sin ella, igual que Acerca de: sin sesión no hay
 * AppLayout y la página trae su propio encabezado.
 */

type Dato = {
    nombre: string;
    para: string;
    dura: string;
};

type Grupo = {
    titulo: string;
    descripcion: string;
    datos: Dato[];
};

const GRUPOS: Grupo[] = [
    {
        titulo: 'Cookies',
        descripcion:
            'Pequeños datos que el navegador manda en cada pedido a Huella.',
        datos: [
            {
                nombre: 'huella-session',
                para: 'Mantener tu sesión iniciada mientras usás la app.',
                dura: '2 horas sin actividad',
            },
            {
                nombre: 'XSRF-TOKEN',
                para: 'Comprobar que cada formulario lo enviaste vos desde Huella y no otro sitio en tu nombre.',
                dura: '2 horas sin actividad',
            },
            {
                nombre: 'remember_web_…',
                para: 'Solo si marcás «Recordarme»: que no tengas que volver a iniciar sesión en este dispositivo.',
                dura: 'Hasta que salgas de la cuenta',
            },
            {
                nombre: 'appearance',
                para: 'Si elegiste tema claro, oscuro o el del sistema, para dibujar la pantalla con ese tema desde el primer instante.',
                dura: '1 año',
            },
            {
                nombre: 'sidebar_state',
                para: 'Si dejaste el menú lateral abierto o cerrado (en computadora).',
                dura: '7 días',
            },
        ],
    },
    {
        titulo: 'Guardado en este dispositivo',
        descripcion:
            'Datos que quedan en el navegador y nunca se mandan solos a ningún lado.',
        datos: [
            {
                nombre: 'appearance · tamanoLetra',
                para: 'El tema y el tamaño de letra que elegiste en este dispositivo.',
                dura: 'Hasta que los cambies',
            },
            {
                nombre: 'huella:aviso-cookies',
                para: 'Que ya leíste este aviso, para no volver a mostrártelo.',
                dura: 'Hasta que borres los datos del navegador',
            },
            {
                nombre: 'huella:visitas · huella:banner-instalar-descartado',
                para: 'Cuándo ofrecerte instalar Huella, y no insistir si dijiste que ahora no.',
                dura: 'Hasta que borres los datos del navegador',
            },
            {
                nombre: 'huella:seccion:…',
                para: 'Si dejaste abierta la galería o la documentación de una ficha.',
                dura: 'Hasta que cierres la pestaña',
            },
            {
                nombre: 'sin-conexion:precalentado · sin-conexion:usuario',
                para: 'Cuándo se guardaron por última vez las pantallas para usarlas sin conexión, y de qué cuenta son.',
                dura: 'Hasta que salgas de la cuenta',
            },
            {
                nombre: 'huella-sin-conexion',
                para: 'Las tomas, pesos, baños y notas que cargaste sin conexión, hasta que se envían.',
                dura: 'Hasta que se sincronizan',
            },
            {
                nombre: 'huella-v…',
                para: 'La app en sí —diseño, íconos, código—, para que abra rápido y también sin señal. No tiene datos tuyos.',
                dura: 'Hasta la próxima versión de Huella',
            },
            {
                nombre: 'huella-datos-v…',
                para: 'Las pantallas que ya viste, para poder consultarlas sin señal.',
                dura: 'Hasta que salgas de la cuenta',
            },
        ],
    },
];

const page = usePage();
const conSesion = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <Head title="Cookies y datos guardados" />

    <div
        :class="
            conSesion
                ? ''
                : 'min-h-svh bg-background fondo-huellas text-foreground'
        "
    >
        <header
            v-if="!conSesion"
            class="mx-auto w-full max-w-2xl px-4 pt-4 sm:px-6 sm:pt-6"
        >
            <Link
                :href="home()"
                class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4 shrink-0" />
                Volver al inicio
            </Link>
        </header>

        <main
            class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 sm:p-6"
            :class="conSesion ? '' : 'pb-12'"
        >
            <h1 class="text-xl font-semibold">Cookies y datos guardados</h1>

            <div class="rounded-xl border border-border bg-card p-6">
                <p class="text-pretty">
                    Todo lo que Huella guarda en tu navegador es necesario para
                    que funcione. No hay publicidad, ni estadísticas de uso, ni
                    nada de terceros que siga lo que hacés.
                </p>
            </div>

            <section
                v-for="grupo in GRUPOS"
                :key="grupo.titulo"
                class="flex flex-col gap-3"
            >
                <div>
                    <h2 class="font-semibold">{{ grupo.titulo }}</h2>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ grupo.descripcion }}
                    </p>
                </div>

                <!--
                    Tarjetas apiladas y no una tabla: con tres columnas, en el
                    celular y con letra grande, la tabla se iría de ancho.
                -->
                <dl
                    class="divide-y divide-border rounded-xl border border-border bg-card"
                >
                    <div
                        v-for="dato in grupo.datos"
                        :key="dato.nombre"
                        class="flex flex-col gap-1 p-4"
                    >
                        <dt class="font-mono text-sm wrap-anywhere">
                            {{ dato.nombre }}
                        </dt>
                        <dd class="text-sm text-pretty">{{ dato.para }}</dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ dato.dura }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">Si entrás con Google</h2>
                <p class="text-sm text-pretty">
                    Google pone sus propias cookies, en su sitio, mientras
                    iniciás sesión con tu cuenta de Google. Si entrás con email
                    y contraseña no interviene.
                </p>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="font-semibold">Cómo borrarlo</h2>
                <p class="text-sm text-pretty">
                    Al salir de tu cuenta se borra todo lo guardado para usar la
                    app sin conexión: es historia clínica en un teléfono que
                    puede ser compartido. El resto lo podés borrar cuando
                    quieras desde la configuración de tu navegador; si borrás
                    las cookies, vas a tener que volver a iniciar sesión.
                </p>
            </section>
        </main>
    </div>
</template>
