<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$pageTitle  = 'Mesa de Ayuda — Tickets';
$pageModule = 'tickets';

// ── Filtros ───────────────────────────────────────────────
$buscar    = clean($_GET['q']         ?? '');
$filEstado = clean($_GET['estado']    ?? '');
$filTipo   = clean($_GET['tipo']      ?? '');
$filPrior  = clean($_GET['prioridad'] ?? '');
$filAgente = (int)($_GET['agente']    ?? 0);
$filCat    = (int)($_GET['cat']       ?? 0);
$filMio    = isset($_GET['mio']);
$page      = max(1, (int)($_GET['page'] ?? 1));

// ── Query base ────────────────────────────────────────────
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

// ── KPIs ─────────────────────────────────────────────────
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

$qpFiltros = http_build_query(array_filter(array_diff_key($_GET, ['page' => null])));
$urlPag    = 'tickets.php' . ($qpFiltros ? '?' . $qpFiltros : '');

ob_start();
?>

<!-- ── KPIs ──────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns: repeat(6, 1fr);">
<?php
$cards = [
    ['label'=>'Nuevos',      'val'=>$kpis['nuevos'],       'icon'=>'fa-circle-plus',          'color'=>'#1565C0','bg'=>'#E8EAF6'],
    ['label'=>'Asignados',   'val'=>$kpis['asignados'],    'icon'=>'fa-user-check',            'color'=>'#0277BD','bg'=>'#E1F5FE'],
    ['label'=>'En proceso',  'val'=>$kpis['en_proceso'],   'icon'=>'fa-spinner',               'color'=>'#00695C','bg'=>'#E0F2F1'],
    ['label'=>'En espera',   'val'=>$kpis['en_espera'],    'icon'=>'fa-pause-circle',          'color'=>'#F9A825','bg'=>'#FFFDE7'],
    ['label'=>'Resueltos',   'val'=>$kpis['cerrados'],     'icon'=>'fa-circle-check',          'color'=>'#2E7D32','bg'=>'#E8F5E9'],
    ['label'=>'SLA vencido', 'val'=>$kpis['sla_vencidos'], 'icon'=>'fa-triangle-exclamation',  'color'=>'#EF5350','bg'=>'#FFEBEE'],
];
foreach ($cards as $c): ?>
<div class="kpi-card" style="border-top: 3px solid <?= $c['color'] ?>;">
    <div class="kpi-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
        <i class="fa-solid <?= $c['icon'] ?>"></i>
    </div>
    <div class="kpi-value" style="color:<?= $c['color'] ?>"><?= (int)$c['val'] ?></div>
    <div class="kpi-label"><?= $c['label'] ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Barra de filtros ───────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body" style="padding:14px 20px;">
        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <!-- Búsqueda -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;"></i>
                <input type="text" name="q" placeholder="Buscar numero, titulo, solicitante..."
                       value="<?= h($buscar) ?>"
                       style="width:100%;height:36px;border:1.5px solid #E2E8F0;border-radius:8px;padding:0 12px 0 32px;font-size:13px;outline:none;">
            </div>
            <!-- Estado -->
            <select name="estado" class="form-control" style="width:160px;height:36px;">
                <option value="">Todos los estados</option>
                <?php foreach(['Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado'] as $e): ?>
                <option value="<?= $e ?>" <?= $filEstado===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Tipo -->
            <select name="tipo" class="form-control" style="width:150px;height:36px;">
                <option value="">Todos los tipos</option>
                <?php foreach(['Solicitud','Incidente','Evento','Alerta'] as $t): ?>
                <option value="<?= $t ?>" <?= $filTipo===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Prioridad -->
            <select name="prioridad" class="form-control" style="width:160px;height:36px;">
                <option value="">Todas las prioridades</option>
                <?php foreach(['Critica','Alta','Media','Baja'] as $p): ?>
                <option value="<?= $p ?>" <?= $filPrior===$p?'selected':'' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Mis tickets -->
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#475569;cursor:pointer;white-space:nowrap;">
                <input type="checkbox" name="mio" <?= $filMio?'checked':'' ?> onchange="this.form.submit()" style="width:16px;height:16px;">
                Mis tickets
            </label>
            <!-- Botones -->
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
            <a href="tickets.php" class="btn btn-ghost btn-sm btn-icon" title="Limpiar">
                <i class="fa-solid fa-xmark"></i>
            </a>
        </form>
    </div>
