# Huella — convenciones del proyecto

App web personal para el historial de salud y vida cotidiana de una o más mascotas.
El eje del producto es el **diario**: una línea de tiempo única donde conviven visitas,
vacunas, desparasitaciones, dietas, pesos, celos y notas.

Documentos de diseño en [docs/](docs/): especificación, esquema de datos y plan de trabajo.
Ante una discrepancia entre esos documentos y este archivo, **manda este archivo**: recoge
las correcciones que se acordaron antes de implementar.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8 · Inertia 3 · Vue 3 + TypeScript · Tailwind 4 · Vite
Auth por Fortify (incluye 2FA y passkeys, ya cableados por el starter kit).
Rutas tipadas con **Wayfinder**, no con Ziggy.
Tests con **Pest 4** (PHPUnit 12 — no subir a Pest 5, que exige PHPUnit 13 y rompe el kit).

## Idioma

- Todo el texto visible va en **español rioplatense, con voseo**. Nada de "usted".
- Tablas, columnas, modelos, rutas y servicios **en español**.
- **Excepción:** `users` y sus columnas base (`name`, `email`, `password`) quedan como
  las genera el starter kit. Las tablas de dominio usan `usuario_id` apuntando a `users.id`.

## Fechas y zona horaria

- Se persiste **siempre en UTC**. `config/app.php` tiene `timezone` fijo en `'UTC'`.
- Cada usuario tiene su `users.zona_horaria`; la conversión ocurre al mostrar y al
  evaluar recordatorios. No cambiar el timezone global: rompe ese diseño.
- La conversión vive en **`User`** (`zona()`, `aUtc()`, `enSuZona()`, `ahora()`,
  `hoy()`), no desperdigada por controladores. Un `datetime-local` del navegador llega
  sin zona: es la del usuario, y `aUtc()` es lo que la aplica.
- Para los registros de una mascota manda la zona del **propietario**, no la de quien
  carga: así en v2 un cuidador en otro país no mueve las horas de las tomas.
- "Hoy" es el día del usuario. Con el servidor en UTC, una toma de las 21:00 de Buenos
  Aires cae en el día siguiente y desaparecería de «Medicación de hoy».
- **`hoy()` es para comparar contra `datetime`; `hoyCalendario()` para comparar contra
  `date`.** Un instante con zona contra una columna `date` (que Carbon lee a medianoche
  UTC) se corre tres horas, y "mañana" se lee como "hoy". Es el error más fácil de
  cometer en todo el proyecto y no da ningún síntoma hasta que alguien mira la fecha.
- `dayjs` en el front, Carbon en el back.

## Backend

- Un **FormRequest por acción de escritura**. Nada de validación en el controlador.
- **`$this->route('mascota')` no siempre devuelve el modelo**: según cuándo se evalúe,
  puede llegar como el id crudo de la URL. Para acotar una regla por mascota va
  `ResuelveMascotaDeLaRuta`, no un `instanceof` con fallback a cero: eso convierte la
  validación en un colador que pasa siempre.
- **`Rule::unique` sobre una columna `date` no es portable.** MySQL trunca a la fecha,
  pero SQLite —el de los tests— guarda `2026-08-17 00:00:00`, y la comparación por
  igualdad nunca encuentra el duplicado: la validación pasa y lo corta la base con un 500.
  Para esos casos va una regla de cierre con `whereDate`.
- **Policy en todo modelo que pertenezca a un usuario**; `$this->authorize()` siempre.
- La autorización de mascotas pasa **por el pivote `mascota_usuario`**, nunca comparando
  `usuario_id` a mano. Es lo que permite sumar multi-cuidador en v2 sin reescribir Policies.
- Los **servicios** contienen la lógica de negocio; los controladores solo orquestan.
- Los **recordatorios los generan observers**, nunca un controlador. Idempotentes por
  `origen_type` + `origen_id` + `tipo`.
- Enums de PHP para todos los ENUM del esquema.
- Todo listado con **eager loading explícito**.
- Soft deletes en las entidades principales.
- Adjuntos en el disco **privado `local`**, servidos por controlador tras verificar
  propiedad. Nunca por URL pública.

