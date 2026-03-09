<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$pageTitle  = 'Mesa de Ayuda — Tickets';
$pageModule = 'tickets';

// ── Filtros ───────────────────────────────────────────────────
$buscar    = clean($_GET['q']         ?? '');
$filEstado = clean($_GET['estado']    ?? '');
$filTipo   = clean($_GET['tipo']      ?? '');
$filPrior  = clean($_GET['prioridad'] ?? '');
$filAgente = (int)($_GET['agente']    ?? 0);
$filCat    = (int)($_GET['cat']       ?? 0);
$filMio    = isset($_GET['mio']);
$page      = max(1, (int)($_GET['page'] ?? 1));

// ── Query base ────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($buscar) {
    $where[]  = '(t.numero LIKE ? OR t.titulo LIKE ? OR sol.nombre LIKE ?)';
    $like     = "%{$buscar}%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($filEstado) { $where[] = 't.estado = ?';      $params[] = $filEstado; }
if ($filTipo)   { $where[] = 't.tipo = ?';         $params[] = $filTipo; }
if ($filPrior)  { $where[] = 't.prioridad = ?';    $params[] = $filPrior; }
if ($filAgente) { $where[] = 't.agente_id = ?';    $params[] = $filAgente; }
if ($filCat)    { $where[] = 't.categoria_id = ?'; $params[] = $filCat; }
if ($filMio)    { $where[] = 't.agente_id = ?';    $params[] = Auth::userId(); }

$whereStr = implode(' AND ', $where);

$sql = "SELECT t.*,
        sol.nombre  AS solicitante_nombre,
        ag.nombre   AS agente_nombre,
        cat.nombre  AS categoria_nombre,
        cat.color   AS categoria_color,
        cat.icono   AS categoria_icono,
        ub.nombre   AS sede_nombre
        FROM itsm_tickets t
        LEFT JOIN adm_usuarios sol    ON sol.id = t.solicitante_id
        LEFT JOIN adm_usuarios ag     ON ag.id  = t.agente_id
        LEFT JOIN itsm_categorias cat ON cat.id  = t.categoria_id
        LEFT JOIN cmdb_ubicaciones ub ON ub.id   = t.sede_id
        WHERE {$whereStr}
        ORDER BY
            FIELD(t.prioridad,'Critica','Alta','Media','Baja'),
            t.sla_resolucion_limite ASC,
            t.fecha_apertura DESC";

$resultado = DB::paginate($sql, $params, $page, PER_PAGE);
$tickets   = $resultado['data'];

// ── KPIs ─────────────────────────────────────────────────────
$kpis = DB::row(
    "SELECT
        COUNT(*) total,
        SUM(estado='Nuevo') nuevos,
        SUM(estado='Asignado') asignados,
        SUM(estado='En_proceso') en_proceso,
        SUM(estado='En_espera') en_espera,
        SUM(estado IN ('Resuelto','Cerrado')) cerrados,
        SUM(estado NOT IN ('Resuelto','Cerrado','Cancelado') AND sla_resolucion_limite < NOW()) sla_vencidos
     FROM itsm_tickets"
);

// URL base para paginacion (sin parametro page)
$qpFiltros = http_build_query(array_filter(array_diff_key($_GET, ['page' => null])));
$urlPag    = 'tickets.php' . ($qpFiltros ? '?' . $qpFiltros : '');

