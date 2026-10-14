# Aviso de cookies y datos guardados

## Contexto

Pediste el aviso de cookies que muestran casi todos los sitios. Revisé qué guarda Huella en el navegador: **todo es necesario para que la app funcione**. No hay analíticas, publicidad ni scripts de terceros, y tampoco fuentes externas.

| Dónde | Qué | Para qué |
|---|---|---|
| Cookie | `huella-session` (nombre de la sesión de Laravel) | Mantener la sesión iniciada |
| Cookie | `XSRF-TOKEN` | Protección contra envíos falsificados |
| Cookie | `remember_web_*` | Solo si marcás "Recordarme" |
| Cookie | `appearance`, `sidebar_state` | Tema claro u oscuro, y la sidebar abierta o cerrada |
| localStorage | `appearance`, `tamanoLetra` | Tema y tamaño de letra de este dispositivo |
| localStorage | `huella:visitas`, `huella:banner-instalar-descartado` | Cuándo ofrecer "Instalá Huella" |
| localStorage | marcas del precalentado | Saber cuándo volver a guardar pantallas y de qué usuario son |
| sessionStorage | secciones plegables | Recordar si dejaste la galería o la documentación abierta |
| IndexedDB | `huella-sin-conexion` | Los cambios cargados sin conexión |
| Cache Storage (service worker) | `huella-v4`, `huella-datos-v1` | La app y las pantallas para usarla sin conexión |

**Google** pone sus propias cookies, en su dominio, solo si entrás con Google.

Como no hay nada opcional, elegiste un **aviso informativo**: un solo botón «Entendido» y un enlace a una página que detalla la tabla de arriba. Un botón «Rechazar» sería de adorno: lo único que podría apagar es el login.

## Cambios

### 1. Componente `resources/js/components/AvisoCookies.vue`

- **Texto:** «Huella usa cookies y guarda datos en tu dispositivo solo para funcionar: mantener tu sesión, recordar tus preferencias y dejarte usarla sin conexión. Nada de publicidad ni seguimiento.»
- **Acciones:**
  - Enlace «Más información» a `/cookies`.
  - Botón «Entendido», con área táctil de 44px (`touch-target`, como `BannerInstalar`).
- **Cómo recuerda que lo cerraste:**
  - Se guarda en `localStorage` con la clave `huella:aviso-cookies`, en el mismo patrón try/catch que `BannerInstalar.vue`.
  - El valor es una versión (`'1'`). Si mañana se suma algo no esencial, se sube la versión y el aviso vuelve a aparecer.
  - Si no hay `localStorage` (modo privado), el aviso igual se muestra y «Entendido» lo cierra mientras dure la pestaña. Para eso se usa una `ref` a nivel de módulo, compartida entre layouts.
- **Recién después de montar:** se renderiza en `onMounted`. Si se dibujara en el primer render, quien ya lo cerró lo vería aparecer y desaparecer.
- **Diseño:**
  - `role="region"` con `aria-label="Aviso de cookies"`.
  - Tarjeta `bg-card`, borde, sombra.
  - Texto con `wrap-anywhere`. Nada de `truncate` ni tamaños en px (reglas de *Tamaño de letra*).
- **Posición, con una prop `sobreBarraInferior`:**
  - Por defecto: fijo abajo (`fixed inset-x-0 bottom-0`, con `env(safe-area-inset-bottom)`). Desde `md` va en una tarjeta angosta abajo a la izquierda.
  - Adentro de la app en el celular: se apila en la zona inferior, arriba de `BannerInstalar` y `BottomNav`, igual que hace hoy el banner de instalación. Así no le pelea el `bottom-0` a la barra.

### 2. Dónde se monta

Va en todos los lugares por donde se entra, porque el aviso tiene que verse en la primera visita:

- `resources/js/layouts/app/AppSidebarLayout.vue`:
  - En el celular: en el `div` de la zona inferior, antes de `<BannerInstalar />`.
  - Desde `md`: una segunda instancia fija.
  - Las dos leen la misma `ref` de módulo, así que se cierran juntas.
- `resources/js/layouts/auth/AuthSimpleLayout.vue`: login, registro e invitaciones.
- `resources/js/pages/Welcome.vue`, `resources/js/pages/AcercaDe.vue` (rama sin sesión) y la página nueva (sin sesión).
- **No va en `/compartido/`.** Es Blade suelta y se muestra en un mostrador (misma regla que las patitas). Ahí tampoco se crean datos locales: el service worker no entra.

### 3. Página `resources/js/pages/Cookies.vue`

- **Título:** «Cookies y datos guardados».
- **Contenido:**
  - Un párrafo de resumen: todo es necesario y no hay seguimiento.
  - Las cookies y los datos guardados de la tabla, cada uno en una fila con nombre, para qué sirve y cuánto dura. En el celular son tarjetas apiladas, no una tabla ancha.
  - Google, aclarando que es solo si entrás con Google.
  - Cómo borrarlo:
    - Salir de la cuenta borra lo guardado para usarla sin conexión.
    - El resto, desde la configuración del navegador.
