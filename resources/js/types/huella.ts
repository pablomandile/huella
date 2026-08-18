/** Tipos del dominio de Huella (los serializa MascotaResource y compañía). */

export type OpcionEnum = {
    value: string;
    label: string;
};

/** Versión liviana compartida por Inertia para el selector del header. */
export type MascotaLigera = {
    id: number;
    nombre: string;
    especie: string;
    foto_miniatura_url: string | null;
};

export type Mascota = {
    id: number;
    nombre: string;
    especie: string;
    especie_etiqueta: string;
    raza: string | null;
    sexo: string;
    sexo_etiqueta: string;
    fecha_nacimiento: string | null;
    fecha_nacimiento_estimada: boolean;
    fecha_adopcion: string | null;
    edad: string | null;
    color: string | null;
    tipo_pelaje: string | null;
    tipo_pelaje_etiqueta: string | null;
    senias_particulares: string | null;
    descripcion: string | null;
    microchip: string | null;
    fecha_microchip: string | null;
    libreta_sanitaria: string | null;
    pedigree: string | null;
    castrado: boolean;
    fecha_castracion: string | null;
    seguro_compania: string | null;
    seguro_poliza: string | null;
    seguro_vencimiento: string | null;
    fallecida: boolean;
    fecha_fallecimiento: string | null;
    celo_visible: boolean;
    foto_url: string | null;
    foto_miniatura_url: string | null;
};

export type FotoGaleria = {
    id: number;
    fecha: string;
    epigrafe: string | null;
    url: string;
    miniatura_url: string;
};

export type Alergia = {
    id: number;
    tipo: string;
    tipo_etiqueta: string;
    agente: string;
    severidad: string | null;
    severidad_etiqueta: string | null;
    fecha_deteccion: string | null;
    sintomas: string | null;
    notas: string | null;
};

/* ---------------------------------------------------------------- catálogos */

/**
 * Lo mínimo que los cinco catálogos tienen en común, y lo único que el
 * combo necesita saber para dibujar una opción.
 *
 * `es_semilla` marca los registros precargados del sistema: se ven y se
 * duplican, pero no se editan (regla de negocio 4).
 */
export type ItemCatalogo = {
    id: number;
    es_semilla: boolean;
    etiqueta: string;
    detalle: string | null;
};

export type Veterinaria = ItemCatalogo & {
    nombre: string;
    direccion: string | null;
    localidad: string | null;
    telefono: string | null;
    whatsapp: string | null;
    email: string | null;
    sitio_web: string | null;
    horarios: string | null;
    urgencias_24h: boolean;
    notas: string | null;
    activa: boolean;
};

export type Veterinario = ItemCatalogo & {
    nombre: string;
    matricula: string | null;
    especialidad: string | null;
    telefono: string | null;
    email: string | null;
    notas: string | null;
    activo: boolean;
    veterinaria_id: number | null;
    veterinaria_nombre: string | null;
};

export type Medicamento = ItemCatalogo & {
    nombre_comercial: string;
    droga: string | null;
    laboratorio: string | null;
    presentacion: string | null;
    categoria: string;
    categoria_etiqueta: string;
    requiere_receta: boolean;
    notas: string | null;
};

export type Vacuna = ItemCatalogo & {
    nombre: string;
    especie: string;
    especie_etiqueta: string;
    descripcion: string | null;
    meses_refuerzo: number | null;
    obligatoria: boolean;
};

export type Alimento = ItemCatalogo & {
    marca: string | null;
    nombre: string;
    tipo: string;
    tipo_etiqueta: string;
    gama: string | null;
    gama_etiqueta: string | null;
    especie: string;
    especie_etiqueta: string;
    etapa: string;
    etapa_etiqueta: string;
    presentacion: string | null;
    medicado: boolean;
    notas: string | null;
    /** Foto del paquete, para reconocerlo en la góndola. Por controlador. */
    foto_url: string | null;
    foto_miniatura_url: string | null;
};

/* ---------------------------------------------------------- núcleo clínico */

