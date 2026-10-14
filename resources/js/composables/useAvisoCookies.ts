import { onMounted, readonly, ref } from 'vue';

/**
 * Si el aviso de cookies ya se cerró en este dispositivo.
 *
 * El estado vive a nivel de módulo y no por componente: en la app hay dos
 * instancias del aviso —la apilada sobre la barra del celular y la flotante
 * de escritorio— y cerrar una tiene que cerrar las dos.
 *
 * La marca guarda una **versión**, no un sí/no. Hoy todo lo que se guarda es
 * necesario y el aviso es informativo; si algún día se suma algo que no lo es
 * (analíticas, por ejemplo), se sube `VERSION` y el aviso vuelve a aparecer
 * para todos. Ese cambio además exige pedir permiso antes de cargarlo.
 */
const CLAVE = 'huella:aviso-cookies';
const VERSION = '1';

const visible = ref(false);
let leido = false;

function yaSeCerro(): boolean {
    try {
        return localStorage.getItem(CLAVE) === VERSION;
    } catch {
        // Sin localStorage (modo privado): se muestra, y cerrarlo dura la pestaña.
        return false;
    }
}

function cerrar() {
    visible.value = false;

    try {
        localStorage.setItem(CLAVE, VERSION);
    } catch {
        // Sin almacenamiento el cierre dura lo que dure la pestaña.
    }
}

export function useAvisoCookies() {
    /*
     * Se decide después de montar y no en el primer render: leer localStorage
     * durante el setup haría que quien ya lo cerró lo vea aparecer y
     * desaparecer en cada carga completa. Y una sola vez por pestaña: al
     * navegar se remonta el layout, y releer no cambia nada.
     */
    onMounted(() => {
        if (!leido) {
            leido = true;
            visible.value = !yaSeCerro();
        }
    });

    return { visible: readonly(visible), cerrar };
}
