<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error','ID invalido'); redirect(BASE_URL.'/modules/itsm/tickets.php'); }

$tk = DB::row(
    "SELECT t.*,
        sol.nombre  AS solicitante_nombre,
        sol.email   AS solicitante_email,
        ag.nombre   AS agente_nombre,
        ag.email    AS agente_email,
        cat.nombre  AS categoria_nombre,
        cat.color   AS categoria_color,
        cat.icono   AS categoria_icono,
        srv.nombre  AS servicio_nombre,
        ci.nombre   AS ci_nombre,
        ci.codigo_ci,
        ub.nombre   AS sede_nombre,
        gr.nombre   AS grupo_nombre,
        cr.nombre   AS creado_por_nombre
     FROM itsm_tickets t
     LEFT JOIN adm_usuarios sol   ON sol.id = t.solicitante_id
     LEFT JOIN adm_usuarios ag    ON ag.id  = t.agente_id
     LEFT JOIN adm_usuarios cr    ON cr.id  = t.created_by
     LEFT JOIN itsm_categorias cat ON cat.id = t.categoria_id
     LEFT JOIN itsm_catalogo_servicios srv ON srv.id = t.servicio_id
     LEFT JOIN cmdb_cis ci         ON ci.id  = t.ci_id
     LEFT JOIN cmdb_ubicaciones ub ON ub.id  = t.sede_id
     LEFT JOIN adm_grupos gr       ON gr.id  = t.grupo_id
     WHERE t.id = ?", [$id]
);
if (!$tk) { flash('error','Ticket no encontrado'); redirect(BASE_URL.'/modules/itsm/tickets.php'); }

$pageTitle  = 'Ticket — ' . $tk['numero'];
$pageModule = 'tickets';

// Determinar si el usuario es agente/admin
$esAgente = Auth::isAgent();

// ── Comentarios ───────────────────────────────────────────────
$sqlComentarios = $esAgente
    ? "SELECT c.*, u.nombre AS autor_nombre FROM itsm_comentarios c LEFT JOIN adm_usuarios u ON u.id = c.usuario_id WHERE c.ticket_id = ? ORDER BY c.created_at ASC"
    : "SELECT c.*, u.nombre AS autor_nombre FROM itsm_comentarios c LEFT JOIN adm_usuarios u ON u.id = c.usuario_id WHERE c.ticket_id = ? AND c.tipo != 'Interno' ORDER BY c.created_at ASC";
$comentarios = DB::query($sqlComentarios, [$id]);

// ── Adjuntos ──────────────────────────────────────────────────
$adjuntos = DB::query(
    "SELECT * FROM itsm_adjuntos WHERE entidad_tipo='ticket' AND entidad_id=? ORDER BY created_at",
    [$id]
) ?: [];

// ── Tickets relacionados ──────────────────────────────────────
$relacionados = [];
if (!empty($tk['ticket_padre_id'])) {
    $rel = DB::row("SELECT id, numero, titulo, estado FROM itsm_tickets WHERE id=?", [$tk['ticket_padre_id']]);
    if ($rel) $relacionados[] = $rel;
}
$hijos = DB::query("SELECT id, numero, titulo, estado FROM itsm_tickets WHERE ticket_padre_id=?", [$id]) ?: [];

// ── POST: agregar comentario ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postClean('action') === 'comentar') {
    Auth::verifyCsrf();
    $contenido = postClean('contenido');
    $tipo      = ($esAgente && in_array(postClean('tipo_comentario'), ['Publico','Interno','Sistema']))
                 ? postClean('tipo_comentario') : 'Publico';
    if ($contenido) {
        DB::insertRow('itsm_comentarios', [
            'ticket_id'  => $id,
            'usuario_id' => Auth::userId(),
            'tipo'       => $tipo,
            'contenido'  => $contenido,
        ]);
        if ($tipo === 'Publico' && !$tk['fecha_primer_respuesta']) {
            $cumpleSla = ($tk['sla_respuesta_limite'] && strtotime($tk['sla_respuesta_limite']) >= time()) ? 1 : 0;
            DB::updateRow('itsm_tickets',
                ['fecha_primer_respuesta' => date('Y-m-d H:i:s'), 'sla_respuesta_cumplido' => $cumpleSla],
                'id = ?', [$id]
            );
        }
        flash('success','Comentario agregado.');
    }
    redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}#comentarios");
}

