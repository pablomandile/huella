import { ref } from 'vue';

/*
 * El tamaño de letra elegido en Ajustes → Apariencia.
 *
 * Se guarda por dispositivo en localStorage, igual que el tema: es una
 * preferencia de cómo se lee en esta pantalla, no un dato de la cuenta —en la
 * compu se puede querer pequeño y en el celular grande—. El script inline del
 * <head> de app.blade.php lo aplica antes del primer pintado; esto solo lo
 * cambia en vivo y lo recuerda.
 *
 * Mediano es el default y se representa SIN atributo en <html>: así, si el
 * almacenamiento falla, la app queda en el tamaño pensado para todos.
 */

export type TamanoLetra = 'pequeno' | 'mediano' | 'grande';

export const TAMANOS_LETRA: { valor: TamanoLetra; etiqueta: string }[] = [
    { valor: 'pequeno', etiqueta: 'Pequeño' },
    { valor: 'mediano', etiqueta: 'Mediano' },
    { valor: 'grande', etiqueta: 'Grande' },
];

const CLAVE = 'tamanoLetra';

function leer(): TamanoLetra {
    try {
        const guardado = localStorage.getItem(CLAVE);

        return guardado === 'pequeno' || guardado === 'grande'
            ? guardado
            : 'mediano';
    } catch {
        return 'mediano';
    }
}

function aplicar(valor: TamanoLetra): void {
    if (valor === 'mediano') {
        delete document.documentElement.dataset.tamanoLetra;
    } else {
        document.documentElement.dataset.tamanoLetra = valor;
    }
}

// Un solo estado para toda la app, como el tema.
const tamano = ref<TamanoLetra>(
    typeof window === 'undefined' ? 'mediano' : leer(),
);

export function useTamanoLetra() {
    function elegir(valor: TamanoLetra): void {
        tamano.value = valor;
        aplicar(valor);

        try {
            localStorage.setItem(CLAVE, valor);
        } catch {
            // Ventana privada o datos bloqueados: vale hasta recargar.
        }
    }

    return { tamano, elegir };
}
