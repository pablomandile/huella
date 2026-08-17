# Diario de Mascotas — Especificación de alcance y funcionalidades

**Versión:** 0.1 (borrador inicial)
**Fecha:** agosto 2026
**Estado:** definición previa a implementación

---

## 1. Visión del producto

Una aplicación web personal para llevar el historial completo de salud y vida cotidiana de una o más mascotas: la historia clínica que el veterinario no te da, en un solo lugar y siempre a mano.

El eje del producto es el **diario**: una línea de tiempo única donde conviven las visitas al veterinario, las vacunas, las desparasitaciones, los cambios de alimento, los pesos, los ciclos de celo y las notas del día a día.

### Problema que resuelve

- La libreta sanitaria de papel se pierde, se moja y no tiene lugar para notas.
- Nadie se acuerda de cuándo fue la última desparasitación ni cuándo toca el refuerzo.
- En una urgencia, no se tiene a mano el historial, las alergias ni la medicación en curso.
- Con varias mascotas, la información se mezcla.

### Principios de diseño

1. **Cargar tiene que ser rápido.** Si registrar una desparasitación lleva más de 30 segundos, nadie lo hace.
2. **Nunca bloquear al usuario.** Si el medicamento o la veterinaria no están en el catálogo, se permite texto libre.
3. **El sistema recuerda, el usuario no.** Todo dato con fecha futura genera un recordatorio automático.
4. **Mobile first.** La carga real ocurre en la veterinaria, con el celular en la mano.

---

## 2. Alcance

### Dentro del alcance (v1)

- Gestión de múltiples mascotas por usuario.
- Ficha completa de la mascota, con identificación y fotos.
- Registro de visitas veterinarias, con diagnóstico e indicaciones.
- Tratamientos con posología y seguimiento de tomas.
- Vacunas y desparasitaciones, con cálculo de la próxima aplicación.
- Catálogos reutilizables: veterinarias, veterinarios, medicamentos, vacunas, alimentos.
- Control de peso con gráfico de evolución.
- Dietas por período.
- Ciclo de celo con estimación del siguiente.
- Recordatorios y notificaciones.
- Diario libre con categorías.
- Adjuntos (recetas, análisis, radiografías).
- Exportación del historial clínico a PDF.

### Fuera del alcance (v1)

- Aplicación móvil nativa (se resuelve con PWA responsive).
- Portal para veterinarios o carga por parte del profesional.
- Compartir mascotas entre varios usuarios (multi-cuidador).
- Integración con tiendas, turnos online o pagos.
- Diagnóstico automático o sugerencias clínicas. **El sistema registra, no aconseja.**
- Geolocalización y búsqueda de veterinarias cercanas.
- Red social o perfiles públicos.

### Explícitamente diferido a v2

| Funcionalidad | Motivo |
|---|---|
| Multi-cuidador con permisos | Requiere modelo de roles y compartición |
| Sincronización offline | Complejidad alta; evaluar según uso real |
| Notificaciones push | Empezar con email y recordatorios in-app |
| Registro de gastos y reportes | Los campos de costo ya existen; el módulo de análisis viene después |
| Módulo de paseos y actividad | Depende de integraciones con wearables |

---

## 3. Actores

| Actor | Descripción |
|---|---|
| **Dueño / usuario** | Único rol en v1. Da de alta mascotas y carga toda la información. |
| **Sistema (jobs programados)** | Genera recordatorios, calcula próximas fechas, envía notificaciones. |
| **Veterinario** | Entidad de datos, no usuario del sistema en v1. |

---

## 4. Módulos funcionales

### 4.1 Ficha de mascota

Alta, edición y baja lógica. Campos: nombre, especie, raza, sexo, fecha de nacimiento (con marca de "estimada" para adopciones), fecha de adopción, color, tipo de pelaje, señas particulares, descripción libre y foto de perfil.

Identificación: microchip con fecha de implantación, número de libreta sanitaria, pedigree.

Estado reproductivo: castrado sí/no con fecha. **Si está castrado, el módulo de celo se oculta.**

Seguro: compañía, número de póliza y vencimiento, que genera recordatorio.

Estado: activa / inactiva / fallecida. Las mascotas fallecidas conservan todo su historial y se pueden consultar en modo lectura.

**Galería:** fotos con fecha y epígrafe, para ver la evolución a lo largo de los años.

---

### 4.2 Catálogo de veterinarias y veterinarios

Alta única y reutilización en cada registro, para no volver a tipear los mismos datos.

**Veterinaria:** nombre, dirección, localidad, teléfono, WhatsApp, email, sitio web, foto, horarios, flag de urgencias 24 h, notas.

**Veterinario:** nombre, matrícula, especialidad, contacto, foto, veterinaria habitual.

