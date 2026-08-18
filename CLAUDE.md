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
- Lo que no entra por `fill()` va en el hook **`despuesDeGuardar`**, y las rutas de
  archivos en **`columnasQueNoSeCopian`**. Lo segundo no es cosmético: dos filas apuntando
  al mismo archivo se ven bien hasta que alguien reemplaza la imagen de una y el borrado
  de la vieja deja a la otra sin foto.
- **El formulario del sheet va siempre por POST, con `_method` para editar.** PHP **no
  parsea el cuerpo multipart de un PUT**: un formulario con archivo enviado por PUT llega
  con `$request->file()` vacío y la imagen se pierde en silencio, sin error de validación
  ni ningún síntoma. Con POST + `_method=put` Laravel enruta igual al PUT y el archivo
  llega. Lo cubre `FotoAlimentoTest`.
- Los alimentos llevan **foto del paquete**: dos balanceados de la misma marca se
  distinguen por el color del envase mucho antes que por el nombre. Se recomprime a WebP
  —no es una prueba, es para reconocer una bolsa— y se sirve por controlador como todo
  lo demás.

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
- **Si la miniatura no se puede generar, el adjunto se guarda igual.** Una imagen con
  cabecera válida y píxeles corruptos —una foto cortada al subir con mala señal— pasa
  `image` y `mimes:`, que solo miran los primeros bytes, y después hace fallar al
  decodificador. Perder el archivo entero por no poder hacerle la vista previa sería el
  peor intercambio posible: se registra el aviso y se sigue. `AdjuntoController::mostrar`
  cae al original cuando no hay miniatura, así que la lista se ve igual.
- Al revés, donde la imagen convertida **es** el producto —la foto de perfil, la galería,
  el paquete de un alimento— una imagen ilegible se rechaza con la regla `ImagenLegible`:
  es un error de validación que el usuario puede accionar, no un 500.
- El cierre de tratamientos vencidos lo hace `huella:cerrar-tratamientos` desde el
  scheduler, y se decide por la **última toma programada**, no por la fecha de fin.

## Documentación de la mascota

La **libreta sanitaria** y el **certificado de rabia**: los dos papeles que el dueño carga
una vez y muestra en un veterinario nuevo, en un viaje o en una guardería.

- **No hay tabla nueva.** Son `adjuntos` colgados directo de la mascota: la tabla ya era
  polimórfica y `Adjunto::mascotaAsociada()` ya contemplaba ese caso, así que la Policy
  funciona sin tocar nada. Solo hicieron falta dos casos de `TipoAdjunto`.
- **Al sumar un caso a un enum hay que ensanchar el ENUM de MySQL.** `adjuntos.tipo` y
  `recordatorios.tipo` son columnas ENUM reales: los casos de PHP solos pasan los tests
  —sqlite no valida ENUM— y revientan en producción con un 500 al primer guardado. Es la
  misma familia de trampa que `Rule::unique` sobre una columna `date`.
- Suben **varios archivos de una**: una libreta son todas sus hojas, y hacer que se carguen
  de a una con el celular en la mano es lo que garantiza que no se carguen nunca.
- No se piden con `capture="environment"`, al contrario que las fotos: `capture` fuerza la
  cámara y anula el `multiple`. Sin él, el selector del celular ya ofrece cámara, galería
  y archivos, que es lo que hace falta para un PDF que llegó por mail.
- La autorización usa **`registrarEventos`** y no `update`, así la regla 3 —mascota
  fallecida, modo lectura— vale sin escribirla otra vez.
- `mascotas.libreta_sanitaria` es el **número** de la libreta y sigue en la ficha; esto son
  sus hojas escaneadas. Son dos cosas distintas.

El **vencimiento del certificado** vive en `mascotas.rabia_vencimiento`, calcando
`seguro_vencimiento`: una fecha de la mascota que genera su recordatorio por observer.
Conviven los dos sobre el mismo origen porque la idempotencia es por `origen_type` +
`origen_id` + **`tipo`**. Es un aviso aparte del de la antirrábica —esa la genera
`aplicaciones_vacuna.proxima_dosis` y habla de la dosis; este habla del papel, que puede
vencer en otra fecha—. Avisa con 30 días, más que una vacuna: hay que conseguir turno, dar
la dosis y que el veterinario emita el certificado.

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

## Galería y visor de imágenes

- `VisorImagen` es **uno solo** por pantalla, fuera de cualquier `v-for`: uno por foto
  multiplicaría los overlays y los focus traps de reka-ui por la cantidad de imágenes.
  Lo usan la galería, los documentos de la mascota, los adjuntos de una visita y el
  paquete de un alimento.
- **X propia y no la del `DialogContent`**, que va con `opacity-70` y sin área táctil: sobre
  fondo oscuro no se ve y en el celular no se acierta.
- **`object-contain`, nunca `cover`.** Recortar una imagen que el usuario abrió para leer
  es perder justo lo que fue a ver.
- El visor **se cierra antes de borrar** lo que está mostrando, o queda con un `src` que
  ya da 404.
- Los PDF no abren en el visor: no se pueden mostrar en un `img`, así que siguen yendo a
  otra pestaña.
- **Nada de acciones que dependan solo de `hover`.** El borrado de la galería vivía en un
  ícono con `group-hover` y en el celular era invisible: justo donde se usa la app. Las
  acciones de una foto están adentro del visor, que se abre con un toque.
- El **pase de fotos** ordena por el campo `fecha` ascendente, no por orden de carga: la
  grilla se ve al revés porque ahí interesa lo último, pero un pase cuenta la vida de la
  mascota y tiene que ir de lo más viejo a lo más nuevo. `fecha` llega como `YYYY-MM-DD`,
  así que alcanza el orden alfabético, con desempate por id para que dos fotos del mismo
  día no se muevan entre renders.
