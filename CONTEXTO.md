# Contexto del Proyecto — Aleatica ITSM
**Última actualización:** 2026-03-09
**Versión del sistema:** 2.0.0

---

## Descripción general
Sistema web PHP para **gestión del área de TI** en una concesionaria de peajes (**Autopista del Norte S.A.C.**), desarrollado bajo el enfoque **ITIL 4**.
URL base: `https://aunoritil.harlec.com.pe`
Base de datos: `itsm_aleatica` (MySQL/MariaDB)

---

## Stack tecnológico
- **Backend:** PHP 8+ (sin framework, MVC artesanal)
- **BD:** MySQL con PDO, clase `DB` en `core/DB.php`
- **Frontend:** Bootstrap 5 + Font Awesome 6 (via CDN)
- **Zona horaria:** `America/Lima`
- **Sesión:** 8 horas, cookie `itsm_session`

---

## Estructura de archivos

```
/
├── config.php              ← Configuración central (BD, rutas, constantes)
├── index.php               ← Entrada (redirige al dashboard)
├── login.php / logout.php
├── dashboard.php           ← Dashboard principal
├── core/
│   ├── DB.php              ← PDO wrapper (query, row, value, paginate, insertRow, update)
│   ├── Auth.php            ← Autenticación, roles, CSRF
│   ├── ITIL.php            ← Lógica ITIL (prioridad, SLA, numeración, notificaciones)
│   ├── Audit.php           ← Auditoría de acciones
│   └── Helpers.php         ← Helpers (clean, postClean, flash, redirect, paginacion, etc.)
├── includes/
│   └── layout.php          ← Layout principal con menú lateral (estilo InvGate)
├── migrations/
│   ├── 001_base.sql        ← Usuarios, grupos, notificaciones, auditoría
│   ├── 002_cmdb.sql        ← CMDB: CIs, ubicaciones, garantías, VLANs
│   ├── 003_itsm.sql        ← ITSM: tickets, comentarios, adjuntos, problemas, cambios, KB
│   └── 004_006_redes_proveedores_finanzas.sql ← Redes, proveedores, contratos
├── modules/
│   ├── admin/              ← Gestión de usuarios (usuarios.php, usuario_form.php, usuario_detalle.php, usuario_action.php)
│   ├── cmdb/               ← CMDB (cis.php, ci_form.php, ci_detalle.php, ci_action.php, garantias.php)
│   ├── proveedores/        ← Proveedores y contratos
│   └── itsm/               ← ✅ NUEVO — Mesa de Ayuda / Tickets
│       ├── index.php
│       ├── tickets.php         ← Listado con KPIs y filtros
│       ├── ticket_form.php     ← Nuevo ticket / editar
│       ├── ticket_detalle.php  ← Detalle con timeline y SLA
│       └── ticket_action.php   ← Manejador POST (cerrar/cancelar/reabrir/asignar/resolver/calificar/bulk)
└── uploads/
```

---

## Módulos implementados

| Módulo | Estado | Notas |
|---|---|---|
| Login / Auth | ✅ Completo | |
| Dashboard | ✅ Completo | |
| Admin - Usuarios | ✅ Completo | |
| CMDB | ✅ Completo | CIs, garantías |
| Proveedores / Contratos | ✅ Completo | |
| **Mesa de Ayuda (Tickets)** | ✅ Completo | Sesión 2026-03-09 |
| Problemas (PRB) | 🔲 Pendiente | tablas ya en BD (003_itsm.sql) |
| Cambios - RFC | 🔲 Pendiente | tablas ya en BD |
| Catálogo de Servicios | 🔲 Pendiente | tablas ya en BD |
| Base de Conocimiento (KB) | 🔲 Pendiente | tablas ya en BD |
| Redes / Telecom | 🔲 Pendiente | tablas en migración 004 |

---

## Tablas principales (BD)

