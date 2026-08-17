import { toast } from 'vue-sonner';

/**
 * Registro del service worker y aviso de actualización.
 *
 * Nunca se recarga sola: el usuario puede estar a mitad de cargar una visita.
 * Se le ofrece un toast y decide él cuándo.
 */
export function registrarServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    // En desarrollo el service worker se saltea: cachearía los assets que Vite
    // sirve por HMR y dejaría la pantalla congelada tras cada cambio.
    if (import.meta.env.DEV) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .then((registro) => {
                registro.addEventListener('updatefound', () => {
                    const nuevo = registro.installing;

                    if (!nuevo) {
                        return;
                    }

                    nuevo.addEventListener('statechange', () => {
                        // 'installed' + un SW ya activo = hay versión nueva
                        // esperando. Sin controller es la primera instalación.
                        if (
                            nuevo.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            avisarActualizacion(nuevo);
                        }
                    });
                });
            })
            .catch(() => {
                // Que falle el registro no puede tumbar la app.
            });

        // Cuando un SW NUEVO reemplaza al anterior, recargamos una sola vez.
        // Ojo: en la primera visita el SW también dispara `controllerchange`
        // al tomar control (clients.claim); recargar ahí haría que todo primer
        // ingreso a la app se recargue solo a los dos segundos.
        let habiaController = !!navigator.serviceWorker.controller;
        let recargando = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!habiaController) {
                habiaController = true;

                return;
            }

            if (recargando) {
                return;
            }

            recargando = true;
            window.location.reload();
        });
    });
}

function avisarActualizacion(nuevo: ServiceWorker): void {
    toast('Hay una versión nueva de Huella', {
        description: 'Actualizá cuando termines lo que estás haciendo.',
        duration: Infinity,
        action: {
            label: 'Actualizar',
            onClick: () => nuevo.postMessage('saltar-espera'),
        },
    });
}
