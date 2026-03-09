-- ============================================================
-- ITSM ALEATICA v2.0 — MIGRACIÓN 003: ITSM (MESA DE AYUDA)
-- ============================================================

USE itsm_aleatica;

-- ──────────────────────────────────────
-- CATEGORÍAS (árbol 3 niveles)
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_categorias (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(200) NOT NULL,
    padre_id    INT,
    icono       VARCHAR(50) DEFAULT 'fa-folder',
    color       VARCHAR(7)  DEFAULT '#4A90C4',
    descripcion VARCHAR(300),
    activa      TINYINT(1) DEFAULT 1,
    orden       INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (padre_id) REFERENCES itsm_categorias(id) ON DELETE SET NULL,
    INDEX idx_padre (padre_id)
);

INSERT INTO itsm_categorias (nombre, padre_id, icono, color) VALUES
('Hardware',            NULL, 'fa-server',        '#1A3A5C'),
('Software',            NULL, 'fa-code',           '#4A90C4'),
('Red y Conectividad',  NULL, 'fa-network-wired',  '#00695C'),
('Telefonia',           NULL, 'fa-phone',          '#6A1B9A'),
('ITS / Campo',         NULL, 'fa-road',           '#FF6B00'),
('Accesos y Seguridad', NULL, 'fa-shield-halved',  '#EF5350'),
('Otros',               NULL, 'fa-ellipsis',       '#94A3B8');

-- Subcategorias Hardware
INSERT INTO itsm_categorias (nombre, padre_id, icono, color) VALUES
('Laptop / Desktop',        1, 'fa-laptop',          '#1A3A5C'),
('Servidor',                1, 'fa-database',         '#1A3A5C'),
('Impresora / Scanner',     1, 'fa-print',            '#1A3A5C'),
('UPS / Energia',           1, 'fa-bolt',             '#1A3A5C');

-- Subcategorias Software
INSERT INTO itsm_categorias (nombre, padre_id, icono, color) VALUES
('Sistema Operativo',       2, 'fa-windows',          '#4A90C4'),
('Aplicacion Corporativa',  2, 'fa-briefcase',        '#4A90C4'),
('Antivirus / Seguridad',   2, 'fa-shield-virus',     '#4A90C4'),
('Licencias',               2, 'fa-key',              '#4A90C4');

-- Subcategorias Red
INSERT INTO itsm_categorias (nombre, padre_id, icono, color) VALUES
('Sin conectividad',        3, 'fa-wifi-slash',       '#00695C' ),
('VPN / Acceso Remoto',     3, 'fa-globe',            '#00695C'),
('Switch / Puerto',         3, 'fa-plug',             '#00695C');

-- Subcategorias ITS
INSERT INTO itsm_categorias (nombre, padre_id, icono, color) VALUES
('Poste SOS sin senial',    5, 'fa-tower-broadcast',  '#FF6B00'),
('Camara sin imagen',       5, 'fa-camera',           '#FF6B00'),
('Router campo caido',      5, 'fa-router',           '#FF6B00');

-- ──────────────────────────────────────
-- POLÍTICAS SLA
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_sla_politicas (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    nombre                  VARCHAR(200) NOT NULL,
    prioridad               ENUM('Critica','Alta','Media','Baja') NOT NULL,
    tiempo_respuesta_min    INT NOT NULL COMMENT 'Minutos para primera respuesta',
    tiempo_resolucion_min   INT NOT NULL COMMENT 'Minutos para resolver',
    horario_laboral         TINYINT(1) DEFAULT 1 COMMENT '1=solo horas laborales, 0=24/7',
    aplica_categoria_id     INT COMMENT 'NULL = aplica a todo',
    activa                  TINYINT(1) DEFAULT 1,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aplica_categoria_id) REFERENCES itsm_categorias(id) ON DELETE SET NULL
);

INSERT INTO itsm_sla_politicas (nombre, prioridad, tiempo_respuesta_min, tiempo_resolucion_min, horario_laboral) VALUES
('SLA Critico',  'Critica', 15,   240,  0),  -- 15min respuesta, 4hrs resolucion, 24/7
('SLA Alto',     'Alta',    60,   480,  1),  -- 1hr respuesta, 8hrs resolucion, laboral
('SLA Medio',    'Media',   240,  1440, 1),  -- 4hrs respuesta, 1 dia, laboral
('SLA Bajo',     'Baja',    480,  4320, 1);  -- 8hrs respuesta, 3 dias, laboral