### Módulo ITSM (003_itsm.sql)
- `itsm_categorias` — Árbol 3 niveles (Hardware, Software, Red, ITS/Campo, etc.)
- `itsm_sla_politicas` — 4 políticas: Crítico (15min/4h), Alto (1h/8h), Medio (4h/1d), Bajo (8h/3d)
- `itsm_catalogo_servicios` — Catálogo con código, propietario, SLA vinculado
- `itsm_tickets` — Tickets con numeración `TKT-YYYY-NNNNN`, estados, prioridad, SLA, origen
- `itsm_comentarios` — Públicos / Internos / Sistema
- `itsm_adjuntos` — Para tickets, problemas, cambios y KB
- `itsm_problemas` — Gestión de problemas con RCA (`PRB-YYYY-NNNNN`)
- `itsm_cambios` — RFC con flujo de aprobación (`RFC-YYYY-NNNNN`)
- `itsm_kb_articulos` — Base de conocimiento con FULLTEXT search

### CMDB (002_cmdb.sql)
- `cmdb_cis`, `cmdb_ubicaciones`, `cmdb_garantias`, `net_vlans`

### Administración (001_base.sql)
- `adm_usuarios`, `adm_grupos`, `adm_notificaciones`, `adm_auditoria`

### Proveedores (004_...)
- `sup_proveedores`, `sup_contratos`

---

## Clase ITIL.php — métodos disponibles
```php
ITIL::calcularPrioridad($urgencia, $impacto)   // Matriz Urgencia x Impacto → prioridad
ITIL::colorPrioridad($prioridad)               // Color hex por prioridad
ITIL::calcularSLA($prioridad, $categoriaId)    // Límites respuesta + resolución
ITIL::estadoSLA($limite, $cumplido)            // Estado semáforo del SLA
ITIL::siguiente($prefijo, $tabla)              // Siguiente número correlativo (TKT/PRB/RFC)
ITIL::notificar($usuarioId, $tipo, ...)        // Insertar notificación en adm_notificaciones
ITIL::slaGlobal($periodo)                      // % cumplimiento SLA del período
```

---

## Lógica del módulo de Tickets (implementado hoy)

### Flujo de estados
```
Nuevo → Asignado → En_proceso → En_espera → Resuelto → Cerrado
                                           ↗
                              (también directo)
Cualquier estado → Cancelado
Cerrado/Resuelto → Nuevo (reabrir)
```

### Prioridad automática (Urgencia x Impacto)
|          | Alto    | Medio  | Bajo  |
|----------|---------|--------|-------|
| **Alta** | Crítica | Alta   | Media |
| **Media**| Alta    | Media  | Media |
| **Baja** | Media   | Baja   | Baja  |

### Acciones en ticket_action.php
- `cerrar` — cierra y marca SLA
- `cancelar` — requiere motivo, agrega comentario de sistema
- `reabrir` — resetea fechas de resolución/cierre
- `asignar` — asigna agente, notifica via ITIL::notificar()
- `resolver` — marca resuelto con solución y fecha
- `calificar` — feedback 1-5 estrellas + cierre definitivo
- `bulk_accion` — acciones masivas (cerrar N tickets / asignar_me)

---

## Convenciones de código
```php
// Helpers disponibles:
clean($str)           // sanitiza GET
postClean($key)       // sanitiza POST
postInt($key)         // int desde POST
flash('success|error|warning', $msg)  // mensaje de sesión
redirect($url)        // redirect + exit
csrfField()           // <input hidden> con token CSRF
paginacion($resultado, $params)  // HTML de paginación Bootstrap
Helpers::truncate($str, $len)    // truncar texto
Helpers::formatBytes($bytes)     // formato legible de tamaño

// Patrón de página:
ob_start();
// ... HTML ...
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
```

---

## Pendientes / Próximos pasos sugeridos
1. **Módulo Problemas (PRB)** — CRUD + vinculación con tickets + RCA
2. **Módulo Cambios RFC** — flujo de aprobación (Borrador → Revisión → Aprobado → Implementación)
3. **Catálogo de Servicios** — CRUD del catálogo con SLA vinculado
4. **Base de Conocimiento** — artículos con búsqueda FULLTEXT, vinculación a tickets
5. **Dashboard mejorado** — métricas SLA, gráficas por período, heat map de incidentes por sede
6. **Portal de autoservicio** — vista simplificada para usuarios no-TI (crear y seguir sus tickets)
7. **Integración monitoreo** — MONITOR_ACTIVO en config.php, tickets automáticos desde alertas
