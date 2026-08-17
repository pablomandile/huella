<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    House,
    Lock,
    ServerCrash,
    SearchX,
    TimerReset,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

/*
 * Página de error única para 403, 404, 419, 500 y 503.
 *
 * Es una página Inertia y no un Blade suelto porque así conserva el idioma, el
 * tema y la marca: un error que se ve como otra app asusta más que el error.
 *
 * Cada texto dice **qué hacer**, no solo qué pasó. "No encontramos esa página"
 * sin una salida deja al usuario en un callejón.
 */

const props = defineProps<{
    status: number;
    autenticado: boolean;
}>();

type Detalle = {
    icono: unknown;
    titulo: string;
    texto: string;
};

const detalles: Record<number, Detalle> = {
    403: {
        icono: Lock,
        titulo: 'Esto no es tuyo',
        texto:
            'No tenés permiso para ver esta página. Si es la ficha de una mascota ' +
            'que compartías con alguien, puede que te hayan quitado el acceso.',
    },
    404: {
        icono: SearchX,
        titulo: 'No encontramos esa página',
        texto:
            'Puede que el enlace esté viejo o que lo que buscabas se haya dado ' +
            'de baja. Nada de lo que cargaste se perdió.',
    },
    419: {
        icono: TimerReset,
        titulo: 'Se venció la sesión',
        texto:
            'Estuviste un rato sin actividad y cerramos la sesión por seguridad. ' +
            'Entrá de nuevo y seguí donde estabas.',
    },
    500: {
        icono: ServerCrash,
        titulo: 'Algo se rompió de nuestro lado',
        texto:
            'No es culpa tuya y no perdiste nada de lo que ya estaba guardado. ' +
            'Probá de nuevo en un momento.',
    },
    503: {
        icono: TimerReset,
        titulo: 'Volvemos en un rato',
        texto:
            'Estamos actualizando la app. Tus datos están intactos; en unos ' +
            'minutos vuelve a andar.',
    },
};

const detalle = computed<Detalle>(
    () =>
        detalles[props.status] ?? {
            icono: ServerCrash,
            titulo: 'Algo no salió bien',
            texto: 'Probá de nuevo. Si sigue pasando, cerrá y volvé a abrir la app.',
        },
);

// Tras un 419 la sesión ya no existe: mandarlo al dashboard rebota al login.
const destino = computed(() =>
    props.autenticado && props.status !== 419 ? dashboard() : login(),
);
const textoDestino = computed(() =>
    props.autenticado && props.status !== 419
        ? 'Ir al inicio'
        : 'Iniciar sesión',
);

/** Solo se ofrece si hay algo atrás: en una pestaña nueva no lleva a ninguna parte. */
const hayHistorial = typeof window !== 'undefined' && window.history.length > 1;

function volver() {
    window.history.back();
}
</script>

<template>
    <Head :title="detalle.titulo" />

    <div
        class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background px-6 py-16 text-center text-foreground"
    >
        <AppLogoIcon class="size-12" />

        <div
            class="flex size-16 items-center justify-center rounded-2xl bg-accent"
        >
            <component
                :is="detalle.icono"
                class="size-8 text-accent-foreground"
                aria-hidden="true"
            />
        </div>

        <div class="max-w-md">
            <p class="text-sm font-medium text-muted-foreground">
                Error {{ status }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold text-balance">
                {{ detalle.titulo }}
            </h1>
            <p class="mt-3 text-pretty text-muted-foreground">
                {{ detalle.texto }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-2">
            <Button as-child class="touch-target">
                <Link :href="destino">
                    <House class="size-4" aria-hidden="true" />
                    {{ textoDestino }}
                </Link>
            </Button>
            <Button
                v-if="hayHistorial"
                variant="ghost"
                class="touch-target"
                @click="volver"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Volver
            </Button>
        </div>
    </div>
</template>
