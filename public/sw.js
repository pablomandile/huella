/*
 * Service worker de Huella.
 *
 * Decisión deliberada: NO se cachean datos. Las respuestas de Inertia son
 * información clínica —dosis, fechas de refuerzo, pesos— y mostrar una dosis
 * vieja es peor que mostrar un cartel de "sin conexión".
 *
 * Regla: cache-first SOLO para URLs con hash de contenido en el nombre
 * (/build/app-A1b2C3.js). Cualquier URL fija —íconos, manifest, imágenes
 * subidas— va por network-first, o queda congelada para siempre.
 *
 * Al cambiar un ícono hay que tocar TRES lugares a la vez:
 *   1. el número de CACHE de acá abajo
 *   2. el ?v= de los <link rel="icon"> del blade
 *   3. el ?v= de los "src" del manifest
 */
const CACHE = 'huella-v2';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/manifest.webmanifest']))
            .catch(() => {}),
    );
    // Acá NO va self.skipWaiting(): el SW nuevo tiene que quedar en espera
    // para que la app muestre el aviso de actualización. Activarlo lo decide
    // el usuario (mensaje 'saltar-espera'); nunca se actualiza en silencio.
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((claves) =>
                Promise.all(
                    claves
                        .filter((clave) => clave !== CACHE)
                        .map((clave) => caches.delete(clave)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// La app avisa por acá cuando el usuario acepta actualizar.
self.addEventListener('message', (event) => {
    if (event.data === 'saltar-espera') {
        self.skipWaiting();
    }
});

const tieneHashDeContenido = (url) => url.pathname.startsWith('/build/');

const esNavegacion = (request) =>
    request.mode === 'navigate' ||
    (request.method === 'GET' &&
        (request.headers.get('accept') || '').includes('text/html'));

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Los assets de Vite llevan el hash en el nombre: si la URL existe, el
    // contenido no cambió nunca. Servirlos de caché es seguro y es lo único
    // que hace que la app abra rápido.
    if (tieneHashDeContenido(url)) {
        event.respondWith(
            caches.match(request).then(
                (cacheada) =>
                    cacheada ||
                    fetch(request).then((respuesta) => {
                        if (respuesta.ok) {
                            const copia = respuesta.clone();
                            caches
                                .open(CACHE)
                                .then((cache) => cache.put(request, copia))
                                .catch(() => {});
                        }
                        return respuesta;
                    }),
            ),
        );
        return;
    }

    // Navegaciones: cartel propio en vez del dinosaurio si no hay conexión, y
    // rescate si la caché del navegador contestó con la variante JSON.
    if (esNavegacion(request)) {
        event.respondWith(
            fetch(request)
                .then((respuesta) => {
                    /*
                     * Una navegación no puede recibir la variante JSON de una
                     * página: la misma URL devuelve HTML o JSON según el header
                     * `X-Inertia`, y el CDN de Hostinger borra el `Vary` que las
                     * distingue, así que el navegador las confunde. El servidor
                     * ya manda `no-store` para que no vuelva a pasar, pero las
                     * entradas guardadas de antes siguen ahí, y con el JSON en
                     * pantalla la app no arranca: ningún script de la página
                     * puede repararlo, solo esto.
                     *
                     * Dos condiciones, y las dos hacen falta:
                     *
                     * `request.mode === 'navigate'` porque `esNavegacion()` de
                     * acá arriba también da true para los XHR de Inertia —el
                     * router manda `Accept: text/html`—, y "arreglarles" la
                     * respuesta les devolvería el HTML de arranque en vez del
                     * JSON de la página: la SPA dejaría de navegar.
                     *
                     * Y el header `X-Inertia` de la **respuesta** en vez del
                     * content-type, porque `/mis-datos` es una navegación que
                     * contesta JSON de verdad y la pediríamos dos veces. Ese
                     * header solo lo trae una respuesta armada para un XHR.
                     */
                    if (
                        request.mode !== 'navigate' ||
                        !respuesta.headers.get('x-inertia')
                    ) {
                        return respuesta;
                    }

                    return fetch(request.url, {
                        cache: 'reload',
                        headers: { Accept: 'text/html' },
                    }).then((recuperada) =>
                        /*
                         * Si la sesión venció, esa URL redirige al login. Una
                         * respuesta ya redirigida no se le puede entregar a una
                         * navegación —el Service Worker API lo prohíbe—, así que
                         * se le pasa el redirect y lo sigue el navegador, que
                         * además deja la barra de direcciones donde corresponde.
                         */
                        recuperada.redirected
                            ? Response.redirect(recuperada.url, 302)
                            : recuperada,
                    );
                })
                .catch(() =>
                    caches.match(OFFLINE_URL).then(
                        (cacheada) =>
                            cacheada ||
                            new Response('Sin conexión', {
                                status: 503,
                                headers: {
                                    'Content-Type': 'text/plain; charset=utf-8',
                                },
                            }),
                    ),
                ),
        );
        return;
    }

    // Todo lo demás (íconos, manifest, XHR de Inertia): network-first, sin
    // guardar nada. Si no hay red, que falle y la UI lo muestre.
    event.respondWith(fetch(request));
});