// ── POST: cambio rapido ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postClean('action') === 'cambio_rapido') {
    Auth::verifyCsrf();
    $cambios     = [];
    $nuevoEstado = postClean('estado');
    $nuevoAgente = postInt('agente_id');

    if ($nuevoEstado) $cambios['estado'] = $nuevoEstado;
    if ($nuevoAgente > 0) {
        $cambios['agente_id'] = $nuevoAgente;
        if (!$tk['fecha_asignacion']) $cambios['fecha_asignacion'] = date('Y-m-d H:i:s');
    } elseif ($nuevoAgente === 0 && postClean('agente_id') === '0') {
        $cambios['agente_id'] = null;
    }

    if (in_array($nuevoEstado, ['Resuelto','Cerrado']) && !$tk['fecha_resolucion']) {
        $cambios['fecha_resolucion']        = date('Y-m-d H:i:s');
        $cambios['sla_resolucion_cumplido'] = ($tk['sla_resolucion_limite'] && strtotime($tk['sla_resolucion_limite']) >= time()) ? 1 : 0;
    }

    if ($cambios) {
        DB::updateRow('itsm_tickets', $cambios, 'id = ?', [$id]);
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Cambio rapido en {$tk['numero']}");
    }
    redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}");
}

// ── Utilidades de display ─────────────────────────────────────
$priorColor = ITIL::colorPrioridad($tk['prioridad']);
$slaResp    = ITIL::estadoSLA($tk['sla_respuesta_limite'],  $tk['sla_respuesta_cumplido']  !== null ? (int)$tk['sla_respuesta_cumplido']  : null);
$slaResol   = ITIL::estadoSLA($tk['sla_resolucion_limite'], $tk['sla_resolucion_cumplido'] !== null ? (int)$tk['sla_resolucion_cumplido'] : null);

$estadoColors = [
    'Nuevo'      => ['bg'=>'#E3F2FD','color'=>'#1565C0'],
    'Asignado'   => ['bg'=>'#E0F7FA','color'=>'#00838F'],
    'En_proceso' => ['bg'=>'#FFF8E1','color'=>'#F9A825'],
    'En_espera'  => ['bg'=>'#ECEFF1','color'=>'#546E7A'],
    'Resuelto'   => ['bg'=>'#E8F5E9','color'=>'#2E7D32'],
    'Cerrado'    => ['bg'=>'#212121','color'=>'#FFFFFF'],
    'Cancelado'  => ['bg'=>'#FFEBEE','color'=>'#C62828'],
];
$sc = $estadoColors[$tk['estado']] ?? ['bg'=>'#E0E0E0','color'=>'#333'];

$agentes = DB::query("SELECT id, nombre FROM adm_usuarios WHERE activo=1 ORDER BY nombre");

ob_start();
?>
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="tickets.php" class="text-decoration-none">Tickets</a></li>
        <li class="breadcrumb-item active"><?= h($tk['numero']) ?></li>
    </ol>
</nav>

