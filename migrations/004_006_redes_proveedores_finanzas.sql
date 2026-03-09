-- ============================================================
-- ITSM ALEATICA v2.0 — MIGRACIÓN 004: REDES Y TELECOM
-- ============================================================
USE itsm_aleatica;

CREATE TABLE IF NOT EXISTS net_vlans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    vlan_id         SMALLINT NOT NULL,
    nombre          VARCHAR(200) NOT NULL,
    descripcion     VARCHAR(300),
    segmento        VARCHAR(18) COMMENT 'Ej: 192.168.10.0',
    mascara         VARCHAR(18) COMMENT 'Ej: 255.255.255.0',
    cidr            TINYINT COMMENT 'Ej: 24',
    gateway         VARCHAR(45),
    cantidad_hosts  INT,
    proposito       VARCHAR(200),
    ubicacion_id    INT,
    activa          TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ubicacion_id) REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    UNIQUE KEY uk_vlan_sede (vlan_id, ubicacion_id)
);

CREATE TABLE IF NOT EXISTS net_anexos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero_anexo    VARCHAR(20) NOT NULL,
    numero_directo  VARCHAR(20),
    usuario_id      INT,
    ci_id           INT COMMENT 'FK cmdb_cis - el telefono IP',
    ubicacion_id    INT,
    ip_address      VARCHAR(45),
    mac_address     VARCHAR(17),
    estado          ENUM('Activo','Inactivo','En_reparacion') DEFAULT 'Activo',
    notas           VARCHAR(300),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)  REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (ci_id)       REFERENCES cmdb_cis(id) ON DELETE SET NULL,
    FOREIGN KEY (ubicacion_id) REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS net_enlaces_isp (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(200) NOT NULL,
    proveedor_id        INT,
    tipo                ENUM('Internet','MPLS','P2P','VPN','Fibra','Satelital') DEFAULT 'Internet',
    tecnologia          VARCHAR(100),
    ancho_banda_mbps    DECIMAL(10,2),
    ip_publica          VARCHAR(45),
    ip_gateway          VARCHAR(45),
    ubicacion_id        INT,
    contrato_id         INT,
    sla_uptime_pct      DECIMAL(5,2),
    estado              ENUM('Activo','Inactivo','Degradado') DEFAULT 'Activo',
    ci_id               INT COMMENT 'CI del router/modem del enlace',
    notas               TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ubicacion_id) REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (ci_id)        REFERENCES cmdb_cis(id) ON DELETE SET NULL
);

-- ============================================================
-- MIGRACIÓN 005: PROVEEDORES Y CONTRATOS
-- ============================================================

CREATE TABLE IF NOT EXISTS sup_proveedores (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(300) NOT NULL,
    nombre_corto    VARCHAR(100),
    ruc             VARCHAR(20) UNIQUE,
    tipo            ENUM('Fabricante','Distribuidor','ISP','Soporte','SaaS','Consultor','Otro') DEFAULT 'Otro',
    categoria       VARCHAR(100),
    pais            VARCHAR(100) DEFAULT 'Peru',
    direccion       VARCHAR(500),
    web             VARCHAR(300),
    estado          ENUM('Activo','Inactivo','Bloqueado') DEFAULT 'Activo',
    evaluacion      DECIMAL(3,1) COMMENT '1.0 - 5.0',
    activo          TINYINT(1) DEFAULT 1,
    notas           TEXT,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES adm_usuarios(id) ON DELETE SET NULL
);

INSERT INTO sup_proveedores (nombre, nombre_corto, ruc, tipo, pais) VALUES
('EFACT S.A.C.',           'EFACT',    '20557657237', 'SaaS',    'Peru'),
('Entel Peru S.A.',        'Entel',    '20214935986', 'ISP',     'Peru'),
('Cisco Systems Peru',     'Cisco',    '20601234567', 'Fabricante','Peru'),
('HP Inc. Peru',           'HP',       '20601234568', 'Fabricante','Peru'),
('Dell Technologies Peru', 'Dell',     '20601234569', 'Fabricante','Peru'),
('Huawei del Peru S.A.',   'Huawei',   '20601234570', 'Fabricante','Peru'),
('Dahua Technology Peru',  'Dahua',    '20601234571', 'Fabricante','Peru');

CREATE TABLE IF NOT EXISTS sup_contactos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id    INT NOT NULL,
    nombre          VARCHAR(200) NOT NULL,
    cargo           VARCHAR(200),
    email           VARCHAR(200),
    telefono        VARCHAR(50),
    telefono_2      VARCHAR(50),
    tipo            ENUM('Comercial','Tecnico','Emergencia','Facturacion','General') DEFAULT 'General',
    principal       TINYINT(1) DEFAULT 0,
    notas           VARCHAR(300),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES sup_proveedores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sup_contratos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    numero              VARCHAR(100) NOT NULL,
    proveedor_id        INT NOT NULL,
    tipo                ENUM('Soporte','Mantenimiento','Licencia','Servicio','SaaS','ISP','Otro') DEFAULT 'Servicio',
    nombre              VARCHAR(300) NOT NULL,
    descripcion         TEXT,
    estado              ENUM('Activo','Vencido','En_renovacion','Cancelado','Borrador') DEFAULT 'Activo',
    fecha_inicio        DATE,
    fecha_fin           DATE,
    renovacion_auto     TINYINT(1) DEFAULT 0,
    alerta_dias         INT DEFAULT 30 COMMENT 'Dias antes de vencer para alertar',
    -- Montos
    moneda              ENUM('PEN','USD','MXN') DEFAULT 'PEN',
    monto_total         DECIMAL(12,2),
    monto_mensual       DECIMAL(12,2),
    -- Archivos
    documento_pdf       VARCHAR(255),
    -- Relaciones
    ci_cubiertos        JSON COMMENT 'Array IDs de CIs cubiertos',
    servicio_id         INT,
    -- Alertas enviadas
    alerta_30_enviada   TINYINT(1) DEFAULT 0,
    alerta_7_enviada    TINYINT(1) DEFAULT 0,
    notas               TEXT,
    created_by          INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES sup_proveedores(id),
    FOREIGN KEY (servicio_id)  REFERENCES itsm_catalogo_servicios(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado    (estado),
    INDEX idx_fecha_fin (fecha_fin),
    INDEX idx_proveedor (proveedor_id)
);

