-- =====================================================================
-- DIARIO DE MASCOTAS — Modelo de datos (MySQL 8+)
-- Convenciones: InnoDB, utf8mb4, snake_case en español,
-- PK `id` BIGINT UNSIGNED, timestamps y soft deletes estilo Laravel.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. USUARIOS
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(120)    NOT NULL,
  email             VARCHAR(180)    NOT NULL,
  password          VARCHAR(255)    NOT NULL,
  telefono          VARCHAR(40)     NULL,
  zona_horaria      VARCHAR(64)     NOT NULL DEFAULT 'America/Argentina/Buenos_Aires',
  email_verified_at TIMESTAMP       NULL,
  remember_token    VARCHAR(100)    NULL,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  deleted_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. MASCOTAS — ficha principal
-- ---------------------------------------------------------------------
CREATE TABLE mascotas (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id          BIGINT UNSIGNED NOT NULL,
  nombre              VARCHAR(80)     NOT NULL,
  especie             ENUM('perro','gato','ave','roedor','reptil','otro') NOT NULL DEFAULT 'perro',
  raza                VARCHAR(120)    NULL,
  sexo                ENUM('macho','hembra','desconocido') NOT NULL DEFAULT 'desconocido',
  fecha_nacimiento    DATE            NULL,
  fecha_nacimiento_estimada TINYINT(1) NOT NULL DEFAULT 0, -- 1 = edad aproximada (adopción)
  fecha_adopcion      DATE            NULL,
  color               VARCHAR(80)     NULL,
  tipo_pelaje         ENUM('corto','medio','largo','rizado','duro','sin_pelo','otro') NULL,
  senias_particulares TEXT            NULL,
  descripcion         TEXT            NULL,
  foto_perfil         VARCHAR(255)    NULL,  -- ruta del archivo

  -- Identificación
  microchip           VARCHAR(40)     NULL,
  fecha_microchip     DATE            NULL,
  libreta_sanitaria   VARCHAR(60)     NULL,
  pedigree            VARCHAR(60)     NULL,

  -- Reproductivo
  castrado            TINYINT(1)      NOT NULL DEFAULT 0,
  fecha_castracion    DATE            NULL,
  -- Regla de negocio: si castrado = 1 el módulo de celo se oculta/desactiva.

  -- Seguro / cobertura
  seguro_compania     VARCHAR(120)    NULL,
  seguro_poliza       VARCHAR(80)     NULL,
  seguro_vencimiento  DATE            NULL,

  -- Estado
  activo              TINYINT(1)      NOT NULL DEFAULT 1,
  fecha_fallecimiento DATE            NULL,

  created_at          TIMESTAMP       NULL,
  updated_at          TIMESTAMP       NULL,
  deleted_at          TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_mascotas_usuario (usuario_id),
  UNIQUE KEY uq_mascotas_microchip (microchip),
  CONSTRAINT fk_mascotas_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. GALERÍA DE FOTOS (evolución en el tiempo)
-- ---------------------------------------------------------------------
CREATE TABLE fotos_mascota (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id  BIGINT UNSIGNED NOT NULL,
  ruta        VARCHAR(255)    NOT NULL,
  fecha       DATE            NOT NULL,
  epigrafe    VARCHAR(255)    NULL,
  created_at  TIMESTAMP       NULL,
  updated_at  TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_fotos_mascota_fecha (mascota_id, fecha),
  CONSTRAINT fk_fotos_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. VETERINARIAS — catálogo reutilizable
-- ---------------------------------------------------------------------
CREATE TABLE veterinarias (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  BIGINT UNSIGNED NOT NULL,
  nombre      VARCHAR(140)    NOT NULL,
  direccion   VARCHAR(255)    NULL,
  localidad   VARCHAR(120)    NULL,
  telefono    VARCHAR(40)     NULL,
  whatsapp    VARCHAR(40)     NULL,
  email       VARCHAR(180)    NULL,
  sitio_web   VARCHAR(255)    NULL,
  latitud     DECIMAL(10,7)   NULL,
  longitud    DECIMAL(10,7)   NULL,
  foto        VARCHAR(255)    NULL,
  horarios    VARCHAR(255)    NULL,
  urgencias_24h TINYINT(1)    NOT NULL DEFAULT 0,
  notas       TEXT            NULL,
  activa      TINYINT(1)      NOT NULL DEFAULT 1,
  created_at  TIMESTAMP       NULL,
  updated_at  TIMESTAMP       NULL,
  deleted_at  TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_veterinarias_usuario (usuario_id),
  CONSTRAINT fk_veterinarias_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. VETERINARIOS (profesionales)
-- ---------------------------------------------------------------------
CREATE TABLE veterinarios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id      BIGINT UNSIGNED NOT NULL,
  veterinaria_id  BIGINT UNSIGNED NULL,   -- veterinaria habitual
  nombre          VARCHAR(140)    NOT NULL,
  matricula       VARCHAR(60)     NULL,
  especialidad    VARCHAR(120)    NULL,   -- clínica, traumatología, etología...
  telefono        VARCHAR(40)     NULL,
  email           VARCHAR(180)    NULL,
  foto            VARCHAR(255)    NULL,
  notas           TEXT            NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  deleted_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_veterinarios_usuario (usuario_id),
  KEY idx_veterinarios_veterinaria (veterinaria_id),
  CONSTRAINT fk_veterinarios_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_veterinarios_veterinaria FOREIGN KEY (veterinaria_id)
    REFERENCES veterinarias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. VISITAS AL VETERINARIO
-- ---------------------------------------------------------------------
CREATE TABLE visitas (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id      BIGINT UNSIGNED NOT NULL,
  veterinaria_id  BIGINT UNSIGNED NULL,
  veterinario_id  BIGINT UNSIGNED NULL,
  fecha_hora      DATETIME        NOT NULL,
  tipo            ENUM('rutina','control','urgencia','cirugia','vacunacion','estudios','otro')
                  NOT NULL DEFAULT 'rutina',
  motivo          VARCHAR(255)    NULL,   -- ej: "gastroenteritis"
  diagnostico     TEXT            NULL,
  indicaciones    TEXT            NULL,
  temperatura     DECIMAL(4,1)    NULL,
  costo           DECIMAL(12,2)   NULL,
  moneda          CHAR(3)         NOT NULL DEFAULT 'ARS',
  proximo_control DATE            NULL,   -- dispara recordatorio
  notas           TEXT            NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  deleted_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_visitas_mascota_fecha (mascota_id, fecha_hora),
  KEY idx_visitas_veterinaria (veterinaria_id),
  KEY idx_visitas_veterinario (veterinario_id),
  CONSTRAINT fk_visitas_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_visitas_veterinaria FOREIGN KEY (veterinaria_id)
    REFERENCES veterinarias (id) ON DELETE SET NULL,
  CONSTRAINT fk_visitas_veterinario FOREIGN KEY (veterinario_id)
    REFERENCES veterinarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. MEDICAMENTOS — catálogo (sirve para tratamientos y desparasitaciones)
-- ---------------------------------------------------------------------
CREATE TABLE medicamentos (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id        BIGINT UNSIGNED NULL,  -- NULL = precargado del sistema
  nombre_comercial  VARCHAR(140)    NOT NULL,
  droga             VARCHAR(140)    NULL,
  laboratorio       VARCHAR(120)    NULL,
  presentacion      VARCHAR(120)    NULL,  -- comprimidos 500mg, suspensión 50ml, pipeta
  categoria         ENUM('antibiotico','antiparasitario_interno','antiparasitario_externo',
                         'antiinflamatorio','analgesico','suplemento','dermatologico','otro')
                    NOT NULL DEFAULT 'otro',
  requiere_receta   TINYINT(1)      NOT NULL DEFAULT 0,
  notas             TEXT            NULL,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  deleted_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_medicamentos_usuario (usuario_id),
  KEY idx_medicamentos_nombre (nombre_comercial),
  CONSTRAINT fk_medicamentos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. TRATAMIENTOS — posología prescripta
-- ---------------------------------------------------------------------
CREATE TABLE tratamientos (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id        BIGINT UNSIGNED NOT NULL,
  visita_id         BIGINT UNSIGNED NULL,   -- puede existir sin visita asociada
  medicamento_id    BIGINT UNSIGNED NULL,
  medicamento_libre VARCHAR(140)    NULL,   -- si no está en el catálogo
  dosis             VARCHAR(80)     NOT NULL, -- "1 comprimido", "2.5 ml"
  via               ENUM('oral','topica','inyectable','oftalmica','otica','rectal','otra')
                    NOT NULL DEFAULT 'oral',
  frecuencia_horas  SMALLINT UNSIGNED NULL,   -- 8 = cada 8 hs (para generar tomas)
  veces_por_dia     TINYINT UNSIGNED  NULL,
  fecha_inicio      DATE            NOT NULL,
  fecha_fin         DATE            NULL,
  duracion_dias     SMALLINT UNSIGNED NULL,
  hora_primera_toma TIME            NULL,
  estado            ENUM('activo','finalizado','suspendido') NOT NULL DEFAULT 'activo',
  notas             TEXT            NULL,    -- "dar con comida"
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  deleted_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_tratamientos_mascota (mascota_id, estado),
  KEY idx_tratamientos_visita (visita_id),
  KEY idx_tratamientos_medicamento (medicamento_id),
  CONSTRAINT fk_tratamientos_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_tratamientos_visita FOREIGN KEY (visita_id)
    REFERENCES visitas (id) ON DELETE SET NULL,
  CONSTRAINT fk_tratamientos_medicamento FOREIGN KEY (medicamento_id)
    REFERENCES medicamentos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. TOMAS DE MEDICAMENTO — se autogeneran desde el tratamiento
-- ---------------------------------------------------------------------
CREATE TABLE tomas_medicamento (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tratamiento_id        BIGINT UNSIGNED NOT NULL,
  fecha_hora_programada DATETIME        NOT NULL,
  fecha_hora_real       DATETIME        NULL,
  estado                ENUM('pendiente','administrada','omitida') NOT NULL DEFAULT 'pendiente',
  notas                 VARCHAR(255)    NULL,
  created_at            TIMESTAMP       NULL,
  updated_at            TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_tomas_tratamiento (tratamiento_id, fecha_hora_programada),
  KEY idx_tomas_pendientes (estado, fecha_hora_programada),
  CONSTRAINT fk_tomas_tratamiento FOREIGN KEY (tratamiento_id)
    REFERENCES tratamientos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. VACUNAS — catálogo
-- ---------------------------------------------------------------------
CREATE TABLE vacunas (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre              VARCHAR(120)    NOT NULL,   -- Quíntuple, Antirrábica, Triple felina
  especie             ENUM('perro','gato','ave','roedor','reptil','otro') NOT NULL DEFAULT 'perro',
  descripcion         TEXT            NULL,
  meses_refuerzo      SMALLINT UNSIGNED NULL,     -- 12 = revacunar al año
  obligatoria         TINYINT(1)      NOT NULL DEFAULT 0,
  created_at          TIMESTAMP       NULL,
  updated_at          TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_vacunas_especie (especie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. APLICACIONES DE VACUNA
-- ---------------------------------------------------------------------
CREATE TABLE aplicaciones_vacuna (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id      BIGINT UNSIGNED NOT NULL,
  vacuna_id       BIGINT UNSIGNED NULL,
  vacuna_libre    VARCHAR(120)    NULL,
  visita_id       BIGINT UNSIGNED NULL,
  veterinaria_id  BIGINT UNSIGNED NULL,
  veterinario_id  BIGINT UNSIGNED NULL,
  fecha           DATE            NOT NULL,
  dosis_nro       TINYINT UNSIGNED NULL,   -- 1a, 2a, refuerzo
  marca           VARCHAR(120)    NULL,
  lote            VARCHAR(60)     NULL,
  vencimiento_lote DATE           NULL,
  proxima_dosis   DATE            NULL,    -- dispara recordatorio
  reacciones      TEXT            NULL,
  notas           TEXT            NULL,    -- nota al margen del tratamiento
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  deleted_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_aplic_vacuna_mascota (mascota_id, fecha),
  KEY idx_aplic_vacuna_proxima (proxima_dosis),
  CONSTRAINT fk_aplic_vacuna_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_aplic_vacuna_vacuna FOREIGN KEY (vacuna_id)
    REFERENCES vacunas (id) ON DELETE SET NULL,
  CONSTRAINT fk_aplic_vacuna_visita FOREIGN KEY (visita_id)
    REFERENCES visitas (id) ON DELETE SET NULL,
  CONSTRAINT fk_aplic_vacuna_veterinaria FOREIGN KEY (veterinaria_id)
    REFERENCES veterinarias (id) ON DELETE SET NULL,
  CONSTRAINT fk_aplic_vacuna_veterinario FOREIGN KEY (veterinario_id)
    REFERENCES veterinarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. DESPARASITACIONES
-- ---------------------------------------------------------------------
CREATE TABLE desparasitaciones (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id        BIGINT UNSIGNED NOT NULL,
  medicamento_id    BIGINT UNSIGNED NULL,
  medicamento_libre VARCHAR(140)    NULL,
  visita_id         BIGINT UNSIGNED NULL,
  tipo              ENUM('interna','externa','mixta') NOT NULL DEFAULT 'interna',
  fecha             DATE            NOT NULL,
  dosis             VARCHAR(80)     NULL,
  peso_al_momento   DECIMAL(6,2)    NULL,  -- la dosis suele depender del peso
  proxima_fecha     DATE            NULL,  -- dispara recordatorio
  notas             TEXT            NULL,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  deleted_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_despar_mascota (mascota_id, fecha),
  KEY idx_despar_proxima (proxima_fecha),
  CONSTRAINT fk_despar_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_despar_medicamento FOREIGN KEY (medicamento_id)
    REFERENCES medicamentos (id) ON DELETE SET NULL,
  CONSTRAINT fk_despar_visita FOREIGN KEY (visita_id)
    REFERENCES visitas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. REGISTROS DE PESO
-- ---------------------------------------------------------------------
CREATE TABLE registros_peso (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id  BIGINT UNSIGNED NOT NULL,
  visita_id   BIGINT UNSIGNED NULL,
  fecha       DATE            NOT NULL,
  peso_kg     DECIMAL(6,2)    NOT NULL,
  condicion_corporal TINYINT UNSIGNED NULL, -- escala 1-9
  origen      ENUM('casa','veterinaria') NOT NULL DEFAULT 'casa',
  notas       VARCHAR(255)    NULL,
  created_at  TIMESTAMP       NULL,
  updated_at  TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_peso_mascota_fecha (mascota_id, fecha),
  CONSTRAINT fk_peso_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_peso_visita FOREIGN KEY (visita_id)
    REFERENCES visitas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. ALIMENTOS — catálogo
-- ---------------------------------------------------------------------
CREATE TABLE alimentos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id    BIGINT UNSIGNED NULL,
  marca         VARCHAR(120)    NULL,
  nombre        VARCHAR(140)    NOT NULL,
  tipo          ENUM('balanceado_seco','humedo','casero','barf','snack','suplemento','otro')
                NOT NULL DEFAULT 'balanceado_seco',
  gama          ENUM('estandar','premium','super_premium','medicado') NULL,
  especie       ENUM('perro','gato','ave','roedor','reptil','otro') NOT NULL DEFAULT 'perro',
  etapa         ENUM('cachorro','adulto','senior','todas') NOT NULL DEFAULT 'adulto',
  presentacion  VARCHAR(80)     NULL,   -- bolsa 15 kg, lata 340 g
  medicado      TINYINT(1)      NOT NULL DEFAULT 0, -- renal, hepático, gastrointestinal
  notas         TEXT            NULL,
  created_at    TIMESTAMP       NULL,
  updated_at    TIMESTAMP       NULL,
  deleted_at    TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_alimentos_usuario (usuario_id),
  CONSTRAINT fk_alimentos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. DIETAS — período en el que la mascota come determinado alimento
-- ---------------------------------------------------------------------
CREATE TABLE dietas (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id        BIGINT UNSIGNED NOT NULL,
  alimento_id       BIGINT UNSIGNED NOT NULL,
  veterinario_id    BIGINT UNSIGNED NULL,   -- si fue prescripta
  fecha_inicio      DATE            NOT NULL,
  fecha_fin         DATE            NULL,   -- NULL = dieta vigente
  racion_diaria_g   SMALLINT UNSIGNED NULL,
  tomas_por_dia     TINYINT UNSIGNED  NULL,
  motivo            VARCHAR(255)    NULL,   -- "dieta renal post gastroenteritis"
  prescripta        TINYINT(1)      NOT NULL DEFAULT 0,
  notas             TEXT            NULL,
  created_at        TIMESTAMP       NULL,
  updated_at        TIMESTAMP       NULL,
  deleted_at        TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_dietas_mascota (mascota_id, fecha_inicio),
  KEY idx_dietas_alimento (alimento_id),
  CONSTRAINT fk_dietas_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE,
  CONSTRAINT fk_dietas_alimento FOREIGN KEY (alimento_id)
    REFERENCES alimentos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_dietas_veterinario FOREIGN KEY (veterinario_id)
    REFERENCES veterinarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. CICLOS DE CELO (solo hembras no castradas)
-- ---------------------------------------------------------------------
CREATE TABLE ciclos_celo (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id            BIGINT UNSIGNED NOT NULL,
  fecha_inicio          DATE            NOT NULL,
  fecha_fin             DATE            NULL,
  duracion_dias         SMALLINT UNSIGNED NULL, -- calculado
  intensidad            ENUM('leve','normal','intensa') NULL,
  sintomas              TEXT            NULL,
  hubo_monta            TINYINT(1)      NOT NULL DEFAULT 0,
  proxima_estimada      DATE            NULL,  -- promedio de intervalos previos
  notas                 TEXT            NULL,
  created_at            TIMESTAMP       NULL,
  updated_at            TIMESTAMP       NULL,
  deleted_at            TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_celo_mascota (mascota_id, fecha_inicio),
  KEY idx_celo_proxima (proxima_estimada),
  CONSTRAINT fk_celo_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Regla: proxima_estimada = último fecha_inicio + promedio de intervalos
-- entre ciclos registrados (fallback: 180 días en caninos).
-- El recordatorio se dispara N días antes (config. del usuario, default 14).

-- ---------------------------------------------------------------------
-- 17. ENTRADAS DE DIARIO — bitácora libre
-- ---------------------------------------------------------------------
CREATE TABLE entradas_diario (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id  BIGINT UNSIGNED NOT NULL,
  fecha       DATE            NOT NULL,
  titulo      VARCHAR(160)    NULL,
  contenido   TEXT            NOT NULL,
  categoria   ENUM('general','sintoma','comportamiento','higiene','paseo',
                   'entrenamiento','hito','viaje') NOT NULL DEFAULT 'general',
  animo       ENUM('muy_bajo','bajo','normal','bueno','excelente') NULL,
  created_at  TIMESTAMP       NULL,
  updated_at  TIMESTAMP       NULL,
  deleted_at  TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_diario_mascota_fecha (mascota_id, fecha),
  CONSTRAINT fk_diario_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. ADJUNTOS — polimórfico (estudios, recetas, radiografías, facturas)
-- ---------------------------------------------------------------------
CREATE TABLE adjuntos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  adjuntable_type VARCHAR(120)    NOT NULL,  -- App\Models\Visita, App\Models\Tratamiento...
  adjuntable_id   BIGINT UNSIGNED NOT NULL,
  tipo            ENUM('receta','analisis','radiografia','ecografia','certificado',
                       'factura','foto','otro') NOT NULL DEFAULT 'otro',
  ruta            VARCHAR(255)    NOT NULL,
  nombre_original VARCHAR(255)    NULL,
  mime            VARCHAR(120)    NULL,
  tamanio_bytes   BIGINT UNSIGNED NULL,
  descripcion     VARCHAR(255)    NULL,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_adjuntos_morph (adjuntable_type, adjuntable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. RECORDATORIOS — unifica vacunas, desparasitaciones, celo, controles
-- ---------------------------------------------------------------------
CREATE TABLE recordatorios (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id          BIGINT UNSIGNED NOT NULL,
  tipo                ENUM('vacuna','desparasitacion','celo','control','medicacion',
                           'peso','seguro','personalizado') NOT NULL,
  titulo              VARCHAR(160)    NOT NULL,
  descripcion         TEXT            NULL,
  fecha_objetivo      DATE            NOT NULL,
  dias_anticipacion   SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  hora_notificacion   TIME            NOT NULL DEFAULT '09:00:00',
  recurrente          TINYINT(1)      NOT NULL DEFAULT 0,
  intervalo_dias      SMALLINT UNSIGNED NULL,
  estado              ENUM('pendiente','notificado','completado','descartado')
                      NOT NULL DEFAULT 'pendiente',
  fecha_completado    DATETIME        NULL,
  -- vínculo opcional con el registro que lo originó
  origen_type         VARCHAR(120)    NULL,
  origen_id           BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP       NULL,
  updated_at          TIMESTAMP       NULL,
  PRIMARY KEY (id),
  KEY idx_record_mascota (mascota_id, estado, fecha_objetivo),
  KEY idx_record_pendientes (estado, fecha_objetivo),
  KEY idx_record_morph (origen_type, origen_id),
  CONSTRAINT fk_record_mascota FOREIGN KEY (mascota_id)
    REFERENCES mascotas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- NOTAS DE IMPLEMENTACIÓN PARA CLAUDE CODE
-- =====================================================================
-- 1. Timeline unificado: la vista principal del "diario" mezcla visitas,
--    aplicaciones_vacuna, desparasitaciones, ciclos_celo, registros_peso,
--    dietas y entradas_diario ordenadas por fecha DESC. Conviene resolverlo
--    en el backend con una colección unificada, no con una VIEW SQL.
-- 2. Los recordatorios se generan por observers/eventos:
--      - aplicaciones_vacuna.proxima_dosis  -> recordatorio tipo 'vacuna'
--      - desparasitaciones.proxima_fecha    -> tipo 'desparasitacion'
--      - visitas.proximo_control            -> tipo 'control'
--      - ciclos_celo.proxima_estimada       -> tipo 'celo'
--      - tratamientos activos               -> tomas_medicamento
-- 3. Si mascotas.castrado = 1 se ocultan celo y sus recordatorios pendientes.
-- 4. Solo una dieta puede tener fecha_fin NULL por mascota (dieta vigente):
--    validar a nivel aplicación al crear una nueva.
-- 5. Los catálogos con usuario_id NULL son datos semilla compartidos
--    (vacunas comunes, antiparasitarios habituales, marcas de alimento).
-- =====================================================================
