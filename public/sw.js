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

    // Navegación sin conexión: cartel propio en vez del dinosaurio.
    if (esNavegacion(request)) {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL).then(
                    (cacheada) =>
                        cacheada ||
                        new Response('Sin conexión', {
                            status: 503,
                            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
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
