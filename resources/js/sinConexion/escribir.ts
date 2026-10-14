import { router } from '@inertiajs/vue3';
import type { PendingVisit } from '@inertiajs/core';
import { toast } from 'vue-sonner';
import { encolar, pendientes } from './cola';
import type { NuevaOperacion } from './cola';
import { sinConexion } from './conexion';
import { sincronizarAhora } from './sincronizar';

/*
 * El único camino para las cuatro escrituras de todos los días, con o sin red.
 *
 * Con conexión y la cola vacía, es el envío Inertia de siempre: validación,
 * mensajes y redirecciones como antes. Si no hay conexión, si el envío se cae
 * por la red, o si todavía hay cambios esperando para salir —para no
 * adelantarse a ellos: marcar y desmarcar una toma tiene que llegar en orden—,
 * se encola y la pantalla sigue como si hubiera salido bien.
 */

/** El avisador global de "esto necesita conexión" se calla un momento después de encolar. */
let ultimaEncolada = 0;
export const recienEncolada = () => Date.now() - ultimaEncolada < 1500;

const hayQueEncolar = () => sinConexion.value || pendientes.value.length > 0;

async function guardarEnCola(op: NuevaOperacion): Promise<void> {
    ultimaEncolada = Date.now();
    await encolar(op);

    if (sinConexion.value) {
        toast.info('Guardado sin conexión', {
            description: 'Se manda solo cuando vuelva la conexión.',
        });
    } else {
        sincronizarAhora();
    }
}

/** Los campos de un formulario, como objeto plano. */
function datosDe(visita: PendingVisit): Record<string, unknown> {
    const { data } = visita;

    return data instanceof FormData
        ? Object.fromEntries(data.entries())
        : { ...(data as Record<string, unknown>) };
}

/**
 * Para el `<Form>` de Inertia:
 *
 *     const peso = encolable((datos) => ({ entidad: 'peso', … }), () => (abierto = false));
 *     <Form :on-before="peso.antes" :on-network-error="peso.errorDeRed" … >
 *
 * `armar` traduce los campos del formulario a la operación; `alEncolar` hace lo
 * mismo que haría el `@success` (cerrar el sheet, limpiar), porque encolado no
 * dispara ningún evento de Inertia.
 */
export function encolable(
    armar: (datos: Record<string, unknown>) => NuevaOperacion,
    alEncolar: () => void,
) {
    let enVuelo: Record<string, unknown> | null = null;

    return {
        antes(visita: PendingVisit): boolean | void {
            const datos = datosDe(visita);

            if (hayQueEncolar()) {
                guardarEnCola(armar(datos)).then(alEncolar, avisarQueNoSePudo);

                // false cancela el envío de Inertia.
                return false;
            }

            enVuelo = datos;
        },

        errorDeRed(): boolean | void {
            if (!enVuelo) {
                return;
            }

            const datos = enVuelo;
            enVuelo = null;
            sinConexion.value = true;
            guardarEnCola(armar(datos)).then(alEncolar, avisarQueNoSePudo);

            // Lo manejamos acá: que Inertia no lo trate como un error de la página.
            return false;
        },
    };
}

/** Marcar una toma, con o sin conexión. */
export function marcarToma(
    url: string,
    toma: { id: number; resumen: string },
    datos: { estado: string },
): void {
    // La hora del toque viaja con la operación: si sincroniza a la noche, la
    // toma igual tiene que figurar a la hora en que se dio.
    const conHora = {
        ...datos,
        fecha_hora_real:
            datos.estado === 'administrada' ? new Date().toISOString() : null,
    };
    const op: NuevaOperacion = {
        entidad: 'toma',
        accion: 'marcar',
        id: toma.id,
        datos: conHora,
        resumen: toma.resumen,
    };

    if (hayQueEncolar()) {
        guardarEnCola(op).catch(avisarQueNoSePudo);

        return;
    }

    router.patch(url, conHora, {
        preserveScroll: true,
        preserveState: true,
        onNetworkError: () => {
            sinConexion.value = true;
            guardarEnCola(op).catch(avisarQueNoSePudo);

            return false;
        },
    });
}

function avisarQueNoSePudo(): void {
    toast.error('No se pudo guardar sin conexión', {
        description:
            'Este navegador no deja guardar datos. Probá de nuevo con conexión.',
    });
}
