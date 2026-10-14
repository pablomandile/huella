import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { sincronizar as rutaSincronizar } from '@/routes';
import { marcarFallidas, pendientes, quitar } from './cola';
import type { Operacion } from './cola';
import { alVolverLaConexion, sinConexion } from './conexion';

/*
 * Manda la cola a POST /sincronizar. Se dispara al arrancar, al volver la
 * conexión, al volver la app a primer plano, cada 30 segundos mientras haya
 * pendientes y apenas se encola algo con conexión.
 *
 * Sin Background Sync: iOS no lo tiene, así que solo corre con la app abierta,
 * y para que se comporte igual en todos los teléfonos tampoco se usa en Android.
 */

const TANDA = 50;
const CADA_MS = 30 * 1000;

export const sincronizando = ref(false);
/** Sesión vencida: los cambios se guardan hasta que vuelva a entrar. */
export const faltaSesion = ref(false);

let corriendo = false;
let otraVez = false;

export function iniciarSincronizacion(): void {
    // Cubre también la vuelta que el sistema no avisa: la detecta la sonda.
    alVolverLaConexion(() => sincronizarAhora());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            sincronizarAhora();
        }
    });
    setInterval(() => {
        if (pendientes.value.length) {
            sincronizarAhora();
        }
    }, CADA_MS);
}

export async function sincronizarAhora(): Promise<void> {
    if (corriendo) {
        otraVez = true;

        return;
    }

    const lote = [...pendientes.value];

    if (!lote.length) {
        return;
    }

    corriendo = true;
    sincronizando.value = true;
    let aplicadas = 0;
    let fallaron = 0;

    try {
        for (let i = 0; i < lote.length; i += TANDA) {
            const resultados = await enviar(lote.slice(i, i + TANDA));
            const ok = resultados.filter((r) => r.estado === 'ok');
            const error = resultados.filter((r) => r.estado === 'error');
            await quitar(ok.map((r) => r.op_id));
            await marcarFallidas(error);
            aplicadas += ok.length;
            fallaron += error.length;
        }

        sinConexion.value = false;
        faltaSesion.value = false;
    } catch (e) {
        if (e instanceof ErrorDeRespuesta && [401, 419].includes(e.status)) {
            if (!faltaSesion.value) {
                toast.warning('Iniciá sesión para sincronizar', {
                    description: 'Lo que cargaste sin conexión sigue guardado.',
                });
            }

            faltaSesion.value = true;
        } else if (!(e instanceof ErrorDeRespuesta)) {
            // Sin respuesta: todavía no hay conexión. Queda para la próxima.
            sinConexion.value = true;
        }
    } finally {
        corriendo = false;
        sincronizando.value = false;
    }

    if (aplicadas) {
        toast.success(
            aplicadas === 1
                ? '1 cambio sincronizado'
                : `${aplicadas} cambios sincronizados`,
        );
        // La pantalla se vuelve a pedir: lo que estaba como pendiente ahora lo
        // trae el servidor, con lo que haya calculado (recordatorios, curva).
        router.reload();
    }

    if (fallaron) {
        toast.error(
            fallaron === 1
                ? 'Un cambio no se pudo guardar'
                : `${fallaron} cambios no se pudieron guardar`,
            { description: 'Tocá el aviso de arriba para ver por qué.' },
        );
    }

    if (otraVez) {
        otraVez = false;
        sincronizarAhora();
    }
}

class ErrorDeRespuesta extends Error {
    constructor(public status: number) {
        super(`HTTP ${status}`);
    }
}

type Resultado = {
    op_id: string;
    estado: 'ok' | 'error';
    errores?: Record<string, string[]>;
};

/** Laravel valida el CSRF de un fetch con la cookie XSRF-TOKEN, en un header. */
function tokenCsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function enviar(lote: Operacion[]): Promise<Resultado[]> {
    const cuerpo = JSON.stringify({
        ops: lote.map(({ op_id, entidad, accion, id, datos }) => ({
            op_id,
            entidad,
            accion,
            id,
            datos,
        })),
    });

    const pedir = () =>
        fetch(rutaSincronizar().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            body: cuerpo,
        });

    let respuesta = await pedir();

    // 419: el token CSRF venció (la app estuvo mucho tiempo abierta). Pedir
    // cualquier página renueva la cookie, y se intenta una vez más.
    if (respuesta.status === 419) {
        await fetch('/dashboard', {
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        });
        respuesta = await pedir();
    }

    if (!respuesta.ok) {
        throw new ErrorDeRespuesta(respuesta.status);
    }

    return ((await respuesta.json()) as { resultados: Resultado[] }).resultados;
}
