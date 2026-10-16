# Términos y condiciones y política de privacidad

## Contexto

El registro de Huella está abierto: cualquiera puede crearse una cuenta, y en
producción ya hay tres. La app guarda el email, el nombre y la zona horaria de cada
usuario, y la historia clínica y los archivos de sus mascotas. Además permite
compartir fichas con otras cuentas o por un enlace público, y entrar con Google.

Hoy no hay ni términos ni política de privacidad.

- **La política de privacidad es lo que más importa.** En Argentina, la ley 25.326
  exige informar qué se junta, para qué y cómo pedir el acceso o el borrado. Google
  también la pide para sacar el ingreso con Google del modo de prueba.
- **Los términos** hacen explícito lo que hoy solo dice Acerca de: Huella registra,
  no aconseja. También cubren la disponibilidad del servicio, qué se puede subir y
  quién responde por lo que se comparte.

**Los textos son a medida, no una plantilla.** Hablan de lo que Huella hace de
verdad. No reemplazan la revisión de un abogado si el servicio crece o empieza a
cobrar.

## Dos hallazgos que el plan tiene que resolver

1. **Borrar la cuenta hoy no borra nada.** `User` usa `SoftDeletes`: el
   `$user->delete()` de `ProfileController::destroy` solo marca la fila. Los
   `cascadeOnDelete` de mascotas, el pivote y los catálogos nunca se disparan, y
   los archivos siguen en el disco. Una política que prometa el borrado sería
   falsa. → **Paso 4: purga real.**
2. **El service worker guardaría la pantalla de aceptación con el nombre de otra.**
   Con el middleware nuevo, un pedido Inertia a `/dashboard` se redirige a
   `/terminos/aceptar`. El `fetch` sigue la redirección y vuelve un 200 con
   `X-Inertia`. `sw.js` (alrededor de la línea 300) lo guardaría como la copia del
   inicio, y sin conexión el inicio mostraría la pantalla de aceptación. Lo mismo
   pasa con el precalentado. → **Paso 5: no guardar respuestas `redirected`.**

## Pasos

### 0. Borradores para revisar (antes de tocar código)

`docs/legales/terminos.md` y `docs/legales/privacidad.md`, para que los revises y
los corrijas. Los textos de las páginas salen de ahí.

**Privacidad** (ley 25.326):

- **Responsable:** Pablo Mandile y su contacto.
- **Qué datos se juntan:**
  - de la cuenta: nombre, email, zona horaria y contraseña cifrada;
  - de las mascotas: la ficha, los registros clínicos y los archivos;
  - técnicos: las cookies, con enlace a `/cookies`.
- **Para qué:** solo para prestar el servicio. No se venden, no hay publicidad y
  no hay perfiles.
- **Con quién se comparten:**
  - Hostinger, que aloja la app;
  - el proveedor de mail, que manda los avisos y las invitaciones;
  - Google, solo si entrás con Google;
  - las personas a las que el propio usuario invita, o a las que les pasa un enlace.
- **Dónde se guardan y cuánto tiempo:** mientras la cuenta exista, y el plazo de
  purga del paso 4.
- **Derechos de acceso, rectificación y supresión:** cada uno se ejerce con una
  función que ya existe. Exportar está en «Mis datos», corregir en el perfil y
  borrar en «Eliminar cuenta», que con el paso 4 pasa a borrar de verdad. Además,
  el mail de contacto.
- **El texto obligatorio sobre la AAIP:** la Agencia de Acceso a la Información
  Pública es el órgano de control de la ley 25.326 (Disposición 10/2008).

**Términos:**

- **Qué es Huella.**
- **Regla 7, en primer plano:** la app registra y no da consejos clínicos. Ante una
  duda, va el veterinario.
- **La cuenta:** la custodia de la contraseña y una edad mínima de 18 años, o con
  autorización.
- **Lo que el usuario carga es suyo:**
  - Huella solo lo usa para mostrárselo;
  - el usuario responde por tener derecho a subirlo;
  - no se puede subir contenido ilegal ni ajeno.
- **Compartir:** el dueño decide quién ve qué. Un enlace público lo puede abrir
  cualquiera que lo tenga, y revocarlo es responsabilidad del dueño.
- **El servicio:**
  - se ofrece «tal cual», gratis y sin garantía de disponibilidad;
  - se recomienda exportar los datos de vez en cuando;
  - puede cambiar o cerrar, con un aviso previo por mail y tiempo para exportar.
- **Cambios en los términos:** se avisan y se vuelven a aceptar.
- **Ley aplicable:** Argentina.

### 1. Versión y constancia de la aceptación

