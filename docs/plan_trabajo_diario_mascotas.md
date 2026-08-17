# Diario de Mascotas — Plan de trabajo técnico

**Stack:** Laravel 11 · Vue 3 · Inertia.js · TailwindCSS · MySQL 8 · Vite
**Target:** PWA instalable, mobile first, funcional en escritorio
**Documentos relacionados:** `especificacion_diario_mascotas.md`, `esquema_diario_mascotas.sql`

> **Nota:** la especificación mencionaba Laravel 12. Este plan usa **Laravel 11**, según lo definido. La diferencia práctica principal: en Laravel 11 se usa `laravel/breeze` como starter kit de autenticación; en 12 serían los starter kits nuevos. El resto del plan es idéntico en ambas versiones.

---

## 0. Decisiones técnicas cerradas

| Punto | Decisión |
|---|---|
| Autenticación | Laravel Breeze (stack Inertia + Vue) |
| Estado cliente | Props de Inertia + composables. Sin Pinia salvo que aparezca estado global real |
| Navegación mobile | Bottom tab bar fija + FAB de carga rápida |
| Navegación desktop | Sidebar lateral, mismo árbol de rutas |
| Estrategia PWA | `vite-plugin-pwa` con Workbox, `registerType: 'prompt'` |
| Offline | Solo shell y assets cacheados. **Sin escritura offline en v1** |
| Imágenes | Conversión a WebP al subir, con Intervention Image |
| Adjuntos | Disco `local` privado, servidos por controlador con Policy |
| PDF | `barryvdh/laravel-dompdf` |
| Gráficos | `chart.js` + `vue-chartjs` |
| Fechas | `dayjs` en el front, Carbon en el back |
| Tests | Pest |
| Formato | Laravel Pint + Prettier con plugin de Tailwind |

---

## 1. Setup inicial

### 1.1 Proyecto base

```bash
composer create-project laravel/laravel:^11.0 diario-mascotas
cd diario-mascotas
composer require laravel/breeze --dev
php artisan breeze:install vue
npm install
```

### 1.2 Paquetes backend

```bash
composer require intervention/image-laravel
composer require barryvdh/laravel-dompdf
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
composer require --dev laravel/pint
```

### 1.3 Paquetes frontend

```bash
npm i -D vite-plugin-pwa workbox-window
npm i chart.js vue-chartjs dayjs @vueuse/core
npm i -D prettier prettier-plugin-tailwindcss @tailwindcss/forms
```

### 1.4 Configuración de entorno

- `.env`: conexión MySQL, `APP_TIMEZONE=America/Argentina/Buenos_Aires`, `FILESYSTEM_DISK=local`.
- `config/app.php`: locale `es`, faker locale `es_AR`.
- Publicar traducciones de validación al español.
- `php artisan storage:link` (solo para assets públicos; los adjuntos clínicos **no** van ahí).

---

## 2. Estructura de carpetas

```
app/
├── Enums/                 # Especie, TipoVisita, EstadoTratamiento, TipoRecordatorio...
├── Http/
│   ├── Controllers/
│   │   ├── MascotaController.php
│   │   ├── VisitaController.php
│   │   ├── TratamientoController.php
│   │   ├── Catalogo/      # Veterinaria, Veterinario, Medicamento, Vacuna, Alimento
│   │   └── AdjuntoController.php
│   ├── Requests/          # Un FormRequest por acción de escritura
│   └── Resources/         # Resources para payloads de Inertia
├── Models/
├── Observers/             # VacunaObserver, DesparasitacionObserver, CeloObserver...
├── Policies/
├── Services/
│   ├── GeneradorTomasService.php
│   ├── GeneradorRecordatoriosService.php
│   ├── EstimadorCeloService.php
│   ├── TimelineService.php
│   └── ImagenService.php
└── Jobs/
    └── ProcesarRecordatoriosDiarios.php

resources/js/
├── Layouts/
│   ├── AppLayout.vue          # Decide sidebar vs bottom nav según breakpoint
│   ├── Partials/BottomNav.vue
│   └── Partials/Sidebar.vue
├── Pages/
│   ├── Dashboard.vue
│   ├── Mascotas/
│   ├── Visitas/
│   ├── Salud/                 # Vacunas, Desparasitaciones
│   ├── Seguimiento/           # Peso, Dietas, Celo
│   ├── Diario/
│   ├── Catalogos/
│   └── Recordatorios/
├── Components/
│   ├── Form/                  # Input, Select, DateField, Combobox, FileUpload
│   ├── Ui/                    # Card, Sheet, Modal, EmptyState, Fab, Chip
│   ├── Timeline/
│   └── Pwa/InstallPrompt.vue
└── composables/
    ├── usePwaInstall.js
    ├── useOnlineStatus.js
    └── useFormatoFecha.js
```

