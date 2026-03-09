-- ============================================================
-- ITSM ALEATICA v2.0 — MIGRACIÓN 001: BASE Y ADMINISTRACIÓN
-- ITIL 4 | PHP 8.3 | MySQL 8
-- Ejecutar en orden: 001 → 002 → ... → 009
-- ============================================================

CREATE DATABASE IF NOT EXISTS itsm_aleatica
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE itsm_aleatica;

-- ──────────────────────────────────────
-- ROLES Y PERMISOS
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200),
    permisos    JSON COMMENT 'Array de permisos: ["tickets.ver","tickets.crear",...]',
    color       VARCHAR(7) DEFAULT '#4CAF50' COMMENT 'Color badge en UI',
    es_sistema  TINYINT(1) DEFAULT 0 COMMENT '1=no se puede eliminar',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO adm_roles (nombre, descripcion, color, es_sistema, permisos) VALUES
('Admin',         'Acceso total al sistema',                          '#EF5350', 1, '["*"]'),
('Supervisor_TI', 'Supervisa equipo TI, reportes y aprobaciones',     '#FF6B00', 1, '["tickets.*","cmdb.*","reportes.*","cambios.aprobar"]'),
('Agente_N1',     'Primer nivel soporte, atiende solicitudes basicas', '#4A90C4', 1, '["tickets.ver","tickets.crear","tickets.editar","kb.ver"]'),
('Agente_N2',     'Segundo nivel, incidentes y problemas complejos',   '#1A3A5C', 1, '["tickets.*","problemas.*","cmdb.ver","cambios.crear"]'),
('Agente_N3',     'Tercer nivel, especialista infraestructura',        '#6A1B9A', 1, '["tickets.*","problemas.*","cambios.*","cmdb.*","redes.*"]'),
('Usuario_Final', 'Portal autoservicio: crear y ver sus tickets',      '#4CAF50', 1, '["portal.tickets","portal.kb"]'),
('Solo_Lectura',  'Acceso de consulta sin modificaciones',             '#94A3B8', 1, '["*.ver"]');

-- ──────────────────────────────────────
-- GRUPOS DE SOPORTE
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_grupos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    descripcion     VARCHAR(300),
    email_grupo     VARCHAR(200),
    responsable_id  INT,
    activo          TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO adm_grupos (nombre, descripcion, email_grupo) VALUES
('Mesa de Ayuda N1',     'Primer nivel de soporte',                'n1@aleatica.pe'),
('Infraestructura',      'Red, servidores y equipos criticos',     'infra@aleatica.pe'),
('Sistemas',             'Aplicaciones y software',                'sistemas@aleatica.pe'),
('ITS / Campo',          'Soporte postes SOS-CAM y equipos viales','its@aleatica.pe'),
('Telecomunicaciones',   'Telefonia y enlaces ISP',                'telecom@aleatica.pe');

-- ──────────────────────────────────────
-- USUARIOS
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(200) NOT NULL,
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    rol_id          INT NOT NULL,
    grupo_id        INT,
    sede_id         INT COMMENT 'FK cmdb_ubicaciones (se define en 002)',
    tipo            ENUM('Agente','Usuario_Final','Admin') DEFAULT 'Usuario_Final',
    activo          TINYINT(1) DEFAULT 1,
    avatar          VARCHAR(255),
    telefono        VARCHAR(30),
    cargo           VARCHAR(200),
    ultimo_login    DATETIME,
    token_reset     VARCHAR(64),
    token_expira    DATETIME,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id)    REFERENCES adm_roles(id),
    FOREIGN KEY (grupo_id)  REFERENCES adm_grupos(id) ON DELETE SET NULL,
    INDEX idx_email  (email),
    INDEX idx_activo (activo)
);

-- Usuario admin por defecto
-- IMPORTANTE: el hash se genera en setup.php al instalar
-- Contraseña temporal: Admin1234! (cambiar después del primer login)
INSERT INTO adm_usuarios (nombre, email, password_hash, rol_id, tipo) VALUES
('Administrador TI', 'admin@aleatica.pe', 'PENDIENTE_SETUP', 1, 'Admin');