- `config/legales.php`: `'version' => '2026-10-…'`. Es la fecha de la versión
  vigente, para los dos textos juntos.
- **Migración:** `users.terminos_version` (string, nullable) y
  `users.terminos_aceptados_en` (datetime, nullable).
- **No son fillable**, igual que `fecha_completado`: los escribe el sistema al
  aceptar. Van en `User::aceptarTerminos()`, que usa `forceFill` con la versión
  vigente y `now()`.
- `User::aceptoTerminosVigentes(): bool`.

### 2. Aceptación al registrarse

- **`Register.vue`:** una casilla obligatoria antes del botón: «Leí y acepto los
  [términos] y la [política de privacidad]». Los enlaces abren en otra pestaña,
  para no perder lo que se cargó. Va con `CampoCheck`, que trae su input oculto.
- **`CreateNewUser`:** la regla `'terminos' => ['accepted']` con su mensaje en
  español, y `aceptarTerminos()` al crear la cuenta. Así el registro con formulario
  nunca pasa por la pantalla del paso 3.

### 3. Middleware `AceptoTerminos` para todos los demás

Cubre tres casos: las 3 cuentas que ya existen, el alta con Google (que no pasa por
el formulario) y cualquier cambio futuro de versión. Con usuario y sin la versión
vigente:

- **Navegación o Inertia:** redirige a `terminos.aceptar` y guarda `url.intended`.
- **JSON** (`expectsJson`): 409 con un mensaje. **Excepción: `POST /sincronizar`
  pasa.** Lo cargado sin conexión se registró bajo la aceptación anterior, y
  frenarlo trabaría la cola para siempre sin avisar.

**Exentas:** la pantalla de aceptación, `/terminos`, `/privacidad`, `/cookies`,
`/acerca-de`, logout, `settings/profile` (DELETE, para irse sin aceptar) y
`mis-datos` (para llevarse lo propio sin aceptar). Las rutas exentas viven en una
constante, y un test las barre.

Se aplica en los grupos `auth` de `web.php` y `settings.php`, no en las rutas
públicas ni en `/compartido/`.

**Pantalla `terminos/Aceptar.vue`**, en `AuthLayout`:

- Un resumen de qué cambió, o «antes de seguir» si es la primera vez.
- Los enlaces a los dos textos.
- La casilla y el botón «Aceptar y continuar». El POST va a
  `terminos.aceptar.store`, que usa un FormRequest con `accepted` y vuelve con
  `redirect()->intended()`.
- Un enlace «Salir».

**Google:** el alta crea la cuenta como hoy, y el middleware la frena en el primer
pedido. Debajo del botón de Google en el login va el texto «Al continuar aceptás
los términos y la política de privacidad». Es informativo; la constancia sigue
siendo la pantalla.

### 4. Purga real de las cuentas borradas

- **Comando `huella:purgar-cuentas`**, diario desde el scheduler (el cron
  `schedule:run` ya existe). Toma las cuentas con `deleted_at` de hace más de
  **30 días** y las borra de verdad con `forceDelete`, más sus archivos del disco
  `local` (fotos, adjuntos, documentos y paquetes de alimentos).
- **Los archivos van primero:** la cascada de la base se lleva las filas que dicen
  dónde está cada uno.
- **Los 30 días** cubren el arrepentimiento y lo que haga falta reclamar, y la
  política lo dice tal cual. Hay que elegirlos con cuidado: después del plazo no
  se recupera nada.
- **Mascotas compartidas:** un cuidador o lector pierde el acceso al purgarse el
  dueño. La cascada del pivote ya lo hace.
- **Test:** un usuario con mascota, foto y adjunto, borrado hace 31 días, no deja
  filas ni archivos. Uno borrado hace 29 días queda intacto.

### 4b. Botón «Descargar mis datos»

Aparecido al escribir los borradores: la ruta `exportacion.datos` (`/mis-datos`)
existe, pero **ninguna pantalla la enlaza**. La política promete el derecho de
acceso desde la app, así que hace falta un botón en *Ajustes → Perfil*, arriba de
«Eliminar cuenta». Es un enlace de descarga, no una navegación Inertia. Un test
comprueba que `Profile.vue` lo enlace.

### 5. El service worker no guarda redirecciones

En `sw.js`, la caché de JSON Inertia se suma `!respuesta.redirected` a la
condición: lo mismo que ya hace la caché de HTML (línea 387). Hay que subir
`CACHE` a `huella-v5`, y `ServiceWorkerTest` cubre la regla.

### 6. Páginas públicas `/terminos` y `/privacidad`