## Catálogos

Los cinco (veterinarias, veterinarios, medicamentos, vacunas, alimentos) comparten
mecánica y la comparten en un solo lugar:

- El trait `EsCatalogo` y la interfaz `Catalogo` dan `esSemilla()`, `perteneceA()`,
  `asignarPropietario()` y los constructores de consulta `disponiblesPara()` /
  `propiosDe()`. Son **métodos estáticos, no scopes**: `CatalogoBaseController`
  los llama sobre modelos que solo conoce en abstracto, y un scope mágico ahí
  no se puede tipar.
- Una sola `CatalogoPolicy`, declarada en cada modelo con
  `#[UsePolicy(CatalogoPolicy::class)]`.
- `CatalogoBaseController` trae `index`, alta, edición, baja y duplicado. Cada hijo
  redeclara `store` y `update` **solo** para poner el FormRequest en la firma, que es
  como Laravel sabe qué validar.
- `usuario_id` NULL = semilla del sistema. La ven todos, no la edita nadie: se duplica
  (regla de negocio 4). `veterinarias` y `veterinarios` son agenda personal y nunca
  tienen semilla.
- El seeder de semilla va **aparte** de `DatabaseSeeder` (`CatalogosSeeder`) porque
  también corre en producción, y es idempotente por `updateOrCreate`.
- **El `store` responde dos cosas:** redirección Inertia para el listado, y JSON con
  el registro serializado cuando `wantsJson()`. Eso último es lo que permite el alta
  al vuelo desde un combo sin que el usuario pierda el formulario a medio cargar.

## Núcleo clínico

- **Una sola `RegistroClinicoPolicy`** para visitas, tratamientos, tomas y adjuntos.
  Ninguno decide por su cuenta: todos implementan `PerteneceAMascota` y preguntan por
  su mascota, que responde por el pivote. En los adjuntos la cadena es más larga
  (adjunto → visita → mascota) y por eso la resuelve el modelo, no la Policy.
- `mascota_id` **no es fillable** en ningún registro clínico: es la FK de la que
  depende toda la autorización. Se crea por la relación (`$mascota->tratamientos()`),
  nunca por asignación masiva.
- `GeneradorTomasService` traduce la posología a tomas concretas. Tres reglas que no
  son obvias:
  1. **`duracion_dias` cuenta dosis, no días de almanaque.** "Cada 8 horas por 7 días"
     son 21 dosis —las que trae la caja—, no las 20 que entran en la ventana de
     calendario si el cronograma arranca a las 8 de la mañana.
  2. Tope de **90 días** por generación, o un crónico sin fecha de fin generaría filas
     para siempre.
  3. Al regenerar se borran **solo las futuras pendientes**. Lo administrado u omitido
     es historia; una pendiente vencida es deuda real ("no le di la de ayer") y
     borrarla falsearía la adherencia.
- Sin frecuencia no se genera cronograma: un "dárselo si le duele" se registra igual,
  pero inventarle horarios sería inventar una indicación que nadie dio.
- **Adjuntos: el original se conserva tal cual.** Una radiografía recomprimida deja de
  servir para lo que se guardó. Solo se recomprime la miniatura, y solo para la vista
  previa de la lista.
- El cierre de tratamientos vencidos lo hace `huella:cerrar-tratamientos` desde el
  scheduler, y se decide por la **última toma programada**, no por la fecha de fin.

## Recordatorios

- **Los generan los observers de la entidad origen, nunca un controlador.** Así valen
  igual si el registro entra por el formulario, por un seeder o por un import futuro.
  Fuentes: `aplicaciones_vacuna.proxima_dosis`, `desparasitaciones.proxima_fecha`,
  `visitas.proximo_control`, `mascotas.seguro_vencimiento`.
- Idempotentes por **`origen_type` + `origen_id` + `tipo`**. Volver a guardar el origen
  mueve el recordatorio; no crea otro.