export type Adjunto = {
    id: number;
    tipo: string;
    tipo_etiqueta: string;
    nombre_original: string | null;
    descripcion: string | null;
    es_imagen: boolean;
    tamanio_legible: string | null;
    /** Siempre por controlador: el disco es privado, no hay URL directa. */
    url: string;
    miniatura_url: string | null;
    descarga_url: string;
};

/** Cuánto del tratamiento se cumplió, para ver la adherencia. */
export type Adherencia = {
    total: number;
    dadas: number;
    pendientes: number;
    salteadas: number;
};

export type Tratamiento = {
    id: number;
    visita_id: number | null;
    medicamento_id: number | null;
    medicamento_libre: string | null;
    nombre_medicamento: string;
    dosis: string;
    via: string;
    via_etiqueta: string;
    frecuencia_horas: number | null;
    veces_por_dia: number | null;
    fecha_inicio: string;
    fecha_fin: string | null;
    duracion_dias: number | null;
    hora_primera_toma: string | null;
    estado: string;
    estado_etiqueta: string;
    notas: string | null;
    /** La indicación entera en una línea: "1 comprimido · cada 8 h · oral". */
    posologia: string;
    adherencia: Adherencia | null;
};

export type Visita = {
    id: number;
    mascota_id: number;
    fecha_hora: string | null;
    /** Ya formateada para el <input datetime-local> de la edición. */
    fecha_hora_local: string | null;
    fecha_legible: string | null;
    tipo: string;
    tipo_etiqueta: string;
    motivo: string | null;
    diagnostico: string | null;
    indicaciones: string | null;
    temperatura: string | null;
    costo: string | null;
    moneda: string;
    proximo_control: string | null;
    notas: string | null;
    veterinaria_id: number | null;
    veterinaria_nombre?: string | null;
    veterinario_id: number | null;
    veterinario_nombre?: string | null;
    tratamientos: Tratamiento[];
    adjuntos: Adjunto[];
};

/** Una fila de "Medicación de hoy". */
export type TomaHoy = {
    id: number;
    hora: string | null;
    fecha_legible: string | null;
    atrasada: boolean;
    estado: string;
    estado_etiqueta: string;
    medicamento: string;
    dosis: string;
    via_etiqueta: string;
    notas_tratamiento: string | null;
    mascota_id: number;
    mascota_nombre: string;
    mascota_foto_url: string | null;
};

/* ------------------------------------------------------------- preventivo */

export type AplicacionVacuna = {
    id: number;
    vacuna_id: number | null;
    vacuna_libre: string | null;
    nombre_vacuna: string;
    visita_id: number | null;
    veterinaria_id: number | null;
    veterinario_id: number | null;
    fecha: string;
    fecha_legible: string;
    dosis_nro: number | null;
    marca: string | null;
    lote: string | null;
    vencimiento_lote: string | null;
    proxima_dosis: string | null;
    reacciones: string | null;
    notas: string | null;
};

export type Desparasitacion = {
    id: number;
    medicamento_id: number | null;
    medicamento_libre: string | null;
    nombre_medicamento: string;
    tipo: string;
    tipo_etiqueta: string;
    fecha: string;
    fecha_legible: string;
    dosis: string | null;
    peso_al_momento: string | null;
    /** "18,4 kg": coma decimal y sin ceros de relleno. */
    peso_legible: string | null;
    proxima_fecha: string | null;
    notas: string | null;
};

/** El semáforo de la ficha: al día, próxima a vencer, vencida o sin datos. */
export type EstadoVacunacion = {
    estado: 'al_dia' | 'proxima' | 'vencida' | 'sin_datos';
    etiqueta: string;
    detalle: string | null;
};

/**
 * Cómo está una fecha de vencimiento. `dias` es negativo si ya pasó, y `texto`
 * viene armado del backend para que la fecha nunca se lea sin su contexto.
 */
export type EstadoVencimiento = {
    estado: 'vigente' | 'por_vencer' | 'vencido';
    dias: number;
    texto: string;
};

/**
 * La documentación de la mascota, agrupada por tipo. Las claves son los valores
 * de `TipoAdjunto::documentosDeMascota()` y siempre vienen, aunque estén vacías.
 */
export type DocumentosDeMascota = {
    libreta_sanitaria: Adjunto[];
    certificado_rabia: Adjunto[];
};