Una visita puede referenciar veterinaria, veterinario, ambos o ninguno.

---

### 4.3 Visitas veterinarias

Registro de cada consulta con fecha y hora, tipo (rutina, control, urgencia, cirugía, vacunación, estudios), motivo, diagnóstico, indicaciones, temperatura, costo y fecha de próximo control.

Desde una visita se puede crear directamente, en el mismo flujo:

- Uno o más tratamientos con su posología.
- Una aplicación de vacuna.
- Una desparasitación.
- Un registro de peso.
- Un cambio de dieta.
- Adjuntos (receta, estudios).

**Criterio de aceptación:** cargar una visita por gastroenteritis con dos medicamentos y una receta adjunta debe poder hacerse sin salir de la pantalla de la visita.

---

### 4.4 Tratamientos y tomas

Cada tratamiento define medicamento (del catálogo o libre), dosis, vía de administración, frecuencia en horas o veces por día, fecha de inicio, duración y notas del tipo "dar con comida".

Al guardarlo, el sistema **genera automáticamente las tomas** en la tabla correspondiente, según la frecuencia y la hora de la primera toma.

Cada toma se marca como pendiente, administrada u omitida, con la hora real. Eso permite ver la adherencia al tratamiento.

Estados del tratamiento: activo, finalizado, suspendido. Al pasar la fecha de fin se finaliza solo.

---

### 4.5 Vacunas

Catálogo de vacunas por especie, con meses de refuerzo sugeridos (semilla: quíntuple, séxtuple, antirrábica, triple felina, leucemia felina).

Cada aplicación registra: fecha, número de dosis, marca, lote, vencimiento del lote, veterinaria y veterinario, reacciones adversas, notas al margen y **fecha de próxima dosis**, que se precarga con los meses de refuerzo del catálogo pero es editable.

La próxima dosis genera un recordatorio automático.

---

### 4.6 Desparasitaciones

Tipo (interna, externa, mixta), medicamento utilizado, dosis, peso al momento de la aplicación (la dosis suele depender del peso), fecha y próxima fecha estimada.

---

### 4.7 Control de peso

Registro con fecha, peso en kg, condición corporal opcional (escala 1-9) y origen (casa o veterinaria).

**Visualización:** gráfico de evolución con selector de rango. Los pesos tomados en visitas se marcan diferenciados en el gráfico.

---

### 4.8 Alimentación y dietas

**Catálogo de alimentos:** marca, nombre, tipo (balanceado seco, húmedo, casero, BARF, snack, suplemento), gama, especie, etapa de vida, presentación y flag de medicado.

**Dietas:** vinculan una mascota con un alimento durante un período, con fecha de inicio, fecha de fin (nula si está vigente), ración diaria en gramos, tomas por día, motivo y si fue prescripta por un veterinario.

Solo puede haber una dieta vigente por mascota. Al iniciar una nueva, la anterior se cierra automáticamente con la fecha del día anterior.

El historial de dietas se ve en la línea de tiempo, lo cual es útil para correlacionar cambios de alimento con síntomas digestivos.

---

### 4.9 Ciclo de celo

Solo visible en hembras no castradas.

Registro de fecha de inicio, fecha de fin, duración calculada, intensidad, síntomas observados y si hubo monta.

**Estimación del próximo celo:** promedio de los intervalos entre los ciclos registrados. Con menos de dos ciclos cargados, se usa un valor por defecto de 180 días en caninos, indicando claramente que es una estimación de baja confianza.

El recordatorio se dispara con la anticipación configurada por el usuario (por defecto, 14 días).

---

### 4.10 Recordatorios

Tabla única que centraliza todo lo que hay que recordar. Se generan automáticamente desde:

| Origen | Tipo de recordatorio |
|---|---|
| `aplicaciones_vacuna.proxima_dosis` | vacuna |
| `desparasitaciones.proxima_fecha` | desparasitacion |
| `visitas.proximo_control` | control |
| `ciclos_celo.proxima_estimada` | celo |
| `mascotas.seguro_vencimiento` | seguro |
| Alta manual | personalizado |

Cada recordatorio tiene días de anticipación, hora de notificación, estado (pendiente, notificado, completado, descartado) y opción de recurrencia.

Un job diario evalúa los pendientes y dispara las notificaciones por email e in-app.

---

### 4.11 Diario

Entradas libres con fecha, título, contenido, categoría (general, síntoma, comportamiento, higiene, paseo, entrenamiento, hito, viaje) y ánimo opcional.

Sirve para lo que no encaja en ningún módulo estructurado: "hoy vomitó dos veces", "primera vez que sube solo al auto", "le tenemos que cortar las uñas".

---

### 4.12 Adjuntos

