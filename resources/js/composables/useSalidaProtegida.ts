import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import type { Borrador } from '@/lib/borradores';
import {
    borrarBorrador,
    camposDelFormulario,
    guardarBorrador,
    tieneArchivos,
} from '@/lib/borradores';

/*
 * Que irse de un formulario a medio cargar pregunte antes.
 *
 * En el celular el "atrás" es un gesto que se hace sin querer, y en una SPA se
 * lleva todo lo cargado sin preguntar: Inertia restaura la página anterior y el
 * formulario se desmonta. Hay dos puertas de salida y cada una se ataja
 * distinto:
 *
 * - **Enlaces** (menú, barra inferior, migas): Inertia dispara `before` y se
 *   puede cancelar. Se cancela, se pregunta, y si confirma se repite la visita.
 *
 * - **El "atrás" del navegador**: `popstate` no se puede cancelar, ya ocurrió.
 *   Por eso, al primer toque en el formulario se apila una entrada "guardia"
 *   con la misma URL: el "atrás" consume la guardia sin cambiar de pantalla, y
 *   el listener frena a Inertia y pregunta. Si sigue cargando, se vuelve a
 *   apilar la guardia.
 *
 * Frenar a Inertia exige correr **antes** que su listener de `popstate`, y eso
 * no lo da `capture`: en `window` los listeners corren en orden de registro, y
 * el de Inertia se registra al arrancar la app. Por eso hay uno solo, global,
 * que `app.ts` instala antes de `createInertiaApp` (`instalarGuardiaDeSalida`),
 * y el formulario montado le presta su manejador. Sin eso Inertia restaura la
 * página encima de la pregunta, y lo único que salva lo cargado es el borrador
 * automático: se probó.
 *
 * La guardia se apila con un toque del usuario y no al montar a propósito:
 * Chrome saltea en el "atrás" las entradas apiladas sin interacción (es su
 * defensa contra las páginas que secuestran el botón), y la guardia no
 * serviría.
 *
 * Mientras la guardia está puesta, guardar o salir por un enlace **reemplaza**
 * la entrada en vez de apilar otra: si no, el historial quedaría con dos veces
 * el formulario y el "atrás" desde la pantalla siguiente pediría dos toques.
 *
 * Las salidas que no pasan por la pregunta —un segundo "atrás" con la pregunta
 * abierta, recargar, que el sistema mate la app en segundo plano— guardan un
 * borrador solas. Lo único que descarta lo cargado es elegir «Salir sin
 * guardar».
 */

/** El manejador del formulario montado, si hay uno. */
let alVolverActivo: ((evento: PopStateEvent) => void) | null = null;

/** Va en `app.ts`, antes de `createInertiaApp`: tiene que quedar primero en la fila. */
export function instalarGuardiaDeSalida(): void {
    window.addEventListener('popstate', (evento) => alVolverActivo?.(evento), {
        capture: true,
    });
}

type Pendiente =
    { tipo: 'historial' } | { tipo: 'visita'; visita: PendingVisit };