- **Encabezado y layout:** misma mecánica que `AcercaDe.vue`, con encabezado propio y fondo `fondo-huellas` cuando no hay sesión.
- **`resources/js/app.ts`:** se suma `name === 'Cookies'` al caso de `AcercaDe` (`AppLayout` con usuario, `null` sin usuario).
- **`routes/web.php`:** `Route::inertia('cookies', 'Cookies')->name('cookies');`, al lado de `acerca-de`, pública.
- Regenerar Wayfinder con `--with-form`.
- **Enlaces a la página:**
  - Desde el aviso.
  - Desde el pie de `Welcome.vue`, junto a «Acerca de».
  - Desde `AcercaDe.vue`.
  - Desde `UserMenuContent.vue`, debajo de «Acerca de».

### 4. Tests (`tests/Feature/Pulido/AvisoCookiesTest.php`)

- `/cookies` da 200 con el componente `Cookies`, con sesión y sin ella.
- **Inventario contra el código:** junta las claves que el código guarda en el navegador, mirando:
  - las llamadas a `localStorage.setItem` y `document.cookie` dentro de `resources/js`;
  - el nombre de la base de `idb` y los nombres de caché de `public/sw.js`.

  Exige que cada clave aparezca en `Cookies.vue`. Si alguien suma una clave nueva y no la declara, la suite falla. Es el mismo patrón que `TamanoLetraTest`, que cuida archivos que nada ata.
- **Otro test:** el aviso está montado en los cuatro puntos de entrada.
- `CaseDeLasPaginasTest` ya cubre la página nueva sin tocar nada.

### 5. Documentación

- **`CLAUDE.md`:** una sección corta, «Aviso de cookies», con estos puntos:
  - por qué es informativo y no hay «Rechazar»;
  - que la página es el inventario y el test lo cuida;
  - que si algún día se suma algo no esencial (analíticas, por ejemplo), cambia el modelo: hay que pedir consentimiento antes de cargarlo y subir la versión del aviso.
- **Skill `adaptar-ui-mobile`:** una nota corta sobre ubicar el aviso en la zona inferior del celular junto a la barra, sin taparla.

## Verificación

1. `composer ci:check`: Pest, PHPStan, Pint, ESLint, Prettier y vue-tsc.
2. En el navegador, por CDP como antes, en 320, 390 y 1280px, con letra Grande:
   - **Primera visita:** el aviso aparece en la portada.
   - **Persistencia:** «Entendido» lo cierra y no vuelve después de recargar ni después de entrar a la app.
   - **Con la bandera borrada:**
     - adentro de la app en el celular, el aviso queda arriba de la barra inferior sin taparla;
     - pasando el barrido `detectar-overflow.mjs`, no aparece ningún desborde.
3. `/cookies` se ve sin sesión, con encabezado propio, y con sesión, adentro de la app.
4. Deploy a Hostinger y commit solo si lo pedís.

## Guardar el plan

Primer paso, antes de implementar: copiar este plan a `docs/plan-aviso-cookies.md`, así queda en el repo junto a los otros documentos de diseño.

## Después de terminar con éxito: la skill

Solo cuando la verificación pase entera, se crea una skill nueva en `~/.claude/skills/aviso-cookies/`. Es la misma carpeta de OneDrive que `adaptar-ui-mobile`, así sirve en cualquier proyecto. Contenido:

- **`SKILL.md`**
  - Una descripción que la active con pedidos como «aviso de cookies», «banner de cookies», «política de cookies», «qué datos guardamos».
  - El proceso:
    1. Hacer el **inventario** con los greps de `localStorage`, `sessionStorage`, `document.cookie`, IndexedDB, Cache Storage, cookies del framework y scripts y fuentes de terceros.
    2. **Clasificar**: necesario o no necesario.
    3. **Decidir el modelo**: aviso informativo si todo es necesario, o consentimiento previo, con bloqueo de lo opcional hasta aceptar, si hay analíticas o publicidad.
    4. Armar el **componente del aviso**, con la versión en la clave para que vuelva a aparecer si algo cambia.
    5. Hacer la **página de detalle**.
    6. Escribir el **test de inventario**, que falla si aparece una clave nueva sin declarar.
    7. Hacer la **verificación**.
  - Las trampas que aparezcan al implementar:
    - el parpadeo si se renderiza antes de `onMounted`;
    - la convivencia con la barra inferior del celular;
    - no mostrarlo en las vistas públicas de mostrador.
- **Plantillas** tomadas de lo que quede andando en Huella: el componente Vue, la página y el test de Pest, con los textos en voseo.
- En `adaptar-ui-mobile/SKILL.md`: una línea que apunte a la skill nueva para la parte de ubicarlo en el celular.