- El pase **tiene que poder pausarse**: WCAG 2.2.2 lo pide para todo lo que se mueve solo
  por más de cinco segundos. Avanzar a mano reinicia la cuenta, y el reloj solo corre con
  el pase abierto.
- Las dos imágenes se cruzan por opacidad. Con `mode="out-in"` habría un instante en negro
  entre una y otra, que es lo que hace que un pase se sienta brusco.

## Mobile first

La carga real ocurre en la veterinaria, con el celular en la mano.

- Bottom tab bar + FAB en `< md`; sidebar en `>= md`. **Mismo árbol de rutas**, no dos apps.
- La barra inferior tiene **cinco destinos y no más**: con seis hay que achicar el área
  táctil por debajo de 44px o cortar las etiquetas. Catálogos y Visitas quedan afuera a
  propósito y están en el menú. `lib/navegacion.ts` es la definición única de las dos.
- Las pantallas por mascota se llegan desde su ficha. Cuando además están en el menú
  —que no tiene contexto de mascota— hace falta un paso previo que pregunte de quién es:
  `visitas.elegir`. **Con una sola mascota redirige sin mostrarse**, porque preguntar cuál
  cuando hay una es un click de más en cada uso.
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

## Caché de las respuestas de Inertia

Una misma URL contesta **dos cuerpos distintos** según el header `X-Inertia`: el HTML de
arranque, o el JSON de la página. Lo único que las separa para una caché es
`Vary: X-Inertia` — y el CDN de Hostinger lo **borra** cuando comprime con brotli, que es
lo que pide cualquier navegador real. Sin ese header, y con el `Cache-Control: no-cache`
que Symfony pone por defecto, el navegador guarda el JSON bajo la URL de la página. Cuando
Chrome descarta una pestaña inactiva y después la restaura, esa navegación es de historial
y reusa lo guardado **sin revalidar**: aparece el JSON crudo en pantalla y la app no
arranca. Un F5 lo tapa, porque una recarga sí revalida.

- El arreglo vive en `HandleInertiaRequests::handle()`: `Cache-Control: no-store` cuando la
  petición trae `X-Inertia`, y `Vary: X-Inertia, Accept-Encoding` siempre.
- **`no-cache` no alcanza:** permite guardar y solo obliga a revalidar, y la navegación de
  historial es justamente la que saltea la revalidación. Hace falta `no-store`.
- **Nunca poner `no-store` en el documento HTML.** Chrome desactiva el back/forward cache
  de las páginas que lo traen y cada "atrás" pasa a ser una ida completa a la red. No da
  ningún síntoma; lo cuida `CacheDeInertiaTest`.
- El `sw.js` tiene además una red de seguridad: si una **navegación** contesta
  `application/json`, la vuelve a pedir con `cache: 'reload'`. Es para las entradas que ya
  quedaron guardadas en los navegadores de antes del arreglo. Cuando el bug ocurre la app
  nunca arranca, así que el service worker es el único que puede repararlo.
- Pasa igual en cualquier app Inertia de este hosting. La skill `inertia-json-crudo` tiene
  el diagnóstico y el parche para portarlo.

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

## Ingreso con Google

Socialite. El alta de credenciales está en [docs/google-oauth.md](docs/google-oauth.md).

- **Sin `GOOGLE_CLIENT_ID` la opción no existe:** el botón no se muestra y las rutas
  dan 404. Es lo que permite tener el código en producción antes del alta en Google
  Cloud, y lo que evita ofrecer un botón que lleva a un 500.
- **Una cuenta por email.** Si Google devuelve un email que ya existe, se le vincula
  el `google_id` en vez de crear otra cuenta: dos cuentas con el mismo email serían
  dos historias clínicas de la misma mascota, cada una invisible desde la otra.
- **El email tiene que venir verificado por Google**, o se rechaza. Sin eso,
  cualquiera podría reclamar la cuenta de otro declarando su dirección.
- Se reconoce por el `sub` de Google, no por el email: el email de una cuenta de
  Google se puede cambiar, el id no.
- **`users.password` es nullable** y estas cuentas quedan sin contraseña: nunca
  eligieron una, y ponerles una al azar las haría figurar como que pueden entrar con
  email y clave.
- Por eso mismo, la pantalla de seguridad usa **`ConfirmarClaveSiLaTiene`** y no el
  `RequirePassword` de Laravel: sin contraseña, esa confirmación no puede pasar
  nunca y la cuenta quedaría afuera del 2FA, de las llaves de acceso y de poder
  definirse una contraseña. Para quien tiene contraseña se le pide igual.
- `CuentaDeGoogle` traduce lo que devuelve Socialite: el flag de email verificado no
  está en su interfaz —vive en el payload crudo—, y esa rareza queda en un lugar.

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

## Deploy

Está en producción en `huella.pablomandile.com.ar` (Hostinger compartido). Los pasos,
las trampas y los cron exactos están en [docs/deploy.md](docs/deploy.md). Lo que hay
que tener presente antes de tocar producción:

- **El server no tiene Node**: el bundle se compila en local y se copia `public/build/`.
- El `php` del CLI es 8.1: `artisan` y `composer` van con `/opt/alt/php84/usr/bin/php`.
- El docroot del subdominio es un **symlink** a `huella/public`, porque hPanel lo creó
  adentro del public del sitio principal.
- Los cron de hPanel **no aceptan `cd ... &&`**: ruta absoluta a `artisan`, sin `cd`.
- Hacen falta **dos** cron: `schedule:run` y `queue:work`. `RecordatoriosDelDia` es
  `ShouldQueue` con cola `database`: sin worker, los avisos quedan en la tabla `jobs`.

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