export function useSalidaProtegida(opciones: {
    /** Contenedor del <form>: los eventos de sus campos burbujean hasta acá. */
    contenedor: Ref<HTMLElement | null>;
    clave: () => string;
    /** El borrador que había al entrar: «Salir sin guardar» lo deja como estaba. */
    borradorPrevio: () => Borrador | null;
    /** Adónde ir si el formulario fue la primera pantalla y no hay "atrás". */
    salidaSinHistorial: () => string;
}) {
    const preguntando = ref(false);
    const conArchivos = ref(false);
    /** Hay una entrada guardia arriba en el historial. Reactiva: va a `:options` del <Form>. */
    const guardia = ref(false);

    let huellaInicial = '';
    let decidido = false;
    let reenviando = false;
    let montado = false;
    let pendiente: Pendiente | null = null;

    const formulario = () =>
        opciones.contenedor.value?.querySelector('form') ?? null;

    /*
     * Lo cargado, comparable. Los vacíos no cuentan: abrir «Más datos» o un
     * bloque de medicamento suma campos vacíos, y eso no es haber cargado nada.
     */
    function huella(): string {
        const form = formulario();

        if (!form) {
            return '';
        }

        const campos = camposDelFormulario(form)
            .filter(([, valor]) => valor.trim() !== '')
            .map(([nombre, valor]) => `${nombre}=${valor}`)
            .sort();

        return JSON.stringify([campos, tieneArchivos(form)]);
    }

    const sucio = () => !decidido && huella() !== huellaInicial;

    /** Lo cargado hasta acá pasa a ser el punto de partida (al montar o al descartar un borrador). */
    function tomarPuntoDePartida() {
        nextTick(() => {
            huellaInicial = huella();
        });
    }

    function apilarGuardia(evento?: Event) {
        if (guardia.value || decidido) {
            return;
        }

        // El botón de enviar no: el <Form> lee `replace` en ese mismo click,
        // antes de que Vue alcance a pasarle la guardia recién puesta.
        const tocado = evento?.target;

        if (tocado instanceof Element && tocado.closest('[type="submit"]')) {
            return;
        }

        window.history.pushState(
            window.history.state,
            '',
            window.location.href,
        );
        guardia.value = true;
    }

    function alVolver(evento: PopStateEvent) {
        if (!guardia.value) {
            return;
        }

        // El "atrás" consumió la guardia: seguimos en la misma pantalla, y
        // Inertia no tiene que enterarse o restauraría el formulario vacío.
        guardia.value = false;
        evento.stopImmediatePropagation();

        if (!sucio()) {
            irAtras();

            return;
        }

        preguntar({ tipo: 'historial' });
    }

    function alNavegar(evento: CustomEvent<{ visit: PendingVisit }>) {
        const visita = evento.detail.visit;

        if (
            reenviando ||
            decidido ||
            visita.prefetch ||
            visita.method !== 'get' ||
            visita.only.length > 0 ||
            visita.except.length > 0
        ) {
            return;
        }

        if (sucio()) {
            preguntar({ tipo: 'visita', visita });

            return false;
        }

        // Nada que perder, pero con la guardia puesta hay que reemplazarla.
        if (guardia.value) {
            repetir(visita);

            return false;
        }
    }

    function preguntar(motivo: Pendiente) {
        const form = formulario();

        pendiente = motivo;
        conArchivos.value = form ? tieneArchivos(form) : false;
        preguntando.value = true;
    }

    function repetir(visita: PendingVisit) {
        reenviando = true;
        router.visit(visita.url, {
            method: visita.method,
            data: visita.data,
            preserveScroll: visita.preserveScroll,
            preserveState: visita.preserveState,
            replace: guardia.value || visita.replace,
            onFinish: () => {
                reenviando = false;
            },
        });
    }

    function irAtras() {
        // Si el formulario abrió la app no hay "atrás": ahí se va a la salida
        // de siempre. Cualquier `popstate` dice que el "atrás" sí ocurrió.
        const plan = window.setTimeout(() => {
            if (montado) {
                router.visit(opciones.salidaSinHistorial(), { replace: true });
            }
        }, 400);

        window.addEventListener('popstate', () => window.clearTimeout(plan), {
            once: true,
        });
        window.history.back();
    }

    function continuar() {
        const motivo = pendiente;

        pendiente = null;
        preguntando.value = false;

        if (motivo?.tipo === 'visita') {
            repetir(motivo.visita);
        } else {
            irAtras();
        }
    }

    function guardarYSalir() {
        const form = formulario();

        if (
            form &&
            guardarBorrador(opciones.clave(), camposDelFormulario(form))
        ) {
            toast.success(
                'Guardamos el borrador. Lo vas a encontrar al volver.',
            );
        } else {
            toast.error('No se pudo guardar el borrador en este teléfono.');

            return;
        }

        decidido = true;
        continuar();
    }

    function salirSinGuardar() {
        // Lo que se haya guardado solo (al pasar a otra app) tampoco va.
        const previo = opciones.borradorPrevio();

        if (previo) {
            guardarBorrador(opciones.clave(), previo.campos);
        } else {
            borrarBorrador(opciones.clave());
        }

        decidido = true;
        continuar();
    }

    function seguirCargando() {
        pendiente = null;
        preguntando.value = false;
        // Es un toque del usuario: la guardia nueva no la saltea Chrome. Si el
        // "atrás" venía de un enlace, la guardia sigue puesta y no se toca.
        apilarGuardia();
    }

    /** Para el `@success` del <Form>: lo guardado de verdad ya no es borrador. */
    function alGuardar() {
        decidido = true;
        borrarBorrador(opciones.clave());
    }

    function guardarSiHaceFalta(): boolean {
        const form = formulario();

        if (!form || !sucio()) {
            return false;
        }

        return guardarBorrador(opciones.clave(), camposDelFormulario(form));
    }

    function alOcultarse() {
        if (document.visibilityState === 'hidden') {
            guardarSiHaceFalta();
        }
    }

    let dejarDeEscuchar: VoidFunction = () => {};

    onMounted(() => {
        montado = true;
        tomarPuntoDePartida();

        const contenedor = opciones.contenedor.value;

        ['input', 'change', 'click'].forEach((tipo) =>
            contenedor?.addEventListener(tipo, apilarGuardia),
        );
        alVolverActivo = alVolver;
        window.addEventListener('pagehide', guardarSiHaceFalta);
        document.addEventListener('visibilitychange', alOcultarse);
        dejarDeEscuchar = router.on('before', alNavegar);
    });

    onBeforeUnmount(() => {
        montado = false;

        // Se va sin haber elegido (un segundo "atrás" con la pregunta abierta).
        if (guardarSiHaceFalta()) {
            toast.info('Guardamos lo que cargaste como borrador.');
        }

        if (alVolverActivo === alVolver) {
            alVolverActivo = null;
        }

        window.removeEventListener('pagehide', guardarSiHaceFalta);
        document.removeEventListener('visibilitychange', alOcultarse);
        dejarDeEscuchar();
    });

    return {
        preguntando,
        conArchivos,
        guardia,
        guardarYSalir,
        salirSinGuardar,
        seguirCargando,
        alGuardar,
        tomarPuntoDePartida,
    };
}