-- ──────────────────────────────────────
-- CATÁLOGO DE SERVICIOS
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_catalogo_servicios (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    codigo                  VARCHAR(20) NOT NULL UNIQUE,
    nombre                  VARCHAR(200) NOT NULL,
    descripcion             TEXT,
    categoria               ENUM('Negocio','Infraestructura','Soporte','Seguridad','Comunicaciones') DEFAULT 'Soporte',
    tipo                    ENUM('Interno','Externo','Compartido') DEFAULT 'Interno',
    propietario_id          INT,
    estado                  ENUM('Activo','Retirado','En_diseno','Obsoleto') DEFAULT 'Activo',
    sla_politica_id         INT,
    costo_mensual_pen       DECIMAL(12,2),
    proveedor_id            INT,
    ci_relacionados         JSON,
    icono                   VARCHAR(50) DEFAULT 'fa-server',
    color                   VARCHAR(7) DEFAULT '#4A90C4',
    visible_portal          TINYINT(1) DEFAULT 1 COMMENT 'Visible en portal de autoservicio',
    notas                   TEXT,
    created_by              INT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (propietario_id)  REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (sla_politica_id) REFERENCES itsm_sla_politicas(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)      REFERENCES adm_usuarios(id) ON DELETE SET NULL
);

-- ──────────────────────────────────────
-- TICKETS (Solicitudes + Incidentes + Eventos)
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_tickets (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(20) NOT NULL UNIQUE COMMENT 'TKT-2025-00001',
    tipo                    ENUM('Solicitud','Incidente','Evento','Alerta') DEFAULT 'Solicitud',
    titulo                  VARCHAR(500) NOT NULL,
    descripcion             TEXT NOT NULL,
    estado                  ENUM('Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado') DEFAULT 'Nuevo',
    prioridad               ENUM('Critica','Alta','Media','Baja') DEFAULT 'Media',
    urgencia                ENUM('Alta','Media','Baja') DEFAULT 'Media',
    impacto                 ENUM('Alto','Medio','Bajo') DEFAULT 'Medio',
    categoria_id            INT,
    servicio_id             INT,
    ci_id                   INT COMMENT 'CI afectado de la CMDB',
    -- Personas
    solicitante_id          INT,
    agente_id               INT,
    grupo_id                INT,
    sede_id                 INT,
    -- Fechas SLA
    fecha_apertura          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_asignacion        DATETIME,
    fecha_primer_respuesta  DATETIME,
    fecha_resolucion        DATETIME,
    fecha_cierre            DATETIME,
    sla_respuesta_limite    DATETIME,
    sla_resolucion_limite   DATETIME,
    sla_respuesta_cumplido  TINYINT(1),
    sla_resolucion_cumplido TINYINT(1),
    -- Origen
    origen                  ENUM('Portal','Email','Telefono','Monitoreo','Manual','API') DEFAULT 'Portal',
    monitor_equipo_id       INT COMMENT 'ID en sistema de monitoreo si origen=Monitoreo',
    -- Vinculacion ITIL
    problema_id             INT,
    cambio_id               INT,
    ticket_padre_id         INT COMMENT 'Para tickets relacionados',
    -- Resolucion
    solucion                TEXT,
    workaround              TEXT,
    kb_articulo_id          INT,
    tiempo_resolucion_min   INT COMMENT 'Minutos reales de resolucion',
    -- Feedback
    calificacion            TINYINT COMMENT '1-5 estrellas',
    comentario_cierre       TEXT,
    -- Auditoria
    created_by              INT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id)    REFERENCES itsm_categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (servicio_id)     REFERENCES itsm_catalogo_servicios(id) ON DELETE SET NULL,
    FOREIGN KEY (ci_id)           REFERENCES cmdb_cis(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitante_id)  REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (agente_id)       REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (grupo_id)        REFERENCES adm_grupos(id) ON DELETE SET NULL,
    FOREIGN KEY (sede_id)         REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_padre_id) REFERENCES itsm_tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)      REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_numero    (numero),
    INDEX idx_estado    (estado),
    INDEX idx_prioridad (prioridad),
    INDEX idx_agente    (agente_id),
    INDEX idx_solicitante (solicitante_id),
    INDEX idx_fecha     (fecha_apertura),
    INDEX idx_sla_resp  (sla_respuesta_limite),
    INDEX idx_sla_res   (sla_resolucion_limite)
);

