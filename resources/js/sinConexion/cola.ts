import { openDB } from 'idb';
import type { IDBPDatabase } from 'idb';
import { computed, ref } from 'vue';

/*
 * La cola de lo que se registró sin conexión, en IndexedDB para que sobreviva a
 * cerrar la app —con el celular en la mano en la veterinaria, se cierra—.
 *
 * Solo las cuatro escrituras de todos los días: marcar una toma, un peso, un
 * baño, una nota. Cada operación es lo que después va a POST /sincronizar
 * ({op_id, entidad, accion, id, datos}), más lo que la app necesita mientras
 * espera: un `resumen` para listarla, la mascota, y su estado.
 */

export type Entidad = 'toma' | 'peso' | 'bano' | 'entrada';

export type Operacion = {
    op_id: string;
    usuario_id: number;
    /** Orden en que se hizo: se sincroniza en ese orden. */
    orden: number;
    creada: string;
    entidad: Entidad;
    accion: 'marcar' | 'crear';
    /** La toma, o la mascota del peso / baño / nota. */
    id: number;
    datos: Record<string, unknown>;
    /** "Peso 18,4 kg · Greta", para la lista de pendientes. */
    resumen: string;
    estado: 'pendiente' | 'error';
    errores: Record<string, string[]> | null;
};

export type NuevaOperacion = Pick<
    Operacion,
    'entidad' | 'accion' | 'id' | 'datos' | 'resumen'
>;

let base: Promise<IDBPDatabase> | null = null;

function abrir(): Promise<IDBPDatabase> | null {
    if (typeof indexedDB === 'undefined') {
        return null;
    }

    base ??= openDB('huella-sin-conexion', 1, {
        upgrade(db) {
            db.createObjectStore('cola', { keyPath: 'op_id' });
        },
    });

    return base;
}

let usuarioId: number | null = null;
let contador = 0;

/** Las operaciones del usuario actual, en el orden en que se hicieron. */
export const cola = ref<Operacion[]>([]);
export const pendientes = computed(() =>
    cola.value.filter((op) => op.estado === 'pendiente'),
);
export const fallidas = computed(() =>
    cola.value.filter((op) => op.estado === 'error'),
);

export async function cargarCola(id: number | null): Promise<void> {
    usuarioId = id;
    await refrescar();
}

async function refrescar(): Promise<void> {
    const db = await abrir();

    if (!db || usuarioId === null) {
        cola.value = [];

        return;
    }

    const todas = (await db.getAll('cola')) as Operacion[];
    // Por usuario: un teléfono compartido no le manda a uno lo que cargó otro.
    cola.value = todas
        .filter((op) => op.usuario_id === usuarioId)
        .sort((a, b) => a.orden - b.orden);
}

// IndexedDB no puede guardar los proxies reactivos de Vue: copia plana.
const plano = <T>(valor: T): T => JSON.parse(JSON.stringify(valor));

/**
 * Suma un cambio a la cola. Marcar dos veces la misma toma deja una sola
 * operación, la última: la cola no crece con cada toque.
 */
export async function encolar(nueva: NuevaOperacion): Promise<void> {
    const db = await abrir();

    if (!db || usuarioId === null) {
        throw new Error('No se puede guardar sin conexión en este navegador.');
    }

    if (nueva.entidad === 'toma') {
        const previa = cola.value.find(
            (op) => op.entidad === 'toma' && op.id === nueva.id,
        );

        if (previa) {
            await db.put('cola', {
                ...plano(previa),
                datos: plano(nueva.datos),
                resumen: nueva.resumen,
                estado: 'pendiente',
                errores: null,
            });
            await refrescar();

            return;
        }
    }

    const op: Operacion = {
        ...plano(nueva),
        op_id: crypto.randomUUID(),
        usuario_id: usuarioId,
        orden: Date.now() * 1000 + (contador++ % 1000),
        creada: new Date().toISOString(),
        estado: 'pendiente',
        errores: null,
    };
    await db.put('cola', op);
    await refrescar();
}

export async function quitar(ids: string[]): Promise<void> {
    const db = await abrir();

    if (!db || !ids.length) {
        return;
    }

    for (const id of ids) {
        await db.delete('cola', id);
    }

    await refrescar();
}

export async function marcarFallidas(
    resultados: { op_id: string; errores?: Record<string, string[]> }[],
): Promise<void> {
    const db = await abrir();

    if (!db || !resultados.length) {
        return;
    }

    for (const { op_id, errores } of resultados) {
        const op = (await db.get('cola', op_id)) as Operacion | undefined;

        if (op) {
            await db.put('cola', {
                ...op,
                estado: 'error',
                errores: errores ?? null,
            });
        }
    }

    await refrescar();
}

/** "Reintentar": vuelve a la cola normal. */
export async function reintentar(opId: string): Promise<void> {
    const db = await abrir();
    const op = db
        ? ((await db.get('cola', opId)) as Operacion | undefined)
        : undefined;

    if (db && op) {
        await db.put('cola', { ...op, estado: 'pendiente', errores: null });
        await refrescar();
    }
}

/** "Descartar": se pierde, a propósito. */
export function descartar(opId: string): Promise<void> {
    return quitar([opId]);
}