ob_start();
?>
<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
<?php
$cards = [
    ['label'=>'Nuevos',      'val'=>$kpis['nuevos'],      'icon'=>'fa-circle-plus',          'color'=>'#1565C0','bg'=>'#E8EAF6'],
    ['label'=>'Asignados',   'val'=>$kpis['asignados'],   'icon'=>'fa-user-check',            'color'=>'#0277BD','bg'=>'#E1F5FE'],
    ['label'=>'En proceso',  'val'=>$kpis['en_proceso'],  'icon'=>'fa-spinner',               'color'=>'#00695C','bg'=>'#E0F2F1'],
    ['label'=>'En espera',   'val'=>$kpis['en_espera'],   'icon'=>'fa-pause-circle',          'color'=>'#F9A825','bg'=>'#FFFDE7'],
    ['label'=>'Resueltos',   'val'=>$kpis['cerrados'],    'icon'=>'fa-circle-check',          'color'=>'#2E7D32','bg'=>'#E8F5E9'],
    ['label'=>'SLA vencido', 'val'=>$kpis['sla_vencidos'],'icon'=>'fa-triangle-exclamation', 'color'=>'#EF5350','bg'=>'#FFEBEE'],
];
foreach ($cards as $c): ?>
<div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid <?= $c['color'] ?> !important;">
        <div class="card-body py-3 px-3 d-flex align-items-center gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:<?= $c['bg'] ?>">
                <i class="fa-solid <?= $c['icon'] ?> fa-lg" style="color:<?= $c['color'] ?>"></i>
            </div>
            <div>
                <div class="fw-bold fs-4 lh-1" style="color:<?= $c['color'] ?>"><?= (int)$c['val'] ?></div>
                <div class="text-muted small"><?= $c['label'] ?></div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Barra de acciones ─────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar numero, titulo, solicitante..." value="<?= h($buscar) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <?php foreach(['Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado'] as $e): ?>
                    <option value="<?= $e ?>" <?= $filEstado===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos los tipos</option>
                    <?php foreach(['Solicitud','Incidente','Evento','Alerta'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filTipo===$t?'selected':'' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="prioridad" class="form-select form-select-sm">
                    <option value="">Todas las prioridades</option>
                    <?php foreach(['Critica','Alta','Media','Baja'] as $p): ?>
                    <option value="<?= $p ?>" <?= $filPrior===$p?'selected':'' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" name="mio" id="chkMio" <?= $filMio?'checked':'' ?> onchange="this.form.submit()">
                    <label class="form-check-label small" for="chkMio">Mis tickets</label>
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <a href="tickets.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- ── Tabla de tickets ──────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2 px-3">
        <span class="fw-semibold" style="color:#1A3A5C">
            <i class="fa-solid fa-ticket me-2" style="color:#1565C0"></i>
            Tickets
            <span class="badge bg-secondary ms-1"><?= $resultado['total'] ?></span>
        </span>
        <a href="ticket_form.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Nuevo Ticket
        </a>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="fa-solid fa-ticket fa-2x mb-3 d-block opacity-25"></i>
        No se encontraron tickets con los filtros aplicados.
        <div class="mt-2"><a href="tickets.php" class="btn btn-outline-primary btn-sm">Ver todos</a></div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:120px">Numero</th>
                    <th>Titulo</th>
                    <th style="width:100px">Tipo</th>
                    <th style="width:90px">Prioridad</th>
                    <th style="width:110px">Estado</th>
                    <th style="width:140px">Solicitante</th>
                    <th style="width:140px">Agente</th>
                    <th style="width:110px">SLA Resolucion</th>
                    <th style="width:80px">Apertura</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $tk): ?>
            <?php
                $priorColor = ITIL::colorPrioridad($tk['prioridad']);
                $sla = ITIL::estadoSLA(
                    $tk['sla_resolucion_limite'],
                    $tk['sla_resolucion_cumplido'] !== null ? (int)$tk['sla_resolucion_cumplido'] : null
                );
                $estadoClasses = [
                    'Nuevo'      => 'bg-primary',
                    'Asignado'   => 'bg-info text-dark',
                    'En_proceso' => 'bg-warning text-dark',
                    'En_espera'  => 'bg-secondary',
                    'Resuelto'   => 'bg-success',
                    'Cerrado'    => 'bg-dark',
                    'Cancelado'  => 'bg-danger',
                ];
                $estadoBadge = $estadoClasses[$tk['estado']] ?? 'bg-secondary';
            ?>
            <tr class="<?= in_array($tk['estado'],['Resuelto','Cerrado','Cancelado']) ? 'text-muted' : '' ?>">
                <td class="ps-3">
                    <a href="ticket_detalle.php?id=<?= $tk['id'] ?>" class="text-decoration-none fw-semibold" style="color:#1565C0">
                        <?= h($tk['numero']) ?>
                    </a>
                </td>
                <td>
                    <a href="ticket_detalle.php?id=<?= $tk['id'] ?>" class="text-decoration-none text-dark">
                        <?= h(truncate($tk['titulo'], 65)) ?>
                    </a>
                    <?php if ($tk['categoria_nombre']): ?>
                    <span class="badge ms-1" style="background:<?= $tk['categoria_color'] ?>22;color:<?= $tk['categoria_color'] ?>;font-size:0.7em">
                        <?= h($tk['categoria_nombre']) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-light text-dark border"><?= $tk['tipo'] ?></span></td>
                <td>
                    <span class="badge" style="background:<?= $priorColor ?>22;color:<?= $priorColor ?>;border:1px solid <?= $priorColor ?>">
                        <?= $tk['prioridad'] ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $estadoBadge ?>" style="font-size:0.75em">
                        <?= str_replace('_',' ',$tk['estado']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($tk['solicitante_nombre']): ?>
                    <span class="d-flex align-items-center gap-1">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                              style="width:22px;height:22px;font-size:0.6em;background:#4A90C4;flex-shrink:0">
                            <?= strtoupper(substr($tk['solicitante_nombre'],0,2)) ?>
                        </span>
                        <?= h(truncate($tk['solicitante_nombre'],18)) ?>
                    </span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($tk['agente_nombre']): ?>
                    <span class="d-flex align-items-center gap-1">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                              style="width:22px;height:22px;font-size:0.6em;background:#00695C;flex-shrink:0">
                            <?= strtoupper(substr($tk['agente_nombre'],0,2)) ?>
                        </span>
                        <?= h(truncate($tk['agente_nombre'],18)) ?>
                    </span>
                    <?php else: ?><span class="text-muted fst-italic small">Sin asignar</span><?php endif; ?>
                </td>
                <td>
                    <?php if (!in_array($tk['estado'],['Resuelto','Cerrado','Cancelado'])): ?>
                    <span class="badge" style="background:<?= $sla['color'] ?>22;color:<?= $sla['color'] ?>;border:1px solid <?= $sla['color'] ?>">
                        <i class="fa-solid <?= $sla['icon'] ?> me-1"></i><?= $sla['label'] ?>
                    </span>
                    <?php if ($tk['sla_resolucion_limite']): ?>
                    <div class="text-muted" style="font-size:0.65em"><?= date('d/m H:i', strtotime($tk['sla_resolucion_limite'])) ?></div>
                    <?php endif; ?>
                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:0.75em">
                    <?= date('d/m/y', strtotime($tk['fecha_apertura'])) ?><br>
                    <?= date('H:i', strtotime($tk['fecha_apertura'])) ?>
                </td>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="ticket_detalle.php?id=<?= $tk['id'] ?>">
                                <i class="fa-solid fa-eye me-2 text-primary"></i>Ver detalle</a></li>
                            <li><a class="dropdown-item" href="ticket_form.php?id=<?= $tk['id'] ?>">
                                <i class="fa-solid fa-pen me-2 text-warning"></i>Editar</a></li>
                            <?php if (!in_array($tk['estado'],['Cerrado','Cancelado'])): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="ticket_action.php" class="d-inline"
                                      onsubmit="return confirm('Cerrar este ticket?')">
                                    <?= Auth::csrfInput() ?>
                                    <input type="hidden" name="action" value="cerrar">
                                    <input type="hidden" name="id" value="<?= $tk['id'] ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fa-solid fa-circle-xmark me-2"></i>Cerrar ticket</button>
                                </form>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginacion -->
    <?php if ($resultado['total_pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
        <span class="text-muted small">
            Mostrando <?= count($tickets) ?> de <?= $resultado['total'] ?> tickets
        </span>
        <?= paginator($resultado['total'], $resultado['per_page'], $page, $urlPag) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
