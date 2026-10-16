import { sinConexion } from './conexion';

/*
 * Precalentado: con conexión, se piden en segundo plano las pantallas
 * principales para que el service worker las guarde, y así se puedan abrir sin
 * red aunque nunca se hayan visitado. Sin esto, "sin conexión" solo mostraría lo
 * que el usuario haya tocado antes, que en una app de consulta es poco.
 *
 * Como mucho cada 10 minutos: son unas cinco pantallas por mascota y no tiene
 * sentido repetirlas en cada navegación.
 */

const CADA_MS = 10 * 60 * 1000;
const MARCA = 'sin-conexion:precalentado';

const GENERALES = ['/dashboard', '/medicacion', '/recordatorios', '/mascotas'];

const DE_CADA_MASCOTA = (id: number) => [
    `/mascotas/${id}`,
    `/mascotas/${id}/diario`,
    `/mascotas/${id}/seguimiento`,
    `/mascotas/${id}/preventivo`,
    `/mascotas/${id}/visitas`,
];

function toca(): boolean {
    try {
        return Date.now() - Number(localStorage.getItem(MARCA) || 0) > CADA_MS;
    } catch {
        return true;
    }
}

export async function precalentar(
    version: string | null,
    mascotas: number[],
): Promise<void> {
    if (
        sinConexion.value ||
        enCurso ||
        !navigator.serviceWorker?.controller ||
        !toca()
    ) {
        return;
    }

    enCurso = true;

    try {
        if (await recorrer(version, mascotas)) {
            // Recién al terminar: si se cortó a mitad —una recarga cancela los
            // pedidos en vuelo, o se fue la señal—, el próximo arranque lo
            // reintenta en vez de esperar diez minutos con la mitad guardada.
            try {
                localStorage.setItem(MARCA, String(Date.now()));
            } catch {
                // Ventana privada: precalienta en cada arranque, no es grave.
            }
        }
    } finally {
        enCurso = false;
    }
}

let enCurso = false;

/** Devuelve si llegó al final. */
async function recorrer(
    version: string | null,
    mascotas: number[],
): Promise<boolean> {
    // El bundle de un deploy nuevo: que el SW guarde los chunks que le falten.
    navigator.serviceWorker.controller?.postMessage({ tipo: 'precachear' });

    // El HTML de arranque, para poder abrir la app en frío sin red.
    await fetch('/dashboard', {
        credentials: 'same-origin',
        headers: { Accept: 'text/html', 'X-Precalentado': '1' },
    }).catch(() => {});

    // De a una: así no compiten con lo que el usuario esté haciendo.
    for (const url of [...GENERALES, ...mascotas.flatMap(DE_CADA_MASCOTA)]) {
        const ok = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': version ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                // Lo reconoce `AceptoTerminos`: un pedido de fondo no puede
                // pisar a dónde volver después de aceptar los términos.
                'X-Precalentado': '1',
                Accept: 'text/html, application/xhtml+xml',
            },
        })
            .then((r) => r.ok)
            .catch(() => false);

        // 409: hubo un deploy y esta pestaña tiene el bundle viejo; se reintenta
        // en el próximo arranque. Sin red, no tiene sentido seguir.
        if (!ok) {
            return false;
        }
    }

    return true;
}

/*
 * Cambio de usuario (o salir de la cuenta): lo guardado es del anterior y no
 * puede quedar a mano del próximo que use el teléfono. Son datos clínicos.
 */
const USUARIO = 'sin-conexion:usuario';

export async function controlarUsuario(id: number | null): Promise<void> {
    let anterior: string | null = null;

    try {
        anterior = localStorage.getItem(USUARIO);

        if (id === null) {
            localStorage.removeItem(USUARIO);
        } else {
            localStorage.setItem(USUARIO, String(id));
        }
    } catch {
        // Sin localStorage no se puede saber: se borra por las dudas.
        anterior = '?';
    }

    if (anterior !== (id === null ? null : String(id))) {
        await borrarDatosGuardados();
    }
}

/** Espera a que el SW termine: si no, el precalentado podría guardar algo que el borrado se lleve. */
function borrarDatosGuardados(): Promise<void> {
    try {
        localStorage.removeItem(MARCA);
    } catch {
        // nada
    }

    const sw = navigator.serviceWorker?.controller;

    if (!sw) {
        return Promise.resolve();
    }

    return new Promise((resolver) => {
        const canal = new MessageChannel();
        canal.port1.onmessage = () => resolver();
        sw.postMessage({ tipo: 'borrar-datos' }, [canal.port2]);
        // Un SW colgado no traba el arranque.
        setTimeout(resolver, 3000);
    });
}
