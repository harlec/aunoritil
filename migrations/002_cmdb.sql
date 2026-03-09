-- ============================================================
-- ITSM ALEATICA v2.0 — MIGRACIÓN 002: CMDB
-- ============================================================

USE itsm_aleatica;

-- ──────────────────────────────────────
-- UBICACIONES (Sedes jerárquicas)
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_ubicaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(200) NOT NULL,
    tipo        ENUM('Region','Sede','Edificio','Piso','Sala','Rack','Campo') DEFAULT 'Sede',
    padre_id    INT COMMENT 'Para jerarquia: Region > Sede > Edificio > Piso > Sala',
    direccion   VARCHAR(500),
    latitud     DECIMAL(10,7),
    longitud    DECIMAL(10,7),
    codigo      VARCHAR(20) UNIQUE,
    activa      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (padre_id) REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    INDEX idx_padre (padre_id)
);

INSERT INTO cmdb_ubicaciones (nombre, tipo, codigo) VALUES
('Autopista del Norte',      'Region', 'ANP'),
('Lima - Sede Central',      'Sede',   'LIM'),
('Nvo. Chimbote',            'Sede',   'CHI'),
('UP. Fortaleza',            'Sede',   'FOR'),
('UP. Huarmey',              'Sede',   'HUA'),
('UP. 402',                  'Sede',   '402'),
('UP. Viru',                 'Sede',   'VIR'),
('UP. Santa',                'Sede',   'SAN');

-- Asignar padre_id a las sedes (padre = Region ANP id=1)
UPDATE cmdb_ubicaciones SET padre_id = 1 WHERE id > 1;

-- ──────────────────────────────────────
-- ELEMENTOS DE CONFIGURACIÓN (CIs)
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_cis (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    codigo_ci           VARCHAR(50) NOT NULL UNIQUE,
    nombre              VARCHAR(200) NOT NULL,
    tipo_ci             ENUM(
                            'Servidor','Laptop','Desktop','Tablet',
                            'Switch','Router','AP','Firewall','Modem',
                            'Poste_ITS','Camara_IP','Modulo_SOS',
                            'UPS','PDU',
                            'Telefono_IP','Central_Telefonica',
                            'Impresora','Escaner',
                            'Licencia_SW','Servicio_SW','Aplicacion',
                            'Enlace_ISP','Circuito',
                            'Otro'
                        ) NOT NULL,
    categoria           ENUM('Hardware','Software','Red','Telefonia','ITS','Servicio','Otro') NOT NULL,
    estado              ENUM('Activo','Inactivo','En_reparacion','En_almacen','Dado_de_baja','En_transito') DEFAULT 'Activo',
    etapa_ciclo_vida    ENUM('Planificado','En_desarrollo','Operativo','Obsoleto','Retirado') DEFAULT 'Operativo',
    criticidad          ENUM('Critico','Alto','Medio','Bajo') DEFAULT 'Medio',
    -- Identificación
    marca               VARCHAR(100),
    modelo              VARCHAR(200),
    numero_serie        VARCHAR(200),
    numero_parte        VARCHAR(100),
    numero_activo       VARCHAR(100) COMMENT 'Numero de activo fijo contable',
    -- Software
    version_firmware    VARCHAR(50),
    version_so          VARCHAR(100),
    -- Red
    ip_address          VARCHAR(45),
    mac_address         VARCHAR(17),
    mac_wifi            VARCHAR(17),
    hostname            VARCHAR(200),
    vlan_id             INT COMMENT 'FK net_vlans (se agrega en 004)',
    -- Ubicacion y asignacion
    ubicacion_id        INT,
    rack_posicion       VARCHAR(50),
    propietario_id      INT COMMENT 'FK adm_usuarios - usuario asignado',
    responsable_ti      INT COMMENT 'FK adm_usuarios - tecnico responsable',
    -- Proveedor y contrato
    proveedor_id        INT COMMENT 'FK sup_proveedores (se agrega en 005)',
    contrato_id         INT COMMENT 'FK sup_contratos (se agrega en 005)',
    -- Fechas y garantia
    fecha_compra        DATE,
    fecha_garantia_fin  DATE,
    -- Financiero
    costo_adquisicion   DECIMAL(12,2),
    moneda_adquisicion  ENUM('PEN','USD','MXN') DEFAULT 'PEN',
    tipo_adquisicion    ENUM('CAPEX','OPEX','Leasing','Donacion') DEFAULT 'CAPEX',
    -- Specs hardware (JSON flexible)
    procesador          VARCHAR(200),
    ram_gb              DECIMAL(6,1),
    disco_gb            DECIMAL(8,1),
    accesorios          JSON COMMENT '{"cargador":true,"mouse":true,"teclado":false,"docking":false}',
    especificaciones    JSON COMMENT 'Atributos adicionales libres por tipo de CI',
    -- Integracion monitoreo
    monitor_id          INT COMMENT 'ID del equipo en el sistema de monitoreo externo',
    monitor_ip          VARCHAR(45) COMMENT 'IP que usa el sistema de monitoreo',
    ultimo_estado_mon   ENUM('Up','Down','Degradado','Desconocido') DEFAULT 'Desconocido',
    ultimo_check_mon    DATETIME,
    -- Notas
    notas               TEXT,
    -- Auditoria
    created_by          INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ubicacion_id)   REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (propietario_id) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (responsable_ti) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)     REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_tipo      (tipo_ci),
    INDEX idx_estado    (estado),
    INDEX idx_ubicacion (ubicacion_id),
    INDEX idx_ip        (ip_address),
    INDEX idx_serie     (numero_serie),
    INDEX idx_monitor   (monitor_id),
    INDEX idx_garantia  (fecha_garantia_fin)
);

