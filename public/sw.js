/*
 * Service worker de Huella.
 *
 * La app se puede abrir y navegar sin conexión, con lo último que se vio. Antes
 * la regla era la contraria —"no se cachean datos: una dosis vieja es peor que
 * un cartel de sin conexión"— y sigue valiendo su motivo: por eso lo guardado se
 * muestra SIEMPRE con la franja de "Sin conexión" y la antigüedad de los datos.
 * Una dosis vieja es peligrosa cuando parece actual, no cuando dice de cuándo es.
 *
 * Tres cachés:
 *   - CACHE: el bundle de /build/ (precacheado entero, también las pantallas
 *     que todavía no se abrieron), el HTML de arranque, íconos y la página
 *     /offline.
 *   - DATOS: el JSON de cada pantalla que manda Inertia y las miniaturas. Va
 *     aparte del HTML a propósito: es la misma URL con otro cuerpo, y si
 *     compartieran caché una pisaría a la otra (ver la nota sobre el CDN).
 *
 * Regla que no cambia: cache-first SOLO para URLs con hash de contenido
 * (/build/app-A1b2C3.js). Todo lo demás va network-first: con red se ve lo
 * último y se guarda; sin red se sirve lo guardado.
 *
 * Al cambiar un ícono hay que tocar TRES lugares a la vez:
 *   1. el número de CACHE de acá abajo
 *   2. el ?v= de los <link rel="icon"> del blade
 *   3. el ?v= de los "src" del manifest
 */
const CACHE = 'huella-v4';
const DATOS = 'huella-datos-v1';
const VIGENTES = [CACHE, DATOS];
const OFFLINE_URL = '/offline';
// Si se navega sin red a una pantalla que nunca se guardó: mejor el inicio
// guardado que el cartel.
const RESPALDO = '/dashboard';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) =>
                cache.addAll([OFFLINE_URL, '/manifest.webmanifest']),
            )
            .then(() => precachearBuild())
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
                        .filter((clave) => !VIGENTES.includes(clave))
                        .map((clave) => caches.delete(clave)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    // La app avisa por acá cuando el usuario acepta actualizar.
    if (event.data === 'saltar-espera') {
        self.skipWaiting();

        return;
    }

    const tipo = event.data?.tipo;

    // Después de un deploy el bundle trae chunks nuevos, y el SW no se
    // reinstala si sw.js no cambió: la app lo pide al arrancar con red.
    if (tipo === 'precachear') {
        event.waitUntil(precachearBuild().catch(() => {}));
    }

    // Salir de la cuenta o cambiar de usuario: lo guardado es historia clínica
    // de alguien y no puede quedar a mano del próximo que use el teléfono.
    if (tipo === 'borrar-datos') {
        event.waitUntil(
            borrarDatos().finally(() => event.ports[0]?.postMessage('listo')),
        );
    }

    // La app pregunta al arrancar si la sirvió la red o una copia guardada.
    if (tipo === 'como-arranque') {
        event.ports[0]?.postMessage(arranqueDesdeCopia);
        arranqueDesdeCopia = null;
    }
});

/*
 * Si la última navegación se sirvió de una copia guardada, y de cuándo era. Una
 * página que arranca de una copia no tiene ningún otro modo de enterarse: el
 * teléfono cree tener red (el wifi de la veterinaria, conectado pero sin
 * internet) y no hubo ningún pedido que fallara. Sin esto, la app mostraría
 * datos viejos sin la franja que dice que son viejos: justo lo que no puede
 * pasar con datos clínicos.
 */
let arranqueDesdeCopia = null;

/** Guarda todo lo que lista el manifest de Vite y tira lo de builds anteriores. */
async function precachearBuild() {
    // URL fija: se pide siempre a la red, nunca de caché.
    const respuesta = await fetch('/build/manifest.json', {
        cache: 'no-store',
    });

    if (!respuesta.ok) {
        return;
    }

    const manifest = await respuesta.json();
    const archivos = new Set();

    for (const entrada of Object.values(manifest)) {
        archivos.add(`/build/${entrada.file}`);
        (entrada.css ?? []).forEach((f) => archivos.add(`/build/${f}`));
        (entrada.assets ?? []).forEach((f) => archivos.add(`/build/${f}`));
    }

    const cache = await caches.open(CACHE);
    const guardadas = await cache.keys();
    const rutas = new Set(guardadas.map((r) => new URL(r.url).pathname));

    await Promise.all(
        [...archivos]
            .filter((f) => !rutas.has(f))
            .map((f) => cache.add(f).catch(() => {})),
    );

    // Lo de builds viejos se acumularía para siempre.
    await Promise.all(
        guardadas
            .filter((r) => {
                const ruta = new URL(r.url).pathname;

                return ruta.startsWith('/build/') && !archivos.has(ruta);
            })
            .map((r) => cache.delete(r)),
    );
}