- **Lo que el usuario ya resolvió no se resucita.** Si marcó "ya se lo di" y después
  corrige el lote de la vacuna, el recordatorio sigue completado. Si en cambio cambia la
  fecha, vuelve a pendiente porque hay que avisar de nuevo.
- `fecha_completado` **no es fillable**: la escribe el sistema al resolver, no un
  formulario. Igual que `mascota_id`.
- Los automáticos no se editan ni se borran a mano: se cambia su origen, o se descartan.
- El aviso corre **cada hora** (`huella:procesar-recordatorios`), no una vez al día: la
  hora de notificación es local de cada usuario y un job diario a hora fija del servidor
  le llega a la mitad de la gente a la hora equivocada.
- Un solo mail por usuario con todo junto, y pasa a **"avisado"**, no a "hecho": que
  llegue el mail no significa que la vacuna se haya dado.
- El mailable **no** usa `ShouldQueue`: lo dispara un comando del scheduler, que ya es
  asíncrono. Encolarlo obligaría a tener un worker corriendo, y en hosting compartido eso
  se cae en silencio y deja al usuario sin avisos sin que nadie se entere.
- Cuidado con los nombres en un Mailable: Laravel pasa a la vista las propiedades
  públicas **y** las claves de `with`, y una propiedad pisa la clave homónima sin dar
  ningún error. Por eso la vista recibe `avisos` y no `recordatorios`.
- El semáforo de vacunación (`EstadoVacunacionService`) solo mira las fechas cargadas.
  **No decide qué vacunas le corresponden a una mascota**: sin datos dice "sin datos", no
  "mal vacunada" (regla de negocio 7).

## Seguimiento

- **Una sola dieta vigente por mascota** (regla 1). Lo garantiza `DietaService` dentro de
  una transacción, con `lockForUpdate`: la base no puede sola, porque MySQL admite
  múltiples NULL en un índice único. Si la nueva arranca el mismo día que la anterior,
  la anterior se cierra ese día —cerrarla "el día anterior" le daría un fin previo a su
  inicio.
- Los pesos **no tienen soft delete**: uno mal cargado deforma la curva y lo que se
  quiere es que desaparezca.
- Un peso por día **y por origen**: dos mediciones de la misma balanza el mismo día son
  una corrección; casa y veterinaria el mismo día son dos datos legítimos, porque las
  balanzas no coinciden.
- `EstimadorCeloService` promedia los intervalos entre ciclos cargados. Con menos de dos
  cae a 180 días, y **la estimación viaja siempre con su nivel de confianza**: una fecha
  sola se lee como un dato, y esto es un promedio. Si la fecha estimada ya pasó se marca
  `vencida` y el texto lo dice, en vez de anunciar como "próximo" algo que ocurrió.
- La estimación de celo es **una por mascota, no una por ciclo**: cada ciclo nuevo cambia
  el origen del recordatorio, así que `dejarSoloEste()` descarta el anterior. Sin eso,
  cada celo cargado dejaría su propio aviso abierto.
- `duracion_dias` y `proxima_estimada` no son fillable: las calcula el observer.

## Diario y exportación

- `TimelineService` unifica **ocho fuentes** en una sola lista. No es una VIEW con UNION:
  las columnas no coinciden ni en nombre ni en tipo, una VIEW obligaría a castear todo a
  texto y habría que mantenerla en migraciones cada vez que una fase suma un campo.
- **Paginado por cursor, no por offset.** Con offset, cargar la página 5 exige descartar
  las 4 anteriores en las ocho tablas, y si entra un evento mientras el usuario scrollea
  las filas se corren y se saltea o repite alguno.
- El orden **tiene que coincidir exactamente** con lo que aplica el cursor: fecha, tipo,
  id numérico. Ordenar por la clave `tipo:id` como texto parece equivalente pero no lo
  es —"peso:10" queda antes que "peso:9"— y ese desajuste saltea eventos en silencio.
- Los tratamientos que salieron de una visita **no son un evento propio**: ya se ven en
  la visita. Solo los sueltos entran a la línea de tiempo.