<!-- Encabezado -->
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h5 class="mb-1 fw-bold" style="color:#1A3A5C">
            <span class="me-2 font-monospace" style="color:#1565C0"><?= h($tk['numero']) ?></span>
            <?= h($tk['titulo']) ?>
        </h5>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge px-3 py-2 rounded-pill fw-semibold" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>">
                <?= str_replace('_',' ',$tk['estado']) ?>
            </span>
            <span class="badge bg-light text-dark border"><?= $tk['tipo'] ?></span>
            <span class="badge" style="background:<?= $priorColor ?>22;color:<?= $priorColor ?>;border:1px solid <?= $priorColor ?>">
                <?= $tk['prioridad'] ?>
            </span>
            <?php if ($tk['categoria_nombre']): ?>
            <span class="badge" style="background:<?= $tk['categoria_color'] ?>22;color:<?= $tk['categoria_color'] ?>">
                <i class="fa-solid <?= $tk['categoria_icono'] ?> me-1"></i><?= h($tk['categoria_nombre']) ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if (!in_array($tk['estado'],['Cerrado','Cancelado'])): ?>
        <a href="ticket_form.php?id=<?= $id ?>" class="btn btn-sm btn-warning">
            <i class="fa-solid fa-pen me-1"></i>Editar
        </a>
        <?php endif; ?>
        <a href="tickets.php" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Columna principal -->
    <div class="col-12 col-lg-8">

        <!-- Descripcion -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-align-left me-2 text-primary"></i>Descripcion
                </span>
            </div>
            <div class="card-body p-3">
                <div class="bg-light rounded-3 p-3" style="white-space:pre-wrap;font-size:0.9em"><?= nl2br(h($tk['descripcion'])) ?></div>
            </div>
        </div>

        <!-- Resolucion -->
        <?php if ($tk['workaround'] || $tk['solucion']): ?>
        <div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #2E7D32 !important;">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small text-success">
                    <i class="fa-solid fa-circle-check me-2"></i>Resolucion
                </span>
            </div>
            <div class="card-body p-3">
                <?php if ($tk['workaround']): ?>
                <div class="mb-3">
                    <div class="small fw-semibold text-muted mb-1">Workaround:</div>
                    <div class="bg-light rounded-3 p-2" style="white-space:pre-wrap;font-size:0.85em"><?= nl2br(h($tk['workaround'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($tk['solucion']): ?>
                <div>
                    <div class="small fw-semibold text-muted mb-1">Solucion definitiva:</div>
                    <div class="bg-success bg-opacity-10 rounded-3 p-2" style="white-space:pre-wrap;font-size:0.85em"><?= nl2br(h($tk['solucion'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Adjuntos -->
        <?php if (!empty($adjuntos)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-paperclip me-2"></i>Adjuntos (<?= count($adjuntos) ?>)
                </span>
            </div>
            <div class="card-body p-3 d-flex flex-wrap gap-2">
                <?php foreach ($adjuntos as $adj): ?>
                <a href="<?= BASE_URL ?>/<?= h($adj['ruta_archivo']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-file me-1"></i>
                    <?= h(truncate($adj['nombre_original'], 30)) ?>
                    <span class="text-muted ms-1" style="font-size:0.75em">(<?= formatBytes((int)($adj['tamano_bytes'] ?? 0)) ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline de comentarios -->
        <div class="card border-0 shadow-sm mb-4" id="comentarios">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-comments me-2 text-primary"></i>
                    Historial
                    <span class="badge bg-secondary ms-1"><?= count($comentarios) ?></span>
                </span>
            </div>
            <div class="card-body p-3">
                <?php if (empty($comentarios)): ?>
                <div class="text-center text-muted py-4 small">
                    <i class="fa-regular fa-comment-dots fa-2x mb-2 d-block opacity-25"></i>
                    Aun no hay comentarios en este ticket.
                </div>
                <?php else: ?>
                <?php foreach ($comentarios as $com): ?>
                <?php
                    $esInterno = $com['tipo'] === 'Interno';
                    $esSistema = $com['tipo'] === 'Sistema';
                    $iniciales = strtoupper(substr($com['autor_nombre'] ?? 'S', 0, 2));
                    $bgAvatar  = $esSistema ? '#94A3B8' : ($esInterno ? '#6A1B9A' : '#1565C0');
                ?>
                <div class="d-flex gap-3 mb-3 <?= $esInterno ? 'opacity-75' : '' ?>">
                    <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                         style="width:36px;height:36px;font-size:0.7em;background:<?= $bgAvatar ?>;min-width:36px">
                        <?= $esSistema ? '<i class="fa-solid fa-robot" style="font-size:0.8em"></i>' : $iniciales ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold small"><?= h($com['autor_nombre'] ?? 'Sistema') ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($esInterno): ?>
                                <span class="badge" style="background:#F3E5F5;color:#6A1B9A;border:1px solid #6A1B9A;font-size:0.65em">
                                    <i class="fa-solid fa-lock me-1"></i>Interno
                                </span>
                                <?php endif; ?>
                                <span class="text-muted" style="font-size:0.75em">
                                    <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="rounded-3 p-3"
                             style="<?= $esInterno ? 'background:#F3E5F5;border-left:3px solid #6A1B9A' : ($esSistema ? 'background:#F5F5F5' : 'background:#EFF6FF') ?>;font-size:0.9em;white-space:pre-wrap">
                            <?= nl2br(h($com['contenido'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!in_array($tk['estado'],['Cerrado','Cancelado'])): ?>
                <hr class="my-3">
                <form method="post">
                    <?= Auth::csrfInput() ?>
                    <input type="hidden" name="action" value="comentar">
                    <?php if ($esAgente): ?>
                    <div class="d-flex gap-3 mb-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_comentario" id="tipoPub" value="Publico" checked>
                            <label class="form-check-label small" for="tipoPub">
                                <i class="fa-solid fa-globe me-1 text-primary"></i>Publico
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_comentario" id="tipoInt" value="Interno">
                            <label class="form-check-label small" for="tipoInt">
                                <i class="fa-solid fa-lock me-1 text-secondary"></i>Nota interna
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <textarea name="contenido" class="form-control form-control-sm mb-2" rows="3"
                              placeholder="Escribe un comentario o actualizacion..." required></textarea>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-paper-plane me-1"></i>Enviar comentario
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Columna lateral -->
    <div class="col-12 col-lg-4">

        <!-- SLA -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-clock me-2 text-warning"></i>SLA
                </span>
            </div>
            <div class="card-body p-3">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Primera respuesta</span>
                        <span class="badge" style="background:<?= $slaResp['color'] ?>22;color:<?= $slaResp['color'] ?>;border:1px solid <?= $slaResp['color'] ?>">
                            <i class="fa-solid <?= $slaResp['icon'] ?> me-1"></i><?= $slaResp['label'] ?>
                        </span>
                    </div>
                    <?php if ($tk['sla_respuesta_limite']): ?>
                    <div class="text-muted" style="font-size:0.75em">Limite: <?= date('d/m/Y H:i', strtotime($tk['sla_respuesta_limite'])) ?></div>
                    <?php endif; ?>
                    <?php if ($tk['fecha_primer_respuesta']): ?>
                    <div class="text-success" style="font-size:0.75em"><i class="fa-solid fa-check me-1"></i>Respondido: <?= date('d/m/Y H:i', strtotime($tk['fecha_primer_respuesta'])) ?></div>
                    <?php endif; ?>
                </div>
                <hr class="my-2">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Resolucion</span>
                        <span class="badge" style="background:<?= $slaResol['color'] ?>22;color:<?= $slaResol['color'] ?>;border:1px solid <?= $slaResol['color'] ?>">
                            <i class="fa-solid <?= $slaResol['icon'] ?> me-1"></i><?= $slaResol['label'] ?>
                        </span>
                    </div>
                    <?php if ($tk['sla_resolucion_limite']): ?>
                    <div class="text-muted" style="font-size:0.75em">Limite: <?= date('d/m/Y H:i', strtotime($tk['sla_resolucion_limite'])) ?></div>
                    <?php endif; ?>
                    <?php if ($tk['fecha_resolucion']): ?>
                    <div class="text-success" style="font-size:0.75em"><i class="fa-solid fa-check me-1"></i>Resuelto: <?= date('d/m/Y H:i', strtotime($tk['fecha_resolucion'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Acciones rapidas (solo agentes) -->
        <?php if (!in_array($tk['estado'],['Cerrado','Cancelado']) && $esAgente): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-sliders me-2 text-primary"></i>Acciones rapidas
                </span>
            </div>
            <div class="card-body p-3">
                <form method="post">
                    <?= Auth::csrfInput() ?>
                    <input type="hidden" name="action" value="cambio_rapido">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cambiar estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <?php foreach(['Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado'] as $e): ?>
                            <option value="<?= $e ?>" <?= $tk['estado']===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reasignar agente</label>
                        <select name="agente_id" class="form-select form-select-sm">
                            <option value="0">— Sin asignar —</option>
                            <?php foreach ($agentes as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $tk['agente_id']==$a['id']?'selected':'' ?>>
                                <?= h($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fa-solid fa-check me-1"></i>Aplicar cambios
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detalles -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-circle-info me-2"></i>Detalles
                </span>
            </div>
            <div class="card-body p-3">
                <?php
                $detalles = [
                    ['icon'=>'fa-user',         'label'=>'Solicitante', 'val'=>$tk['solicitante_nombre']],
                    ['icon'=>'fa-user-gear',     'label'=>'Agente',      'val'=>$tk['agente_nombre']],
                    ['icon'=>'fa-users',         'label'=>'Grupo',       'val'=>$tk['grupo_nombre']],
                    ['icon'=>'fa-layer-group',   'label'=>'Servicio',    'val'=>$tk['servicio_nombre']],
                    ['icon'=>'fa-server',        'label'=>'CI CMDB',     'val'=>$tk['ci_nombre'] ? "[{$tk['codigo_ci']}] {$tk['ci_nombre']}" : null],
                    ['icon'=>'fa-location-dot',  'label'=>'Sede',        'val'=>$tk['sede_nombre']],
                    ['icon'=>'fa-tower-cell',    'label'=>'Origen',      'val'=>$tk['origen']],
                    ['icon'=>'fa-calendar-plus', 'label'=>'Apertura',    'val'=>$tk['fecha_apertura'] ? date('d/m/Y H:i', strtotime($tk['fecha_apertura'])) : null],
                    ['icon'=>'fa-user-pen',      'label'=>'Creado por',  'val'=>$tk['creado_por_nombre']],
                ];
                foreach ($detalles as $d):
                    if (!$d['val']) continue; ?>
                <div class="d-flex gap-2 mb-2 align-items-start">
                    <i class="fa-solid <?= $d['icon'] ?> text-muted mt-1" style="width:16px;font-size:0.8em"></i>
                    <div>
                        <div class="text-muted" style="font-size:0.7em;line-height:1"><?= $d['label'] ?></div>
                        <div class="small fw-semibold"><?= h($d['val']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tickets relacionados -->
        <?php if (!empty($relacionados) || !empty($hijos)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small" style="color:#1A3A5C">
                    <i class="fa-solid fa-diagram-project me-2"></i>Relacionados
                </span>
            </div>
            <div class="card-body p-3">
                <?php foreach (array_merge($relacionados, $hijos) as $rel): if (!$rel) continue; ?>
                <a href="ticket_detalle.php?id=<?= $rel['id'] ?>" class="d-flex gap-2 text-decoration-none mb-2">
                    <span class="font-monospace small" style="color:#1565C0"><?= h($rel['numero']) ?></span>
                    <span class="text-dark small"><?= h(truncate($rel['titulo'], 40)) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