---

## 3. Fases de implementación

Cada fase termina con algo usable. No se avanza a la siguiente sin que la anterior esté navegable en el celular.

---

### Fase 0 — Fundaciones

**Objetivo:** proyecto levantando, autenticación funcionando, layout mobile listo.

| # | Tarea |
|---|---|
| 0.1 | Setup del proyecto y paquetes (sección 1) |
| 0.2 | Breeze instalado, vistas de auth traducidas al español |
| 0.3 | Migración de `usuarios` ajustada (teléfono, zona horaria) |
| 0.4 | Tema Tailwind: paleta, tipografía, escala de espaciado, `safe-area-inset` para iOS |
| 0.5 | `AppLayout` responsive: bottom nav en `< md`, sidebar en `>= md` |
| 0.6 | Componentes base de formulario e UI |
| 0.7 | Pint + Prettier + configuración de Pest |

**Criterio de aceptación:** registro, login y logout funcionan; el layout se ve correcto en un iPhone SE y en un monitor de 27".

---

### Fase 1 — PWA

Se hace temprano, no al final. Instalarla desde el día uno cambia cómo se prueba todo lo demás.

| # | Tarea |
|---|---|
| 1.1 | Configurar `vite-plugin-pwa` en `vite.config.js` |
| 1.2 | Generar el set de íconos (192, 256, 384, 512, maskable) |
| 1.3 | Manifest completo (ver 4.1) |
| 1.4 | Service worker con estrategias de cache (ver 4.2) |
| 1.5 | Composable `usePwaInstall` capturando `beforeinstallprompt` |
| 1.6 | Componente `InstallPrompt.vue` con banner y entrada en el menú |
| 1.7 | Instrucciones manuales para iOS/Safari |
| 1.8 | Prompt de actualización cuando hay service worker nuevo |
| 1.9 | Página offline de fallback |
| 1.10 | Indicador de estado sin conexión en la UI |

**Criterio de aceptación:** la app se instala en Android y en escritorio desde un botón propio dentro de la interfaz; en iOS aparece el instructivo; Lighthouse da PWA installable en verde.

---

### Fase 2 — Mascotas

| # | Tarea |
|---|---|
| 2.1 | Migraciones `mascotas` y `fotos_mascota` |
| 2.2 | Modelos, enums, factories, seeders |
| 2.3 | `MascotaPolicy` — un usuario solo ve lo suyo |
| 2.4 | CRUD completo con FormRequests |
| 2.5 | `ImagenService`: redimensionado y conversión a WebP |
| 2.6 | Subida de foto de perfil desde cámara del celular |
| 2.7 | Galería con vista por fecha |
| 2.8 | Selector de mascota activa persistido en sesión |
| 2.9 | Cálculo de edad a partir de la fecha de nacimiento, contemplando "estimada" |
| 2.10 | Ocultamiento condicional del módulo de celo según sexo y castración |

**Criterio de aceptación:** se puede dar de alta una mascota con foto desde el celular en menos de un minuto.

---

### Fase 3 — Catálogos

| # | Tarea |
|---|---|
| 3.1 | Migraciones: `veterinarias`, `veterinarios`, `medicamentos`, `vacunas`, `alimentos` |
| 3.2 | CRUD de cada catálogo |
| 3.3 | Seeder con datos semilla (`usuario_id` nulo): vacunas y antiparasitarios de uso habitual en Argentina |
| 3.4 | Componente `Combobox` con búsqueda, creación al vuelo y opción "no está en la lista" |
| 3.5 | Regla: los registros semilla no se editan, se duplican |

**Criterio de aceptación:** desde el formulario de una visita se puede crear una veterinaria nueva sin perder lo cargado.

---

### Fase 4 — Núcleo clínico

| # | Tarea |
|---|---|
| 4.1 | Migraciones `visitas`, `tratamientos`, `tomas_medicamento`, `adjuntos` |
| 4.2 | CRUD de visitas |
| 4.3 | Formulario de visita con secciones desplegables para crear tratamientos, vacunas, desparasitaciones y peso en el mismo envío |
| 4.4 | `GeneradorTomasService`: genera las tomas según frecuencia y fecha de inicio |
| 4.5 | Regla de edición: regenerar solo tomas futuras pendientes |
| 4.6 | Pantalla "Medicación de hoy" con marcado de toma en un tap |
| 4.7 | Subida de adjuntos con Policy y controlador de descarga firmado |
| 4.8 | Cierre automático de tratamientos vencidos |