async function borrarDatos() {
    await caches.delete(DATOS);

    // El HTML de arranque también lleva los datos de la página embebidos.
    const cache = await caches.open(CACHE);
    const claves = await cache.keys();
    await Promise.all(
        claves.filter((r) => esHtmlDePantalla(r)).map((r) => cache.delete(r)),
    );
}

function esHtmlDePantalla(request) {
    const ruta = new URL(request.url).pathname;

    return (
        ruta !== OFFLINE_URL &&
        !/^\/(build|icons)\//.test(ruta) &&
        !/\.(png|ico|svg|webmanifest|jpe?g|webp)$/.test(ruta)
    );
}

/** Le dice a la pestaña si lo que ve viene del servidor o de lo guardado. */
async function avisar(clientId, mensaje) {
    if (!clientId) {
        return;
    }

    const cliente = await self.clients.get(clientId);
    cliente?.postMessage({ tipo: 'origen-datos', ...mensaje });
}

/** Las miniaturas se guardan; los originales (radiografías, PDF) no: pesan. */
const esMiniatura = (url) =>
    url.searchParams.get('min') === '1' ||
    url.pathname.endsWith('/foto-perfil');

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    /*
     * La ficha compartida sale del alcance del service worker, entera.
     *
     * 1. El rescate de JSON crudo de más abajo **vuelve a pedir** la navegación
     *    con `cache: 'reload'`. En /compartido/ eso duplicaría el contador de
     *    aperturas, que es justo el dato con el que el dueño decide si revocar.
     * 2. Es una ficha ajena abierta en un dispositivo ajeno: no se guarda nada
     *    de ella, ni ahora que el SW sí guarda datos.
     */
    if (
        url.pathname === '/compartido' ||
        url.pathname.startsWith('/compartido/')
    ) {
        return;
    }

    // Los assets de Vite llevan el hash en el nombre: si la URL existe, el
    // contenido no cambió nunca. Servirlos de caché es seguro.
    if (
        url.pathname.startsWith('/build/') &&
        url.pathname !== '/build/manifest.json'
    ) {
        event.respondWith(
            caches.match(request).then(
                (guardada) =>
                    guardada ||
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

    // XHR de Inertia (cambio de pantalla dentro de la app): el JSON de la
    // página. `mode !== 'navigate'` no sobra: una navegación de verdad nunca
    // debe recibir este JSON (ver el rescate de más abajo).
    if (request.headers.get('x-inertia') && request.mode !== 'navigate') {
        event.respondWith(datosDeInertia(request, event.clientId));

        return;
    }

    /*
     * Navegaciones, y también el HTML que pide el precalentado con `fetch`. Sin
     * lo segundo, el HTML de arranque solo se guardaría al abrir la app con una
     * carga completa, y quien entra por el login —que redirige por Inertia, sin
     * carga completa— nunca podría abrirla en frío sin red.
     */
    const pideHtml = (request.headers.get('accept') || '').includes(
        'text/html',
    );

    if (request.mode === 'navigate' || pideHtml) {
        event.respondWith(navegacion(request));

        return;
    }

    // Miniaturas y el JSON del scroll del diario: network-first y se guardan,
    // para que la pantalla guardada no se vea con huecos.
    const esJson = (request.headers.get('accept') || '').includes(
        'application/json',
    );

    if (esMiniatura(url) || (esJson && url.pathname.endsWith('/diario/mas'))) {
        event.respondWith(redPrimeroYGuardar(request));

        return;
    }

    // Todo lo demás (íconos, manifest): network-first, sin guardar. Si no hay
    // red, lo que haya en caché o que falle.
    event.respondWith(
        fetch(request).catch(() =>
            caches.match(request).then((r) => r || Response.error()),
        ),
    );
});

async function datosDeInertia(request, clientId) {
    /*
     * Una recarga parcial (`only: [...]`) trae solo algunas props. Guardarla
     * pisaría la copia completa de esa pantalla con una a medias, y sin red la
     * página se dibujaría sin la mitad de los datos. Va directo a la red.
     */
    const parcial =
        request.headers.get('x-inertia-partial-data') ||
        request.headers.get('x-inertia-partial-except');

    try {
        const respuesta = await fetch(request);

        // Solo páginas completas: un 409 de versión, un 302 al login o un error
        // del servidor no son "la pantalla".
        if (
            !parcial &&
            respuesta.status === 200 &&
            respuesta.headers.get('x-inertia')
        ) {
            const headers = new Headers(respuesta.headers);
            headers.set('X-Guardado-En', new Date().toISOString());
            const cuerpo = await respuesta.clone().blob();
            const cache = await caches.open(DATOS);
            await cache.put(
                request.url,
                new Response(cuerpo, { status: 200, headers }),
            );
        }

        avisar(clientId, { sinConexion: false });

        return respuesta;
    } catch {
        const guardada = await caches.match(request.url, { cacheName: DATOS });

        if (guardada) {
            avisar(clientId, {
                sinConexion: true,
                guardadoEn: guardada.headers.get('X-Guardado-En'),
            });

            return guardada;
        }

        // Pantalla que nunca se guardó. El header lo reconoce la app (evento
        // `httpException` de Inertia) para avisar con un toast en vez del modal
        // de error.
        avisar(clientId, { sinConexion: true });

        return new Response('Sin conexión', {
            status: 503,
            headers: {
                'Content-Type': 'text/plain; charset=utf-8',
                'X-Sin-Conexion': '1',
            },
        });
    }
}

async function navegacion(request) {
    try {
        const respuesta = await fetch(request);

        /*
         * Una navegación no puede recibir la variante JSON de una página: la
         * misma URL devuelve HTML o JSON según el header `X-Inertia`, y el CDN
         * de Hostinger borra el `Vary` que las distingue, así que el navegador
         * las confunde. El servidor ya manda `no-store` para que no vuelva a
         * pasar, pero las entradas guardadas de antes siguen ahí, y con el JSON
         * en pantalla la app no arranca: ningún script de la página puede
         * repararlo, solo esto.
         *
         * Se mira el header `X-Inertia` de la **respuesta** y no el content-type,
         * porque `/mis-datos` es una navegación que contesta JSON de verdad y la
         * pediríamos dos veces. Ese header solo lo trae una respuesta para un XHR.
         */
        if (request.mode === 'navigate' && respuesta.headers.get('x-inertia')) {
            const recuperada = await fetch(request.url, {
                cache: 'reload',
                headers: { Accept: 'text/html' },
            });

            /*
             * Si la sesión venció, esa URL redirige al login. Una respuesta ya
             * redirigida no se le puede entregar a una navegación —el Service
             * Worker API lo prohíbe—, así que se le pasa el redirect y lo sigue
             * el navegador.
             */
            return recuperada.redirected
                ? Response.redirect(recuperada.url, 302)
                : recuperada;
        }

        // Solo el HTML sano y sin redirect: guardar el login al que redirige una
        // sesión vencida lo dejaría como la versión "sin conexión" de la página.
        // Y solo HTML: /mis-datos (JSON) y la historia clínica (PDF) no.
        const esHtml = (respuesta.headers.get('content-type') || '').includes(
            'text/html',
        );

        if (respuesta.ok && !respuesta.redirected && esHtml) {
            guardarHtml(request.url, respuesta.clone()).catch(() => {});
        }

        return respuesta;
    } catch {
        // `ignoreVary`: Huella manda `Vary: X-Inertia` siempre, y una copia que
        // guardó el precalentado podría no "coincidir" con una navegación. En
        // esta caché solo hay HTML (el JSON vive en DATOS), así que es seguro.
        const guardada = await caches.match(request.url, {
            cacheName: CACHE,
            ignoreVary: true,
        });

        // El `fetch` del precalentado no necesita respaldo: que falle.
        if (request.mode !== 'navigate') {
            return guardada || Response.error();
        }

        if (guardada) {
            arranqueDesdeCopia = {
                guardadoEn: guardada.headers.get('X-Guardado-En'),
            };

            return guardada;
        }

        if (
            await caches.match(RESPALDO, { cacheName: CACHE, ignoreVary: true })
        ) {
            return Response.redirect(RESPALDO, 302);
        }

        return (
            (await caches.match(OFFLINE_URL)) ||
            new Response('Sin conexión', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            })
        );
    }
}

/** Guarda el HTML de una pantalla con la fecha, para poder decir de cuándo es. */
async function guardarHtml(url, respuesta) {
    const headers = new Headers(respuesta.headers);
    headers.set('X-Guardado-En', new Date().toISOString());
    const cuerpo = await respuesta.blob();
    const cache = await caches.open(CACHE);
    await cache.put(url, new Response(cuerpo, { status: 200, headers }));
}

async function redPrimeroYGuardar(request) {
    try {
        const respuesta = await fetch(request);

        if (respuesta.ok) {
            const copia = respuesta.clone();
            caches
                .open(DATOS)
                .then((cache) => cache.put(request, copia))
                .catch(() => {});
        }

        return respuesta;
    } catch {
        return (
            (await caches.match(request, { cacheName: DATOS })) ||
            Response.error()
        );
    }
}