-- ──────────────────────────────────────
-- COMENTARIOS DE TICKETS
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_comentarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT NOT NULL,
    usuario_id  INT,
    tipo        ENUM('Publico','Interno','Sistema') DEFAULT 'Publico' COMMENT 'Interno=solo agentes',
    contenido   TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)  REFERENCES itsm_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
);

-- ──────────────────────────────────────
-- ADJUNTOS
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_adjuntos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    entidad_tipo    ENUM('ticket','problema','cambio','kb') NOT NULL,
    entidad_id      INT NOT NULL,
    nombre_original VARCHAR(300) NOT NULL,
    ruta_archivo    VARCHAR(500) NOT NULL,
    tamano_bytes    INT,
    mime_type       VARCHAR(100),
    subido_por      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subido_por) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_entidad (entidad_tipo, entidad_id)
);

-- ──────────────────────────────────────
-- PROBLEMAS (Root Cause Analysis)
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_problemas (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(20) NOT NULL UNIQUE COMMENT 'PRB-2025-00001',
    titulo                  VARCHAR(500) NOT NULL,
    descripcion             TEXT,
    estado                  ENUM('Nuevo','En_investigacion','Error_conocido','Resuelto','Cerrado') DEFAULT 'Nuevo',
    prioridad               ENUM('Critica','Alta','Media','Baja') DEFAULT 'Media',
    ci_id                   INT,
    servicio_id             INT,
    propietario_id          INT,
    rca_descripcion         TEXT COMMENT 'Root Cause Analysis narrativo',
    rca_tecnica             ENUM('5_Porques','Ishikawa','Fault_Tree','Cambios_Recientes','Otro'),
    workaround              TEXT,
    solucion_definitiva     TEXT,
    tickets_relacionados    JSON COMMENT 'Array IDs de tickets',
    error_conocido          TINYINT(1) DEFAULT 0,
    fecha_deteccion         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion        DATETIME,
    created_by              INT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ci_id)          REFERENCES cmdb_cis(id) ON DELETE SET NULL,
    FOREIGN KEY (servicio_id)    REFERENCES itsm_catalogo_servicios(id) ON DELETE SET NULL,
    FOREIGN KEY (propietario_id) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)     REFERENCES adm_usuarios(id) ON DELETE SET NULL
);

-- ──────────────────────────────────────
-- CAMBIOS (RFC)
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_cambios (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(20) NOT NULL UNIQUE COMMENT 'RFC-2025-00001',
    titulo                  VARCHAR(500) NOT NULL,
    tipo                    ENUM('Normal','Estandar','Emergencia') DEFAULT 'Normal',
    estado                  ENUM('Borrador','En_revision','Aprobado','En_implementacion','Completado','Rechazado','Cancelado') DEFAULT 'Borrador',
    riesgo                  ENUM('Alto','Medio','Bajo') DEFAULT 'Medio',
    descripcion             TEXT,
    justificacion           TEXT,
    plan_implementacion     TEXT,
    plan_rollback           TEXT,
    impacto_servicio        TEXT,
    ci_afectados            JSON,
    solicitante_id          INT,
    aprobador_id            INT,
    implementador_id        INT,
    fecha_solicitada        DATETIME,
    fecha_inicio_real       DATETIME,
    fecha_fin_real          DATETIME,
    ventana_inicio          DATETIME,
    ventana_fin             DATETIME,
    resultado               ENUM('Exitoso','Parcial','Fallido','Rollback'),
    lecciones_aprendidas    TEXT,
    ticket_origen_id        INT,
    created_by              INT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitante_id)   REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id)     REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (implementador_id) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_origen_id) REFERENCES itsm_tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)       REFERENCES adm_usuarios(id) ON DELETE SET NULL
);

-- ──────────────────────────────────────
-- BASE DE CONOCIMIENTO
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS itsm_kb_articulos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(500) NOT NULL,
    descripcion     VARCHAR(500),
    contenido       LONGTEXT NOT NULL,
    categoria_id    INT,
    visibilidad     ENUM('Publico','Agentes','Privado') DEFAULT 'Agentes',
    estado          ENUM('Borrador','Publicado','Archivado') DEFAULT 'Borrador',
    vistas          INT DEFAULT 0,
    util_si         INT DEFAULT 0,
    util_no         INT DEFAULT 0,
    autor_id        INT,
    tags            JSON,
    ticket_origen_id INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id)    REFERENCES itsm_categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (autor_id)        REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_origen_id) REFERENCES itsm_tickets(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_kb (titulo, descripcion, contenido)
);