**Criterio de aceptación:** cargar una visita por gastroenteritis con dos medicamentos y una receta adjunta, sin salir de la pantalla.

---

### Fase 5 — Preventivo y recordatorios

| # | Tarea |
|---|---|
| 5.1 | Migraciones `aplicaciones_vacuna`, `desparasitaciones`, `recordatorios` |
| 5.2 | CRUD de vacunas y desparasitaciones |
| 5.3 | Precarga de próxima dosis según `meses_refuerzo` del catálogo, editable |
| 5.4 | `GeneradorRecordatoriosService` + observers de cada entidad origen |
| 5.5 | Job diario `ProcesarRecordatoriosDiarios` en el scheduler |
| 5.6 | Notificaciones por email con Markdown mailables |
| 5.7 | Bandeja de recordatorios: completar, posponer, descartar |
| 5.8 | Semáforo de estado de vacunación en la ficha |

**Criterio de aceptación:** al cargar una vacuna con refuerzo a los 12 meses, aparece automáticamente un recordatorio y llega el mail en la fecha correspondiente.

---

### Fase 6 — Seguimiento

| # | Tarea |
|---|---|
| 6.1 | Migraciones `registros_peso`, `alimentos`/`dietas`, `ciclos_celo` |
| 6.2 | Carga rápida de peso desde el dashboard |
| 6.3 | Gráfico de evolución de peso con selector de rango |
| 6.4 | Gestión de dietas con cierre automático de la anterior |
| 6.5 | Validación de dieta vigente única |
| 6.6 | Registro de ciclos de celo |
| 6.7 | `EstimadorCeloService`: promedio de intervalos, fallback de 180 días, con nivel de confianza visible |
| 6.8 | Descarte de recordatorios de celo al marcar castración |

**Criterio de aceptación:** con tres ciclos cargados, la estimación del próximo usa el promedio real y no el valor por defecto.

---

### Fase 7 — Diario, timeline y exportación

| # | Tarea |
|---|---|
| 7.1 | Migración y CRUD de `entradas_diario` |
| 7.2 | `TimelineService`: unifica eventos de todos los módulos, ordenados y paginados por cursor |
| 7.3 | UI de timeline con íconos y color por tipo |
| 7.4 | Filtros por tipo y rango de fechas |
| 7.5 | Búsqueda de texto sobre motivos, diagnósticos y notas |
| 7.6 | Dashboard completo (sección 5 de la especificación) |
| 7.7 | Exportación a PDF de la historia clínica |
| 7.8 | Exportación de datos del usuario en JSON |

**Criterio de aceptación:** el timeline de una mascota con 200 eventos carga en menos de un segundo y pagina sin saltos.

---

### Fase 8 — Pulido y salida

| # | Tarea |
|---|---|
| 8.1 | Estados vacíos con llamados a la acción en todas las pantallas |
| 8.2 | Revisión de accesibilidad: contraste, foco, labels |
| 8.3 | Lighthouse: performance, PWA y accesibilidad |
| 8.4 | Optimización de imágenes: WebP, `srcset`, lazy loading |
| 8.5 | Revisión de N+1 con eager loading |
| 8.6 | Tests de features críticas |
| 8.7 | Manejo de errores y páginas 403/404/500 |
| 8.8 | Seeder de demo para probar con datos reales |
| 8.9 | Deploy y configuración del cron |

---

## 4. PWA en detalle

### 4.1 Manifest

```js
// vite.config.js
VitePWA({
  registerType: 'prompt',
  injectRegister: 'auto',
  manifest: {
    name: 'Diario de Mascotas',
    short_name: 'Mascotas',
    description: 'Historial de salud y vida de tus mascotas',
    theme_color: '#0f766e',
    background_color: '#ffffff',
    display: 'standalone',
    orientation: 'portrait',
    scope: '/',
    start_url: '/dashboard',
    lang: 'es-AR',
    categories: ['health', 'lifestyle'],
    icons: [
      { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' }
    ],
    shortcuts: [
      { name: 'Cargar peso', url: '/peso/crear' },
      { name: 'Nueva visita', url: '/visitas/crear' },
      { name: 'Medicación de hoy', url: '/medicacion' }
    ]
  }
})
```

### 4.2 Estrategias de cache