CREATE TABLE IF NOT EXISTS sup_sla_proveedor (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id         INT NOT NULL,
    nombre_metrica      VARCHAR(200) NOT NULL,
    valor_comprometido  DECIMAL(10,2) NOT NULL,
    unidad              VARCHAR(50) COMMENT 'Ej: %, horas, minutos',
    periodo_medicion    ENUM('Diario','Semanal','Mensual','Trimestral') DEFAULT 'Mensual',
    penalidad           TEXT,
    forma_medicion      TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contrato_id) REFERENCES sup_contratos(id) ON DELETE CASCADE
);

-- ============================================================
-- MIGRACIÓN 006: FINANZAS TI
-- ============================================================

CREATE TABLE IF NOT EXISTS fin_facturas (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento          ENUM('Factura','Recibo','Boleta','Nota_Credito','Nota_Debito') DEFAULT 'Factura',
    numero_documento        VARCHAR(100) NOT NULL,
    proveedor_id            INT NOT NULL,
    contrato_id             INT,
    servicio_id             INT,
    ubicacion_id            INT,
    fecha_emision           DATE NOT NULL,
    fecha_vencimiento       DATE,
    fecha_pago              DATE,
    moneda                  ENUM('PEN','USD','MXN') DEFAULT 'PEN',
    monto_subtotal          DECIMAL(12,2) NOT NULL DEFAULT 0,
    monto_igv               DECIMAL(12,2) DEFAULT 0,
    monto_total             DECIMAL(12,2) NOT NULL,
    tc_usd                  DECIMAL(8,4),
    tc_mxn                  DECIMAL(8,4),
    monto_pen               DECIMAL(12,2) COMMENT 'Equivalente en soles',
    estado                  ENUM('Pendiente','Pagado','Vencido','Anulado') DEFAULT 'Pendiente',
    archivo_pdf             VARCHAR(255),
    nombre_archivo_original VARCHAR(255),
    periodo                 VARCHAR(7) COMMENT 'YYYY-MM',
    observaciones           TEXT,
    created_by              INT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES sup_proveedores(id),
    FOREIGN KEY (contrato_id)  REFERENCES sup_contratos(id) ON DELETE SET NULL,
    FOREIGN KEY (servicio_id)  REFERENCES itsm_catalogo_servicios(id) ON DELETE SET NULL,
    FOREIGN KEY (ubicacion_id) REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES adm_usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado    (estado),
    INDEX idx_proveedor (proveedor_id),
    INDEX idx_periodo   (periodo)
);

CREATE TABLE IF NOT EXISTS fin_consumos_mensuales (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id            INT NOT NULL,
    contrato_id             INT,
    ubicacion_id            INT,
    periodo                 VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    anio                    SMALLINT NOT NULL,
    mes                     TINYINT NOT NULL,
    descripcion_consumo     VARCHAR(255),
    unidad                  VARCHAR(50) DEFAULT 'unidades',
    cantidad_total          INT DEFAULT 0,
    limite_plan             INT,
    facturas_electronicas   INT DEFAULT 0,
    boletas_electronicas    INT DEFAULT 0,
    notas_credito           INT DEFAULT 0,
    notas_debito            INT DEFAULT 0,
    otros_comprobantes      INT DEFAULT 0,
    moneda                  ENUM('PEN','USD','MXN') DEFAULT 'PEN',
    costo_consumo           DECIMAL(12,2) DEFAULT 0,
    costo_pen               DECIMAL(12,2) DEFAULT 0,
    tc_usd                  DECIMAL(8,4),
    tc_mxn                  DECIMAL(8,4),
    archivo_reporte         VARCHAR(255),
    nombre_archivo_original VARCHAR(255),
    observaciones           TEXT,
    created_by              INT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_consumo (proveedor_id, periodo),
    FOREIGN KEY (proveedor_id) REFERENCES sup_proveedores(id),
    FOREIGN KEY (contrato_id)  REFERENCES sup_contratos(id) ON DELETE SET NULL,
    FOREIGN KEY (ubicacion_id) REFERENCES cmdb_ubicaciones(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES adm_usuarios(id) ON DELETE SET NULL
);

-- Historial EFACT (datos ya extraídos)
CREATE TABLE IF NOT EXISTS fin_consumos_efact (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    periodo                 VARCHAR(7) NOT NULL UNIQUE,
    anio                    SMALLINT NOT NULL,
    mes                     TINYINT NOT NULL,
    facturas_electronicas   INT DEFAULT 0,
    boletas_electronicas    INT DEFAULT 0,
    notas_credito           INT DEFAULT 0,
    notas_debito            INT DEFAULT 0,
    cantidad_total          INT DEFAULT 0,
    total_aceptados         INT DEFAULT 0,
    total_rechazados        INT DEFAULT 0,
    total_transacciones     INT DEFAULT 0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_anio   (anio),
    INDEX idx_periodo (periodo)
);