- La página siguiente del scroll llega por **JSON, no por Inertia**: el scroll suma a la
  lista que ya está en pantalla, y una navegación Inertia la reemplazaría entera perdiendo
  la posición del scroll.
- El PDF pone **las alergias primero**, antes de la ficha: es el dato que puede cambiar
  una decisión en una urgencia y nadie va a hojear tres páginas para encontrarlo. Estilo
  sobrio a propósito: se imprime en blanco y negro y se lee fotocopiado.
- El JSON de exportación **no embebe los adjuntos**: un archivo con radiografías en base64
  pesa cientos de megas y no se puede abrir. Van sus datos y de dónde bajarlos.
- **Cuidado con las directivas Blade pegadas a una letra**: `castrado/a@endif` no se
  reconoce como directiva —Blade lo toma como texto— y el `@if` queda abierto, con un
  error de sintaxis en la vista compilada que no señala la línea real. Para condicionales
  inline cortos conviene una expresión: `{{ $x ? ', algo' : '' }}`.

## Gráficos

- `chart.js` + `vue-chartjs`. En la curva de peso, tres decisiones de lectura:
  1. **El eje Y no arranca en cero.** Un perro de 18 kg que engorda 2 se ve plano en una
     escala 0–20, y esos 2 kg son el dato. La contra es que exagera la pendiente, así que
     el eje va etiquetado en kg y la variación numérica se muestra aparte.
  2. Los pesos de la veterinaria se dibujan con otra forma de punto: sin distinguirlos,
     un salto de balanza se lee como que la mascota engordó de un día para el otro.
  3. **El gráfico nunca es la única fuente**: siempre va la lista con fechas y valores al
     lado, para lector de pantalla y para puntos encimados.

## Frontend

- Páginas Inertia en **`resources/js/pages`, siempre en minúscula**. Linux distingue
  mayúsculas y un case equivocado rompe el build y los tests aunque en Windows ande.
- Reutilizar los componentes de `resources/js/components/ui/` (shadcn-vue sobre reka-ui):
  ya hay `card`, `sheet`, `dialog`, `input`, `select`, `sidebar`, `collapsible`, `skeleton`.
  No escribir uno nuevo sin mirar antes si existe. Propios: `SelectNativo`,
  `TextareaNativo`, `CampoCheck`, `CampoFoto`, `ComboboxCatalogo`, `ListaCatalogo`.
- Estado por props de Inertia + composables. Sin Pinia.
- Los valores de un formulario viajan en **inputs reales** dentro del `<Form>` de
  Inertia. Los componentes de reka-ui son botones, no inputs: `CampoCheck` y
  `ComboboxCatalogo` llevan su `<input type="hidden">` espejo por eso.
- Elegir de un catálogo se hace con `ComboboxCatalogo`: sheet con buscador (que
  normaliza acentos) y alta al vuelo por `fetch`. **No** un popover: en el celular una
  lista larga dentro de un popover no se maneja con el pulgar.
- Fuera de ese caso, todo lo que escribe va por Inertia. `resources/js/lib/http.ts`
  existe solo para el alta al vuelo.

## Mobile first

La carga real ocurre en la veterinaria, con el celular en la mano.

- Bottom tab bar + FAB en `< md`; sidebar en `>= md`. **Mismo árbol de rutas**, no dos apps.
- Sheets en vez de modales en pantallas chicas.
- Teclado correcto: `inputmode="decimal"` para peso, `type="date"`, `type="tel"`.
- Áreas táctiles de 44 px mínimo. `env(safe-area-inset-bottom)` en la barra inferior.
- Cámara directa en la subida de fotos: `capture="environment"`.
- Nada que dependa solo de `hover`.

## Marca

Los assets están en `public/img/`: `huella-icono-app.webp` (ícono),
`huella-logo-horizontal.webp` y `huella-logo-vertical.webp`.

- `AppLogoIcon.vue` renderiza el ícono de marca; cambiándolo ahí se actualizan
  la sidebar, el header y las pantallas de auth de una sola vez.
- Los logos tienen **fondo blanco opaco**, sin transparencia. En modo oscuro no
  se invierten (arruinaría los colores): se les da una base clara redondeada.