| Recurso | Estrategia |
|---|---|
| Assets de Vite (JS/CSS) | `CacheFirst` con revisión por hash |
| Íconos y fuentes | `CacheFirst`, 30 días |
| Imágenes subidas | `StaleWhileRevalidate`, máximo 60 entradas |
| Respuestas de Inertia | **Sin cache.** Son datos clínicos, no se sirven viejos |
| Navegación sin conexión | Fallback a `/offline` |

> Decisión deliberada: no cachear datos. Mostrar una dosis desactualizada es peor que mostrar un cartel de "sin conexión".

### 4.3 Botón de instalación

`usePwaInstall.js` debe:

1. Escuchar `beforeinstallprompt`, hacer `preventDefault()` y guardar el evento.
2. Exponer `puedeInstalar`, `instalar()` y `yaInstalada`.
3. Detectar modo standalone con `display-mode: standalone` y `navigator.standalone` en iOS.
4. Detectar Safari en iOS, donde el evento no existe, y activar el instructivo manual.
5. Recordar si el usuario descartó el banner (localStorage), y no volver a mostrarlo por 30 días.

Puntos de entrada en la UI:

- **Banner** discreto al pie, tras la segunda visita, descartable.
- **Ítem fijo "Instalar app"** en el menú de usuario, siempre visible mientras no esté instalada.
- **Modal para iOS** con los pasos ilustrados: Compartir → Agregar a inicio.

### 4.4 Actualizaciones

Con `registerType: 'prompt'`, cuando hay una versión nueva se muestra un toast del tipo "Hay una actualización disponible" con un botón para recargar. Nunca actualizar en silencio mientras el usuario está cargando un formulario.

---

## 5. Consideraciones mobile first

- **Bottom nav** con cinco destinos: Inicio, Timeline, Cargar (FAB central), Salud, Más.
- **FAB central** que abre un sheet con las acciones frecuentes: peso, visita, medicación, nota.
- **Sheets en vez de modales** en pantallas chicas.
- **Inputs con teclado correcto**: `inputmode="decimal"` para peso, `type="date"` nativo, `type="tel"` para teléfonos.
- **Áreas táctiles** de 44 px mínimo.
- **`env(safe-area-inset-bottom)`** en la barra inferior, para el notch de iOS.
- **Cámara directa** en la subida de fotos: `capture="environment"`.
- **Sin hover** como único indicador de nada.
- **Desktop**: el mismo árbol de rutas, con sidebar y grillas de más columnas. No es una app aparte.

---

## 6. Convenciones

- Nombres de tablas, columnas, modelos y rutas **en español**, como en el esquema.
- Un FormRequest por cada acción de escritura. Nada de validación en el controlador.
- Policies en todos los modelos que pertenezcan a un usuario. `$this->authorize()` siempre.
- Enums de PHP 8.1 para todos los ENUM del esquema.
- Los servicios contienen la lógica de negocio; los controladores solo orquestan.
- Los observers generan recordatorios; nunca se crean a mano desde un controlador.
- Toda consulta de listado con eager loading explícito.
- Soft deletes en las entidades principales, como define el esquema.

---

## 7. Orden sugerido para trabajar con Claude Code

1. Fase 0 completa en una sesión, verificando que Breeze quede funcionando.
2. Fase 1 (PWA) en una sesión aparte: es autocontenida y conviene no mezclarla.
3. De la fase 2 en adelante, **una sesión por fase**, con este orden interno: migraciones → modelos y enums → factories y seeders → policies → servicios → controladores y requests → páginas Vue → tests.
4. Al terminar cada fase, correr Pint, Prettier y los tests antes de pasar a la siguiente.
5. Mantener un `CLAUDE.md` en la raíz con las convenciones de la sección 6, para que no haya que repetirlas en cada sesión.

---

## 8. Riesgos identificados

| Riesgo | Mitigación |
|---|---|
| El formulario de visita se vuelve enorme y confuso | Secciones colapsables, todas opcionales salvo fecha y tipo |
| Instalación PWA en iOS, que no soporta el prompt nativo | Instructivo manual explícito, asumido desde el diseño |
| La generación de tomas explota con tratamientos largos | Límite de generación a 90 días; regenerar por lotes si hace falta |
| Recordatorios duplicados al editar registros | Clave de idempotencia por `origen_type` + `origen_id` + `tipo` |
| Adjuntos pesados desde la cámara del celular | Compresión en el cliente antes de subir, más límite de 10 MB |
| Zona horaria en los recordatorios | Guardar en UTC, mostrar y calcular en la zona del usuario |