</div>

<!-- ── Tabla de tickets ──────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fa-solid fa-ticket me-2" style="color:#1565C0;margin-right:8px;"></i>
            Tickets
            <span class="badge ms-1" style="background:#E8EAF6;color:#1565C0;border:1px solid #c5cae9;margin-left:6px;"><?= $resultado['total'] ?></span>
        </span>
        <a href="ticket_form.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Nuevo Ticket
        </a>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-ticket opacity-25"></i>
        <p>No se encontraron tickets con los filtros aplicados.</p>
        <a href="tickets.php" class="btn btn-ghost btn-sm" style="margin-top:12px;">Ver todos</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:130px">Número</th>
                    <th>Título</th>
                    <th style="width:100px">Tipo</th>
                    <th style="width:90px">Prioridad</th>
                    <th style="width:110px">Estado</th>
                    <th style="width:150px">Solicitante</th>
                    <th style="width:150px">Agente</th>
                    <th style="width:120px">SLA Resolución</th>
                    <th style="width:80px">Apertura</th>
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $tk):
                $priorColor = ITIL::colorPrioridad($tk['prioridad']);
                $sla = ITIL::estadoSLA(
                    $tk['sla_resolucion_limite'],
                    $tk['sla_resolucion_cumplido'] !== null ? (int)$tk['sla_resolucion_cumplido'] : null
                );
                $cerrado = in_array($tk['estado'], ['Resuelto','Cerrado','Cancelado']);
                $estadoColors = [
                    'Nuevo'      => ['#EDE7F6','#6A1B9A'],
                    'Asignado'   => ['#E3F2FD','#1565C0'],
                    'En_proceso' => ['#E8F4FD','#0277BD'],
                    'En_espera'  => ['#FFF3E0','#E65100'],
                    'Resuelto'   => ['#E8F5E9','#2E7D32'],
                    'Cerrado'    => ['#F5F5F5','#616161'],
                    'Cancelado'  => ['#FFEBEE','#C62828'],
                ];
                [$eBg, $eColor] = $estadoColors[$tk['estado']] ?? ['#F5F5F5','#475569'];
            ?>
            <tr style="<?= $cerrado ? 'opacity:.6' : '' ?>">
                <td>
                    <a href="ticket_detalle.php?id=<?= $tk['id'] ?>" class="mono"
                       style="color:#1565C0;font-weight:600;text-decoration:none;font-size:12px;">
                        <?= h($tk['numero']) ?>
                    </a>
                </td>
                <td>
                    <a href="ticket_detalle.php?id=<?= $tk['id'] ?>"
                       style="color:#1E293B;text-decoration:none;">
                        <?= h(truncate($tk['titulo'], 65)) ?>
                    </a>
                    <?php if ($tk['categoria_nombre']): ?>
                    <span class="badge" style="background:<?= $tk['categoria_color'] ?>22;color:<?= $tk['categoria_color'] ?>;border:1px solid <?= $tk['categoria_color'] ?>44;font-size:10px;margin-left:4px;">
                        <?= h($tk['categoria_nombre']) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge" style="background:#F1F5F9;color:#475569;border:1px solid #E2E8F0;">
                        <?= $tk['tipo'] ?>
                    </span>
                </td>
                <td>
                    <span class="badge" style="background:<?= $priorColor ?>22;color:<?= $priorColor ?>;border:1px solid <?= $priorColor ?>44;">
                        <?= $tk['prioridad'] ?>
                    </span>
                </td>
                <td>
                    <span class="badge" style="background:<?= $eBg ?>;color:<?= $eColor ?>;border:1px solid <?= $eColor ?>33;">
                        <?= str_replace('_',' ',$tk['estado']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($tk['solicitante_nombre']): ?>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="width:24px;height:24px;border-radius:50%;background:#4A90C4;color:white;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <?= strtoupper(substr($tk['solicitante_nombre'],0,2)) ?>
                        </span>
                        <span style="font-size:12px;"><?= h(truncate($tk['solicitante_nombre'],18)) ?></span>
                    </div>
                    <?php else: ?><span style="color:#94A3B8">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($tk['agente_nombre']): ?>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="width:24px;height:24px;border-radius:50%;background:#00695C;color:white;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <?= strtoupper(substr($tk['agente_nombre'],0,2)) ?>
                        </span>
                        <span style="font-size:12px;"><?= h(truncate($tk['agente_nombre'],18)) ?></span>
                    </div>
                    <?php else: ?><span style="color:#94A3B8;font-style:italic;font-size:12px;">Sin asignar</span><?php endif; ?>
                </td>
                <td>
                    <?php if (!$cerrado): ?>
                    <span class="badge" style="background:<?= $sla['color'] ?>22;color:<?= $sla['color'] ?>;border:1px solid <?= $sla['color'] ?>44;">
                        <i class="fa-solid <?= $sla['icon'] ?>" style="margin-right:4px;"></i><?= $sla['label'] ?>
                    </span>
                    <?php if ($tk['sla_resolucion_limite']): ?>
                    <div style="font-size:10px;color:#94A3B8;margin-top:2px;">
                        <?= date('d/m H:i', strtotime($tk['sla_resolucion_limite'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?><span style="color:#94A3B8;">—</span><?php endif; ?>
                </td>
                <td style="font-size:11px;color:#94A3B8;">
                    <?= date('d/m/y', strtotime($tk['fecha_apertura'])) ?><br>
                    <?= date('H:i', strtotime($tk['fecha_apertura'])) ?>
                </td>
                <td>
                    <div style="position:relative;" x-data="{ open: false }">
                        <button onclick="toggleMenu(this)" class="btn btn-ghost btn-sm btn-icon">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="tk-menu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:white;border:1px solid #E2E8F0;border-radius:10px;padding:6px;min-width:170px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:50;">
                            <a href="ticket_detalle.php?id=<?= $tk['id'] ?>" class="tk-menu-item">
                                <i class="fa-solid fa-eye" style="color:#1565C0;width:16px;"></i> Ver detalle
                            </a>
                            <a href="ticket_form.php?id=<?= $tk['id'] ?>" class="tk-menu-item">
                                <i class="fa-solid fa-pen" style="color:#F9A825;width:16px;"></i> Editar
                            </a>
                            <?php if (!$cerrado): ?>
                            <hr style="margin:4px 0;border-color:#F1F5F9;">
                            <form method="post" action="ticket_action.php"
                                  onsubmit="return confirm('¿Cerrar este ticket?')">
                                <?= Auth::csrfInput() ?>
                                <input type="hidden" name="action" value="cerrar">
                                <input type="hidden" name="id" value="<?= $tk['id'] ?>">
                                <button type="submit" class="tk-menu-item" style="width:100%;background:none;border:none;color:#EF5350;cursor:pointer;text-align:left;">
                                    <i class="fa-solid fa-circle-xmark" style="width:16px;"></i> Cerrar ticket
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($resultado['total_pages'] > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid #F1F5F9;">
        <span style="font-size:12px;color:#94A3B8;">
            Mostrando <?= count($tickets) ?> de <?= $resultado['total'] ?> tickets
        </span>
        <?= paginator($resultado['total'], $resultado['per_page'], $page, $urlPag) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.tk-menu-item {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 10px; border-radius: 7px;
    font-size: 13px; color: #475569; text-decoration: none;
    transition: background .1s;
}
.tk-menu-item:hover { background: #F8FAFC; color: #1E293B; }
</style>
<script>
function toggleMenu(btn) {
    const menu = btn.nextElementSibling;
    const allMenus = document.querySelectorAll('.tk-menu');
    allMenus.forEach(m => { if (m !== menu) m.style.display = 'none'; });
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
    if (!e.target.closest('[onclick="toggleMenu(this)"]') && !e.target.closest('.tk-menu')) {
        document.querySelectorAll('.tk-menu').forEach(m => m.style.display = 'none');
    }
});
</script>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