-- ──────────────────────────────────────
-- RELACIONES ENTRE CIs
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_relaciones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ci_origen_id    INT NOT NULL,
    ci_destino_id   INT NOT NULL,
    tipo_relacion   ENUM(
                        'Depende_de','Contiene','Conectado_a',
                        'Respaldado_por','Virtualizado_en',
                        'Licenciado_en','Monitorizado_por'
                    ) NOT NULL,
    descripcion     VARCHAR(300),
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ci_origen_id)  REFERENCES cmdb_cis(id) ON DELETE CASCADE,
    FOREIGN KEY (ci_destino_id) REFERENCES cmdb_cis(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)    REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_relacion (ci_origen_id, ci_destino_id, tipo_relacion),
    INDEX idx_origen  (ci_origen_id),
    INDEX idx_destino (ci_destino_id)
);

-- ──────────────────────────────────────
-- GARANTÍAS
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_garantias (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    ci_id               INT NOT NULL,
    tipo                ENUM('Fabricante','Extendida','Contrato_Mantenimiento','Soporte_SW') DEFAULT 'Fabricante',
    proveedor_texto     VARCHAR(200) COMMENT 'Nombre proveedor si no esta en BD',
    numero_caso         VARCHAR(100) COMMENT 'Numero de caso/ticket con proveedor',
    fecha_inicio        DATE,
    fecha_fin           DATE NOT NULL,
    cobertura           TEXT COMMENT 'Que cubre la garantia',
    sla_respuesta_hrs   INT COMMENT 'Horas de respuesta comprometidas',
    contacto_soporte    VARCHAR(200),
    telefono_soporte    VARCHAR(50),
    email_soporte       VARCHAR(200),
    url_portal          VARCHAR(300),
    archivo_doc         VARCHAR(255),
    alerta_90_enviada   TINYINT(1) DEFAULT 0,
    alerta_30_enviada   TINYINT(1) DEFAULT 0,
    notas               TEXT,
    created_by          INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ci_id)     REFERENCES cmdb_cis(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_ci        (ci_id),
    INDEX idx_fecha_fin (fecha_fin)
);

-- ──────────────────────────────────────
-- LICENCIAS DE SOFTWARE
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_licencias (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(200) NOT NULL,
    fabricante          VARCHAR(200),
    tipo                ENUM('Perpetua','Suscripcion','OEM','Volumen','Freeware','OpenSource') DEFAULT 'Perpetua',
    version             VARCHAR(50),
    numero_licencia     VARCHAR(300),
    cantidad_puestos    INT DEFAULT 1,
    puestos_usados      INT DEFAULT 0,
    fecha_adquisicion   DATE,
    fecha_vencimiento   DATE,
    costo               DECIMAL(12,2),
    moneda              ENUM('PEN','USD','MXN') DEFAULT 'USD',
    contrato_id         INT COMMENT 'FK sup_contratos',
    archivo_doc         VARCHAR(255),
    notas               TEXT,
    created_by          INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_vencimiento (fecha_vencimiento)
);

-- ──────────────────────────────────────
-- ASIGNACIÓN LICENCIAS → CIs
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_asignacion_licencias (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    licencia_id     INT NOT NULL,
    ci_id           INT NOT NULL,
    usuario_id      INT,
    fecha_asignacion DATE,
    notas           VARCHAR(300),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (licencia_id) REFERENCES cmdb_licencias(id) ON DELETE CASCADE,
    FOREIGN KEY (ci_id)       REFERENCES cmdb_cis(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)  REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_lic_ci (licencia_id, ci_id)
);

-- ──────────────────────────────────────
-- HISTORIAL DE CAMBIOS EN CIs
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS cmdb_historial_ci (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    ci_id           INT NOT NULL,
    tipo_cambio     ENUM('Estado','Asignacion','Ubicacion','Garantia','Especificacion','Otro') NOT NULL,
    valor_anterior  VARCHAR(500),
    valor_nuevo     VARCHAR(500),
    descripcion     VARCHAR(500),
    usuario_id      INT,
    ticket_id       INT COMMENT 'FK itsm_tickets - cambio originado por ticket',
    cambio_id       INT COMMENT 'FK itsm_cambios - RFC que origino el cambio',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ci_id)      REFERENCES cmdb_cis(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_ci    (ci_id),
    INDEX idx_fecha (created_at)
);
