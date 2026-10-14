import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { cargarCola } from './cola';
import { iniciarConexion, sinConexion } from './conexion';
import { recienEncolada } from './escribir';
import { controlarUsuario, precalentar } from './precalentar';
import { iniciarSincronizacion, sincronizarAhora } from './sincronizar';

/*
 * Arranque del modo sin conexión. Lo llama app.ts una vez.
 *
 * Qué funciona sin red:
 *   - Navegar todo lo que ya se vio o se precalentó, con la franja que dice de
 *     cuándo son los datos.
 *   - Marcar una toma, registrar un peso, un baño o una nota: van a la cola y
 *     se mandan solos al volver la conexión.
 * Lo demás (visitas, tratamientos, vacunas, fotos, compartir) pide conexión:
 * sus efectos los calcula el servidor y no se pueden adelantar sin inventarlos.
 */

type Compartidas = {
    auth?: { user?: { id: number } | null };
    mascotas?: { id: number }[];
};

let usuarioActual: number | null | undefined;

export function iniciarSinConexion(): void {
    if (typeof window === 'undefined') {
        return;
    }

    iniciarConexion();
    iniciarSincronizacion();

    // Lo último que se supo, para precalentar cuando el SW recién tome control.
    let version: string | null = null;
    let mascotas: number[] = [];

    // Cada página trae el usuario: si cambió (o salió), se borra lo guardado
    // del anterior y se carga su cola. También dispara en la carga inicial.
    router.on('navigate', (evento) => {
        const { page } = evento.detail;
        const props = page.props as Compartidas;
        const id = props.auth?.user?.id ?? null;
        version = page.version ?? null;
        mascotas = (props.mascotas ?? []).map((m) => m.id);

        if (id !== usuarioActual) {
            usuarioActual = id;
            controlarUsuario(id).then(() => {
                cargarCola(id).then(() => sincronizarAhora());

                if (id !== null) {
                    precalentar(version, mascotas);
                }
            });
        }
    });

    /*
     * En la primera visita el SW se instala **después** de que cargó la página,
     * así que en ese `navigate` todavía no controla la pestaña y el precalentado
     * no tiene quién guarde nada. Sin esto, la app recién funcionaría sin red a
     * partir de la segunda vez que se abre: justo la que puede no llegar.
     */
    navigator.serviceWorker?.addEventListener('controllerchange', () => {
        if (usuarioActual) {
            precalentar(version, mascotas);
        }
    });

    // Pantalla que nunca se guardó, sin red: el SW contesta 503 con este header.
    // Un toast en vez del modal de error de Inertia, que mostraría el texto
    // crudo de la respuesta.
    router.on('httpException', (evento) => {
        if (evento.detail.response.headers['x-sin-conexion']) {
            sinConexion.value = true;
            toast.warning('Esta pantalla no está guardada', {
                description:
                    'Abrila una vez con conexión y después vas a poder verla sin red.',
            });

            return false;
        }
    });

    // Cualquier otro envío sin red (una visita, una vacuna, una foto): no se
    // encola —pide conexión— pero tampoco puede fallar en silencio. Lo que sí
    // se encoló ya avisó por su cuenta.
    router.on('networkError', () => {
        sinConexion.value = true;

        if (!recienEncolada()) {
            toast.error('Esto necesita conexión', {
                description:
                    'Sin red se pueden marcar tomas y registrar pesos, baños y notas. Lo demás, cuando vuelva la señal.',
            });
        }

        return false;
    });
}