-- ──────────────────────────────────────
-- SESIONES
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_sesiones (
    id          VARCHAR(64) PRIMARY KEY,
    usuario_id  INT NOT NULL,
    ip          VARCHAR(45),
    user_agent  TEXT,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_en   DATETIME NOT NULL,
    activa      TINYINT(1) DEFAULT 1,
    FOREIGN KEY (usuario_id) REFERENCES adm_usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_expira  (expira_en)
);

-- ──────────────────────────────────────
-- AUDITORÍA
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_auditoria (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    tabla           VARCHAR(100) NOT NULL,
    registro_id     INT,
    accion          ENUM('CREAR','EDITAR','ELIMINAR','LOGIN','LOGOUT','EXPORTAR','VER') NOT NULL,
    datos_anteriores JSON,
    datos_nuevos     JSON,
    usuario_id      INT,
    ip              VARCHAR(45),
    user_agent      VARCHAR(500),
    descripcion     VARCHAR(500),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabla     (tabla, registro_id),
    INDEX idx_usuario   (usuario_id),
    INDEX idx_fecha     (created_at)
);

-- ──────────────────────────────────────
-- CONFIGURACIÓN DEL SISTEMA
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_configuracion (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    clave       VARCHAR(100) NOT NULL UNIQUE,
    valor       TEXT,
    descripcion VARCHAR(300),
    tipo        ENUM('texto','numero','boolean','json','color') DEFAULT 'texto',
    modulo      VARCHAR(50),
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO adm_configuracion (clave, valor, descripcion, tipo, modulo) VALUES
('app_nombre',           'Aleatica ITSM',                      'Nombre del sistema',                    'texto',   'general'),
('app_empresa',          'Autopista del Norte S.A.C.',          'Razon social',                          'texto',   'general'),
('app_version',          '2.0.0',                               'Version del sistema',                   'texto',   'general'),
('app_color_primario',   '#1A3A5C',                             'Color primario UI',                     'color',   'general'),
('app_color_secundario', '#4CAF50',                             'Color secundario UI',                   'color',   'general'),
('app_color_acento',     '#FF6B00',                             'Color acento UI',                       'color',   'general'),
('ticket_prefijo',       'TKT',                                 'Prefijo numeracion tickets',            'texto',   'itsm'),
('ticket_siguiente_num', '1',                                   'Siguiente numero de ticket',            'numero',  'itsm'),
('sla_zona_horaria',     'America/Lima',                        'Zona horaria para calculo SLA',         'texto',   'itsm'),
('sla_horario_inicio',   '08:00',                               'Inicio horario laboral SLA',            'texto',   'itsm'),
('sla_horario_fin',      '18:00',                               'Fin horario laboral SLA',               'texto',   'itsm'),
('moneda_principal',     'PEN',                                 'Moneda por defecto',                    'texto',   'finanzas'),
('tc_usd',               '3.75',                               'Tipo de cambio USD referencial',        'numero',  'finanzas'),
('tc_mxn',               '0.19',                               'Tipo de cambio MXN referencial',        'numero',  'finanzas'),
('monitor_host',         '',                                    'Host BD sistema de monitoreo',          'texto',   'monitor'),
('monitor_db',           '',                                    'Nombre BD sistema de monitoreo',        'texto',   'monitor'),
('monitor_user',         '',                                    'Usuario BD monitoreo (solo lectura)',    'texto',   'monitor'),
('monitor_pass',         '',                                    'Password BD monitoreo',                 'texto',   'monitor'),
('monitor_activo',       '0',                                   'Integracion monitoreo activa (0/1)',    'boolean', 'monitor');

-- ──────────────────────────────────────
-- NOTIFICACIONES
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS adm_notificaciones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NOT NULL,
    tipo            VARCHAR(50) COMMENT 'ticket_asignado, sla_riesgo, garantia_vence, etc.',
    titulo          VARCHAR(200) NOT NULL,
    mensaje         TEXT,
    icono           VARCHAR(50) DEFAULT 'fa-bell',
    color           VARCHAR(7) DEFAULT '#4A90C4',
    url_destino     VARCHAR(300),
    leida           TINYINT(1) DEFAULT 0,
    entidad_tipo    VARCHAR(50) COMMENT 'ticket, ci, contrato, etc.',
    entidad_id      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES adm_usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida (usuario_id, leida),
    INDEX idx_created (created_at)
);
