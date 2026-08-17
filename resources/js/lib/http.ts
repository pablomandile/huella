/**
 * POST en JSON, por fuera de Inertia.
 *
 * Se usa para el alta al vuelo desde un combo: el usuario está a mitad de un
 * formulario y necesita sumar una veterinaria que no tenía cargada. Una
 * navegación Inertia le vaciaría lo que ya venía escribiendo, así que ese
 * único caso va por `fetch` y el controlador contesta JSON.
 *
 * Para todo lo demás se usa Inertia, que ya maneja CSRF, errores y estado.
 */

export type ErroresValidacion = Record<string, string[]>;

export class ErrorDeValidacion extends Error {
    constructor(public readonly errores: ErroresValidacion) {
        super('Revisá los datos cargados.');
        this.name = 'ErrorDeValidacion';
    }

    /** Primer mensaje de cada campo, que es lo que se muestra bajo el input. */
    get porCampo(): Record<string, string> {
        return Object.fromEntries(
            Object.entries(this.errores).map(([campo, mensajes]) => [
                campo,
                mensajes[0],
            ]),
        );
    }
}

/** Laravel firma la cookie XSRF-TOKEN; hay que devolverla en el header. */
function tokenCsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

export async function postJson<T>(
    url: string,
    datos: Record<string, unknown>,
): Promise<T> {
    const respuesta = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            // Sin esto Laravel contestaría una redirección de Inertia.
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': tokenCsrf(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(datos),
    });

    if (respuesta.status === 422) {
        const cuerpo = (await respuesta.json()) as {
            errors: ErroresValidacion;
        };

        throw new ErrorDeValidacion(cuerpo.errors ?? {});
    }

    if (!respuesta.ok) {
        throw new Error(
            respuesta.status === 419
                ? 'Se venció la sesión. Recargá la página e intentá de nuevo.'
                : 'No se pudo guardar. Revisá la conexión e intentá de nuevo.',
        );
    }

    return (await respuesta.json()) as T;
}