Sistema polimórfico: cualquier registro (visita, tratamiento, vacuna, entrada de diario) puede tener archivos asociados.

Tipos: receta, análisis, radiografía, ecografía, certificado, factura, foto.

Formatos: JPG, PNG, WebP, PDF. Límite de 10 MB por archivo.

---

### 4.13 Línea de tiempo unificada

La pantalla principal de cada mascota. Mezcla, ordenados por fecha descendente, todos los eventos de todos los módulos, con íconos y colores por tipo.

Filtros por tipo de evento y por rango de fechas. Búsqueda por texto sobre motivos, diagnósticos y notas.

---

### 4.14 Exportación

Generación de un PDF con la historia clínica completa o filtrada por rango de fechas, pensado para llevar a una consulta con un veterinario nuevo o a un viaje.

Incluye: ficha, vacunas, desparasitaciones, tratamientos, visitas, curva de peso y alergias registradas.

---

## 5. Dashboard

Vista de entrada al sistema, con:

- Selector de mascota activa (o vista consolidada si hay varias).
- Próximos recordatorios (siguientes 30 días).
- Tomas de medicación pendientes para hoy.
- Último peso y variación respecto al registro anterior.
- Dieta vigente.
- Última visita al veterinario.
- Estado de vacunación: al día / vencida / próxima a vencer.

---

## 6. Requisitos no funcionales

| Requisito | Definición |
|---|---|
| **Responsive** | Mobile first. La carga en la veterinaria se hace desde el celular. |
| **Rendimiento** | Carga de la línea de tiempo con paginación; imágenes en WebP con lazy loading. |
| **Privacidad** | Datos de una cuenta jamás visibles para otra. Adjuntos servidos tras verificar propiedad, nunca por URL pública directa. |
| **Backup** | Exportación completa de los datos del usuario en JSON. |
| **Idioma** | Español rioplatense. Estructura preparada para i18n, sin implementarla en v1. |
| **Zona horaria** | Configurable por usuario; los recordatorios respetan su huso. |
| **Accesibilidad** | Contraste AA, navegación por teclado, labels en todos los formularios. |
| **Auditoría** | Soft deletes en todas las entidades principales. Nada se borra de verdad. |

---

## 7. Stack propuesto

- **Backend:** Laravel 12, PHP 8.3+
- **Base de datos:** MySQL 8
- **Frontend:** Vue 3 + Inertia.js + TailwindCSS
- **Gráficos:** Chart.js o ApexCharts
- **Imágenes:** Intervention Image, conversión automática a WebP
- **PDF:** DomPDF o Browsershot
- **Jobs:** scheduler de Laravel para el job diario de recordatorios
- **Autenticación:** Laravel Breeze con Inertia

---

## 8. Reglas de negocio

1. Si `mascotas.castrado = 1`, el módulo de celo se oculta y sus recordatorios pendientes se descartan.
2. Solo una dieta por mascota puede tener `fecha_fin` nula.
3. Al guardar un tratamiento con frecuencia definida, se generan las tomas correspondientes hasta la fecha de fin.
4. Editar la frecuencia de un tratamiento regenera únicamente las tomas futuras pendientes; las administradas no se tocan.
5. Los catálogos con `usuario_id` nulo son datos semilla compartidos y no editables por el usuario; puede duplicarlos para personalizar.
6. Una mascota marcada como fallecida pasa a modo lectura y sus recordatorios pendientes se descartan.
7. Los cálculos de próxima fecha son sugerencias precargadas y siempre editables por el usuario.
8. El sistema no emite recomendaciones clínicas de ningún tipo.

---

## 9. Fases de implementación

**Fase 1 — Base**
Autenticación, CRUD de mascotas, galería de fotos, dashboard mínimo.

**Fase 2 — Catálogos**
Veterinarias, veterinarios, medicamentos, vacunas, alimentos, con datos semilla.

**Fase 3 — Núcleo clínico**
Visitas, tratamientos, tomas de medicamento, adjuntos.

**Fase 4 — Preventivo**
Vacunas, desparasitaciones, motor de recordatorios y notificaciones.

**Fase 5 — Seguimiento**
Peso con gráficos, dietas, ciclo de celo.

**Fase 6 — Diario y cierre**
Entradas de diario, línea de tiempo unificada, búsqueda, exportación a PDF.

---

## 10. Preguntas abiertas

1. ¿El nombre definitivo del producto?
2. ¿Notificaciones por email en v1, o alcanza con los avisos in-app?
3. ¿Se precargan catálogos de vacunas y antiparasitarios con nomenclatura argentina, o se deja vacío para que el usuario cargue?
4. ¿La exportación a PDF entra en v1 o se difiere?
5. ¿Se contempla desde ya el multi-cuidador en el modelo de datos, aunque no se implemente?