- `pages/Terminos.vue` y `pages/Privacidad.vue`, con la misma mecánica que
  `Cookies.vue`: con sesión van en `AppLayout`, sin sesión en `PublicoLayout`. Se
  suman al caso de `app.ts`.
- **Arriba de todo:** «Versión del {fecha}», que se lee de la prop compartida
  `legales.version`.
- `Route::inertia` públicas; regenerar Wayfinder con `--with-form`.
- **Enlaces:** el pie de la portada (Acerca de · Cookies · Términos · Privacidad),
  Acerca de, el menú de usuario, la página de cookies («ver también»), el registro
  y la pantalla de aceptación.

### 7. Tests (`tests/Feature/Legales/`)

- **Registro:** sin la casilla falla con un error en `terminos`. Con la casilla
  guarda la versión y la fecha, y entra sin pasar por la pantalla de aceptación.
- **Usuario sin versión, o con una vieja:**
  - `/dashboard` lo redirige a la pantalla de aceptación;
  - al aceptar vuelve a donde iba;
  - con una versión vieja, la pantalla vuelve a aparecer.
- **Lo que pasa sin aceptar:** las rutas exentas responden; `POST /sincronizar`
  pasa; un pedido JSON recibe 409.
- **Google:** una cuenta nueva por Google cae en la pantalla de aceptación.
- **Barrido de exentas:** el middleware cubre todas las rutas `auth` que no están
  en la lista de exentas. El patrón es el de `PrivacidadMascotasTest`.
- **Páginas:** `/terminos` y `/privacidad` dan 200 con sesión y sin ella.
- **Purga:** la del paso 4. `CaseDeLasPaginasTest` cubre las páginas nuevas solo.

### 8. Documentación

`CLAUDE.md` suma la sección «Términos y privacidad», con estos puntos:

- La versión única en la config, que se sube para volver a pedir la aceptación.
- La constancia en `users`, sin fillable.
- Las rutas exentas y por qué.
- `/sincronizar` pasa sin aceptar.
- La trampa del SW con las redirecciones.
- La purga a 30 días, y que la política promete exactamente eso.

## Verificación

1. `composer ci:check`.
2. **Navegador (CDP), con un perfil limpio:**
   - **Registro:**
     - sin la casilla da error;
     - con la casilla entra.
   - **Cuenta existente:** al entrar cae en la pantalla de aceptación, y al aceptar
     vuelve a donde iba.
   - **Enlaces:** los de términos y privacidad abren en otra pestaña.
   - **Barrido de desbordes** a 320px con letra grande en las tres pantallas.
3. **Sin conexión:** con la versión sin aceptar, el inicio guardado **no** es la
   pantalla de aceptación.
4. **Deploy:**
   - extras de PHP (middleware, controlador, FormRequest, comando, config,
     migración, rutas y `sw.js`);
   - `migrate --force` y `route:cache`;
   - confirmar que las 3 cuentas de producción ven la pantalla de aceptación;
   - commit y push.

## Después: la skill `terminos-y-privacidad`

Se arma cuando lo anterior funciona, en `~/.claude/skills/terminos-y-privacidad/`.

- **Advertencia al principio, en negrita:** los textos **se reformulan para cada
  app**. Una plantilla genérica promete cosas que la app no hace —y una promesa
  falsa en una política de privacidad es peor que no tenerla— y calla lo que la
  app sí hace. Tampoco reemplaza a un abogado.
- **Un cuestionario para relevar la app antes de escribir**, con un grep para cada
  pregunta:
  - qué datos personales se juntan;
  - qué terceros los tocan (hosting, mail, OAuth, analíticas, pagos);
  - si hay datos sensibles (salud, finanzas);
  - si se comparte con otras cuentas o por enlace público;
  - si «borrar cuenta» borra de verdad (`SoftDeletes`) y los archivos;
  - qué se puede exportar;
  - si hay menores;
  - si se cobra;
  - el país (la ley y la autoridad de control: en Argentina, la 25.326 y la AAIP;
    en la UE, el RGPD).
- **Una estructura de cada texto**, con las secciones y lo que tiene que decir
  cada una. Con ejemplos marcados como ejemplos, no para copiar.
- **El patrón técnico:**
  - la versión en la config y la constancia sin fillable;
  - la casilla en el registro;
  - el middleware con las exentas;
  - el caso OAuth;
  - la sincronización offline que pasa;
  - el SW y las redirecciones;
  - la purga real;
  - los tests.
- **Las plantillas de código** tomadas de Huella. Los textos de Huella **no** se
  incluyen como plantilla, a propósito: solo como ejemplo de tono y de
  especificidad.