- Los PNG del set PWA (`public/icons/`) se generan **desde** el ícono de marca,
  no a mano. El maskable rellena el lienzo con el color de fondo del propio
  ícono y lo centra al 92%, para sobrevivir al recorte de Android.

## PWA

Service worker propio en `public/sw.js`. **No** usar `vite-plugin-pwa`.

- `beforeinstallprompt` se captura con un **script inline en el `<head>`**, antes de que
  monte Vue. Hacerlo en `onMounted` deja el botón sin aparecer, de forma intermitente.
- `cache-first` **solo** para `/build/` (tiene hash de contenido). Todo lo demás
  network-first. **Las respuestas de Inertia no se cachean**: son datos clínicos, y mostrar
  una dosis vieja es peor que mostrar un cartel de "sin conexión".
- Al cambiar un ícono hay que tocar **tres lugares**: el nombre de `CACHE` en `sw.js`,
  el `?v=` de los `<link rel="icon">` y el `?v=` del manifest.
- En producción, `sw.js` y `manifest.webmanifest` necesitan `no-cache` en `.htaccess`,
  o el CDN sirve un service worker viejo durante días y congela todo lo demás.

## Reglas de negocio que se validan en la aplicación

1. Solo una dieta por mascota con `fecha_fin` NULL. Al crear una nueva, cerrar la anterior
   con la fecha del día anterior, **dentro de una transacción**.
2. Si `mascotas.castrado = 1`: se oculta el módulo de celo y se descartan sus recordatorios.
3. Mascota fallecida: pasa a modo lectura y se descartan sus recordatorios pendientes.
4. Los catálogos con `usuario_id` NULL son semilla compartida: no se editan, se duplican.
5. Al editar la frecuencia de un tratamiento se regeneran **solo las tomas futuras
   pendientes**; las administradas no se tocan.
6. Los cálculos de próxima fecha son sugerencias precargadas, **siempre editables**.
7. **El sistema registra, no aconseja.** Ninguna recomendación clínica.

## Seeders

Están separados porque uno va a producción y el otro no:

- `CatalogosSeeder` — semilla compartida (`usuario_id` NULL). Idempotente. **Corre en
  producción:** `php artisan db:seed --class=CatalogosSeeder --force`.
- `DemoSeeder` — la mascota de ejemplo. **Lanza excepción si `isProduction()`**, y
  `DatabaseSeeder` no lo llama allá. Un usuario de demo en producción es una cuenta real
  que nadie creó.

Ninguno de los dos usa `WithoutModelEvents`: los observers tienen que correr, son los que
escriben el pivote de propietario y los recordatorios.

## Repo y CI

Remoto: `github.com/pablomandile/huella`. El workflow `.github/workflows/tests.yml` corre
`composer setup` y `composer ci:check` en Ubuntu. Dos cosas que ya rompieron y están
resueltas — no revertirlas:

- **PHP 8.4 en el workflow.** Todo Symfony 8 del `composer.lock` exige `>=8.4.1`; con 8.3
  el `composer install` corta antes de correr un solo test.
- **El job levanta un servicio MySQL.** `composer setup` incluye `artisan migrate`, y sin
  base el job muere en el setup con "Connection refused". Los tests usan sqlite en memoria,
  pero ese migrate real vale: MySQL rechaza esquemas que sqlite tolera.

Antes de pushear, correr `composer ci:check` — es exactamente lo que corre el CI, ESLint
incluido (Pint y Prettier pasando no alcanzan).

`CaseDeLasPaginasTest` compara cada `Inertia::render()` contra el listado real del
directorio, con comparación estricta de strings. Es lo único que detecta desde Windows un
case equivocado que en Linux rompería el build.

## Comandos

```bash
php artisan test          # Pest
composer ci:check         # todo lo que corre el CI
./vendor/bin/pint         # formato PHP
npm run format            # Prettier
npm run lint              # ESLint
npm run types:check       # vue-tsc
composer types:check      # PHPStan (Larastan)
npm run dev               # Vite
```
