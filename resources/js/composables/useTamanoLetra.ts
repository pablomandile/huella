import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update } from '@/routes/tamano-letra';

/*
 * El tamaño de letra elegido en Ajustes → Apariencia.
 *
 * Lo decide el servidor (HandleTamanoLetra: cuenta → cookie → default) y lo
 * escribe en el `<html>` de arranque, así que al cargar no hay nada que hacer.
 * Esto cubre el otro momento: cuando la persona elige. Inertia no recarga el
 * documento, así que el atributo lo mueve el cliente en el acto.
 */

export type TamanoLetra = 'pequeno' | 'mediano' | 'grande';

export type OpcionTamanoLetra = {
    valor: TamanoLetra;
    etiqueta: string;
    descripcion: string;
    /** El porcentaje como número (100, 112.5, 125), para dibujar la muestra. */
    escala: number;
};

const POR_DEFECTO: TamanoLetra = 'mediano';

/** Donde lo guardaba la versión anterior, por dispositivo. Solo se lee para migrarlo. */
const CLAVE_VIEJA = 'tamanoLetra';

function aplicarAlDocumento(valor: TamanoLetra): void {
    if (typeof document !== 'undefined') {
        document.documentElement.dataset.tamanoLetra = valor;
    }
}

export function useTamanoLetra() {
    const page = usePage();

    const tamano = computed<TamanoLetra>(
        () =>
            (page.props.tamanoLetra as TamanoLetra | undefined) ?? POR_DEFECTO,
    );

    const opciones = computed<OpcionTamanoLetra[]>(
        () =>
            (page.props.tamanosLetra as OpcionTamanoLetra[] | undefined) ?? [],
    );

    function elegir(valor: TamanoLetra): void {
        if (valor === tamano.value) {
            return;
        }

        // Se aplica antes de que conteste: esperar el viaje se siente trabado.
        // Si falla, `onError` lo deja como estaba.
        const anterior = tamano.value;
        aplicarAlDocumento(valor);

        router.put(
            update.url(),
            { tamano_letra: valor },
            {
                preserveScroll: true,
                // Para que `tamanoLetra` se relea de los props: con el estado
                // preservado, el selector seguiría marcando la opción vieja.
                preserveState: false,
                onError: () => aplicarAlDocumento(anterior),
            },
        );
    }

    /**
     * Antes el tamaño vivía en localStorage. Si quedó una elección de esa época,
     * se pasa a la cuenta una sola vez y se borra, para que nadie la pierda.
     */
    function migrarDeLocalStorage(): void {
        let vieja: string | null = null;

        try {
            vieja = localStorage.getItem(CLAVE_VIEJA);
            localStorage.removeItem(CLAVE_VIEJA);
        } catch {
            return;
        }

        if (vieja === 'pequeno' || vieja === 'grande') {
            elegir(vieja);
        }
    }

    return { tamano, opciones, elegir, migrarDeLocalStorage };
}
