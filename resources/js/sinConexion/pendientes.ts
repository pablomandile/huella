import { computed } from 'vue';
import { cola } from './cola';
import type { Entidad, Operacion } from './cola';

/*
 * Lo encolado, listo para mostrarse en pantalla antes de sincronizar: el peso
 * que se acaba de cargar tiene que aparecer en la lista ya, no cuando vuelva la
 * señal. Va marcado como "sin sincronizar" porque todavía no es un dato del
 * servidor —y lo que el servidor rechace no se muestra como hecho—.
 *
 * Al sincronizar, la cola se vacía y la pantalla se recarga del servidor: esto
 * nunca reemplaza a los datos de verdad, solo los adelanta.
 */

const vigentes = computed(() =>
    cola.value.filter((op) => op.estado === 'pendiente'),
);

/** Las operaciones pendientes de una entidad para una mascota (o toma). */
export function pendientesDe(entidad: Entidad, id?: number) {
    return computed<Operacion[]>(() =>
        vigentes.value.filter(
            (op) =>
                op.entidad === entidad && (id === undefined || op.id === id),
        ),
    );
}

/** Estado pendiente de cada toma marcada sin conexión: id => estado. */
export const tomasMarcadas = computed(
    () =>
        new Map(
            vigentes.value
                .filter((op) => op.entidad === 'toma')
                .map((op) => [op.id, String(op.datos.estado)]),
        ),
);
