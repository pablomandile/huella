import { ref } from 'vue';

/*
 * Si la app está sin conexión, y de cuándo son los datos que muestra.
 *
 * `navigator.onLine` solo sabe si hay una red conectada, no si llega al
 * servidor: el wifi de la veterinaria sin internet, los datos agotados. Por eso
 * cuentan también otras tres señales:
 *
 *   - el service worker avisa si la última pantalla salió de lo guardado;
 *   - al arrancar se le pregunta si la página misma salió de una copia (en una
 *     carga en frío no hubo ningún pedido que fallara, y sin esto la app
 *     mostraría datos viejos sin decirlo);
 *   - mientras se está sin conexión, una sonda liviana a `/up`, porque si el
 *     teléfono nunca creyó perder la red tampoco va a avisar que volvió, y la
 *     franja no se iría nunca.
 */

export const sinConexion = ref(
    typeof navigator !== 'undefined' && navigator.onLine === false,
);

/** ISO de cuándo se guardó la copia que se está mostrando, si se sabe. */
export const datosDel = ref<string | null>(null);

const SONDA_CADA_MS = 30 * 1000;

/** Se llama cuando vuelve la conexión: lo usa la sincronización. */
let alVolver: () => void = () => {};

export function alVolverLaConexion(funcion: () => void): void {
    alVolver = funcion;
}

function marcarConectado(): void {
    const estabaSin = sinConexion.value;
    sinConexion.value = false;
    datosDel.value = null;

    if (estabaSin) {
        alVolver();
    }
}

export function iniciarConexion(): void {
    window.addEventListener('online', marcarConectado);
    window.addEventListener('offline', () => (sinConexion.value = true));

    navigator.serviceWorker?.addEventListener('message', (evento) => {
        if (evento.data?.tipo !== 'origen-datos') {
            return;
        }

        if (evento.data.sinConexion) {
            sinConexion.value = true;
            datosDel.value = evento.data.guardadoEn ?? datosDel.value;
        } else {
            marcarConectado();
        }
    });
    // Con addEventListener los mensajes del SW quedan en espera hasta pedir
    // explícitamente que empiecen a llegar.
    navigator.serviceWorker?.startMessages();

    preguntarComoArranco();

    setInterval(() => {
        if (sinConexion.value) {
            sondear();
        }
    }, SONDA_CADA_MS);
}

/** ¿Esta página la sirvió la red, o una copia guardada? */
function preguntarComoArranco(): void {
    const sw = navigator.serviceWorker?.controller;

    if (!sw) {
        return;
    }

    const canal = new MessageChannel();
    canal.port1.onmessage = (evento) => {
        const copia = evento.data as { guardadoEn: string | null } | null;

        if (copia) {
            sinConexion.value = true;
            datosDel.value = copia.guardadoEn;
        }
    };
    sw.postMessage({ tipo: 'como-arranque' }, [canal.port2]);
}

/** Un pedido mínimo al servidor: si contesta, hay conexión de verdad. */
async function sondear(): Promise<void> {
    try {
        const respuesta = await fetch('/up', {
            method: 'HEAD',
            cache: 'no-store',
            credentials: 'same-origin',
        });

        if (respuesta.ok) {
            marcarConectado();
        }
    } catch {
        // Sigue sin conexión: se vuelve a probar en 30 segundos.
    }
}