export type Recordatorio = {
    id: number;
    mascota_id: number;
    mascota_nombre?: string;
    tipo: string;
    tipo_etiqueta: string;
    /** Los automáticos no se editan a mano: se cambia su origen. */
    es_automatico: boolean;
    titulo: string;
    descripcion: string | null;
    fecha_objetivo: string;
    fecha_legible: string;
    dias_restantes: number;
    /** Dicho como lo diría una persona: "es mañana", "en 3 semanas". */
    cuando: string;
    vencido: boolean;
    dias_anticipacion: number;
    hora_notificacion: string;
    estado: string;
    estado_etiqueta: string;
    recurrente: boolean;
    intervalo_dias: number | null;
};

/* ------------------------------------------------------------- seguimiento */

export type RegistroPeso = {
    id: number;
    fecha: string;
    fecha_legible: string;
    peso_kg: number;
    /** "18,4 kg": coma decimal y sin ceros de relleno. */
    peso_legible: string;
    condicion_corporal: number | null;
    origen: string;
    origen_etiqueta: string;
    /** Se dibuja distinto: la balanza de la veterinaria no es la de casa. */
    en_veterinaria: boolean;
    visita_id: number | null;
    notas: string | null;
};

export type VariacionPeso = {
    kilos: number;
    texto: string;
    sube: boolean;
};

export type Dieta = {
    id: number;
    alimento_id: number;
    alimento?: string;
    alimento_medicado?: boolean;
    veterinario_id: number | null;
    veterinario?: string | null;
    fecha_inicio: string;
    fecha_fin: string | null;
    periodo: string;
    vigente: boolean;
    racion_diaria_g: number | null;
    tomas_por_dia: number | null;
    racion_legible: string | null;
    motivo: string | null;
    prescripta: boolean;
    notas: string | null;
};

export type CicloCelo = {
    id: number;
    fecha_inicio: string;
    fecha_inicio_legible: string;
    fecha_fin: string | null;
    duracion_dias: number | null;
    en_curso: boolean;
    intensidad: string | null;
    intensidad_etiqueta: string | null;
    sintomas: string | null;
    hubo_monta: boolean;
    proxima_estimada: string | null;
    notas: string | null;
};

/**
 * La estimación del próximo celo viaja siempre con su nivel de confianza: una
 * fecha sola se lee como un dato, y esto es un promedio.
 */
export type EstimacionCelo = {
    fecha: string | null;
    fecha_legible: string | null;
    dias_promedio: number;
    confianza: 'muy_baja' | 'baja' | 'media';
    confianza_etiqueta: string;
    detalle: string;
    intervalos: number[];
    usa_promedio_real: boolean;
    /** La fecha estimada ya pasó: el celo ocurrió y no se cargó. */
    vencida: boolean;
};

/* ------------------------------------------------------------------ diario */

/**
 * Un evento de la línea de tiempo, ya normalizado por el servidor.
 * El ícono y el color los decide el front a partir de `tipo`.
 */
export type EventoTimeline = {
    tipo: string;
    id: number;
    /** Única y estable: es la que evita repetir al paginar. */
    clave: string;
    fecha: string;
    fecha_legible: string;
    titulo: string;
    detalle: string | null;
    url: string | null;
    /* Extras según el tipo. */
    etiqueta_tipo?: string;
    veterinaria?: string | null;
    medicamentos?: number;
    adjuntos?: number;
    proxima_dosis?: string | null;
    vigente?: boolean;
    categoria?: string;
    categoria_etiqueta?: string;
    animo_etiqueta?: string | null;
    contenido?: string;
};

export type FiltrosTimeline = {
    tipos: string[];
    desde: string | null;
    hasta: string | null;
    busqueda: string | null;
};

export type EntradaDiario = {
    id: number;
    fecha: string;
    titulo: string | null;
    contenido: string;
    categoria: string;
    animo: string | null;
};

/** Una fila de "lo que hay que dar hoy" en el dashboard. */
export type TomaDelDia = {
    id: number;
    hora: string | null;
    atrasada: boolean;
    /** El día, solo si está atrasada: la hora sola se lee como de hoy. */
    dia: string | null;
    medicamento: string;
    dosis: string;
    mascota: string;
};
