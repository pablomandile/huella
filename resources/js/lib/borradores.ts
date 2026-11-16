/*
 * Borradores de formularios, guardados en el teléfono.
 *
 * Es lo que salva una visita a medio cargar cuando el dedo resbala hacia
 * "atrás", el sistema mata la app en segundo plano o se recarga la página. Va
 * en localStorage y no en el servidor: tiene que andar sin conexión —la
 * veterinaria es justo donde no hay señal— y un borrador es de este teléfono,
 * no un dato de la historia clínica.
 *
 * Son datos clínicos en un teléfono que puede ser compartido, así que duran
 * poco (`VIGENCIA_DIAS`) y se borran todos al salir de la cuenta, junto con lo
 * guardado para usar sin conexión (`precalentar.ts`).
 *
 * Los archivos no entran: localStorage solo guarda texto, y una foto de una
 * receta en base64 se come el cupo entero del navegador.
 */

const PREFIJO = 'huella:borrador:';
const VIGENCIA_DIAS = 7;

export type CamposBorrador = [string, string][];

export type Borrador = {
    /** ISO, del reloj del teléfono: solo sirve para decir "de hace un rato". */
    guardado: string;
    campos: CamposBorrador;
};

export function leerBorrador(clave: string): Borrador | null {
    try {
        const crudo = localStorage.getItem(PREFIJO + clave);

        if (!crudo) {
            return null;
        }

        const borrador = JSON.parse(crudo) as Borrador;
        const edad = Date.now() - new Date(borrador.guardado).getTime();

        if (!(edad < VIGENCIA_DIAS * 86_400_000)) {
            localStorage.removeItem(PREFIJO + clave);

            return null;
        }

        return borrador;
    } catch {
        return null;
    }
}

/** Devuelve si se pudo guardar: sin localStorage (modo privado) no hay borrador. */
export function guardarBorrador(
    clave: string,
    campos: CamposBorrador,
): boolean {
    const llave = `huella:borrador:${clave}`;
    const borrador: Borrador = { guardado: new Date().toISOString(), campos };

    try {
        localStorage.setItem(llave, JSON.stringify(borrador));

        return true;
    } catch {
        return false;
    }
}

export function borrarBorrador(clave: string): void {
    try {
        localStorage.removeItem(PREFIJO + clave);
    } catch {
        // Sin localStorage tampoco había nada guardado.
    }
}

/** Al salir de la cuenta o cambiar de usuario: lo del anterior no queda a mano. */
export function borrarTodosLosBorradores(): void {
    try {
        Object.keys(localStorage)
            .filter((llave) => llave.startsWith(PREFIJO))
            .forEach((llave) => localStorage.removeItem(llave));
    } catch {
        // nada
    }
}

/**
 * Lo cargado en un formulario, como pares nombre/valor. Sin archivos (no se
 * pueden guardar) ni `_method`, que lo pone el propio formulario.
 */
export function camposDelFormulario(form: HTMLFormElement): CamposBorrador {
    const campos: CamposBorrador = [];

    new FormData(form).forEach((valor, nombre) => {
        if (typeof valor === 'string' && nombre !== '_method') {
            campos.push([nombre, valor]);
        }
    });

    return campos;
}

/** Si el formulario tiene algún archivo elegido: esos no viajan en el borrador. */
export function tieneArchivos(form: HTMLFormElement): boolean {
    return [...new FormData(form).values()].some(
        (valor) => valor instanceof File && valor.size > 0,
    );
}
