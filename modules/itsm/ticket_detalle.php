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

$esAgente = Auth::isAgent();

$sqlComentarios = $esAgente
    ? "SELECT c.*, u.nombre AS autor_nombre FROM itsm_comentarios c LEFT JOIN adm_usuarios u ON u.id = c.usuario_id WHERE c.ticket_id = ? ORDER BY c.created_at ASC"
    : "SELECT c.*, u.nombre AS autor_nombre FROM itsm_comentarios c LEFT JOIN adm_usuarios u ON u.id = c.usuario_id WHERE c.ticket_id = ? AND c.tipo != 'Interno' ORDER BY c.created_at ASC";
$comentarios = DB::query($sqlComentarios, [$id]);

$adjuntos = DB::query(
    "SELECT * FROM itsm_adjuntos WHERE entidad_tipo='ticket' AND entidad_id=? ORDER BY created_at", [$id]
) ?: [];

$relacionados = [];
if (!empty($tk['ticket_padre_id'])) {
    $rel = DB::row("SELECT id, numero, titulo, estado FROM itsm_tickets WHERE id=?", [$tk['ticket_padre_id']]);
    if ($rel) $relacionados[] = $rel;
}
$hijos = DB::query("SELECT id, numero, titulo, estado FROM itsm_tickets WHERE ticket_padre_id=?", [$id]) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && postClean('action') === 'comentar') {
    Auth::verifyCsrf();
    $contenido = postClean('contenido');
    $tipo = ($esAgente && in_array(postClean('tipo_comentario'), ['Publico','Interno','Sistema']))
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

$priorColor = ITIL::colorPrioridad($tk['prioridad']);
$slaResp    = ITIL::estadoSLA($tk['sla_respuesta_limite'],  $tk['sla_respuesta_cumplido']  !== null ? (int)$tk['sla_respuesta_cumplido']  : null);
$slaResol   = ITIL::estadoSLA($tk['sla_resolucion_limite'], $tk['sla_resolucion_cumplido'] !== null ? (int)$tk['sla_resolucion_cumplido'] : null);

$estadoColors = [
    'Nuevo'      => ['#E3F2FD','#1565C0'],
    'Asignado'   => ['#E0F7FA','#00838F'],
    'En_proceso' => ['#FFF8E1','#F9A825'],
    'En_espera'  => ['#ECEFF1','#546E7A'],
    'Resuelto'   => ['#E8F5E9','#2E7D32'],
    'Cerrado'    => ['#212121','#FFFFFF'],
    'Cancelado'  => ['#FFEBEE','#C62828'],
];
[$eBg, $eColor] = $estadoColors[$tk['estado']] ?? ['#E0E0E0','#333'];

$cerrado  = in_array($tk['estado'], ['Cerrado','Cancelado']);
$agentes  = DB::query("SELECT id, nombre FROM adm_usuarios WHERE activo=1 ORDER BY nombre");

ob_start();
?>
<!-- Breadcrumb -->
<div style="font-size:12px;color:#94A3B8;margin-bottom:12px;">
    <a href="tickets.php" style="color:#94A3B8;text-decoration:none;">Tickets</a>
    <i class="fa-solid fa-chevron-right" style="font-size:10px;margin:0 6px;"></i>
    <span><?= h($tk['numero']) ?></span>
</div>

<!-- Encabezado del ticket -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div style="flex:1;min-width:0;">
        <h1 class="page-title" style="margin-bottom:10px;">
            <span class="mono" style="color:#1565C0;margin-right:10px;"><?= h($tk['numero']) ?></span>
            <?= h($tk['titulo']) ?>
        </h1>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span class="badge" style="background:<?= $eBg ?>;color:<?= $eColor ?>;border:1px solid <?= $eColor ?>33;font-size:12px;padding:5px 12px;">
                <?= str_replace('_',' ',$tk['estado']) ?>
            </span>
            <span class="badge" style="background:#F1F5F9;color:#475569;border:1px solid #E2E8F0;">
                <?= $tk['tipo'] ?>
            </span>
            <span class="badge" style="background:<?= $priorColor ?>22;color:<?= $priorColor ?>;border:1px solid <?= $priorColor ?>44;">
                <?= $tk['prioridad'] ?>
            </span>
            <?php if ($tk['categoria_nombre']): ?>
            <span class="badge" style="background:<?= $tk['categoria_color'] ?>22;color:<?= $tk['categoria_color'] ?>;border:1px solid <?= $tk['categoria_color'] ?>44;">
                <?php if ($tk['categoria_icono']): ?><i class="fa-solid <?= $tk['categoria_icono'] ?>" style="margin-right:4px;"></i><?php endif; ?>
                <?= h($tk['categoria_nombre']) ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0;">
        <?php if (!$cerrado): ?>
        <a href="ticket_form.php?id=<?= $id ?>" class="btn btn-sm" style="background:#FFF8E1;color:#F9A825;border:1px solid #FFE082;">
            <i class="fa-solid fa-pen"></i> Editar
        </a>
        <?php endif; ?>
        <a href="tickets.php" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<!-- Layout principal -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

    <!-- Columna principal -->
    <div>
        <!-- Descripción -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-align-left" style="color:#1565C0;margin-right:8px;"></i>
                    Descripción
                </span>
            </div>
            <div class="card-body">
                <div style="background:#F8FAFC;border-radius:8px;padding:16px;white-space:pre-wrap;font-size:13px;line-height:1.6;">
                    <?= nl2br(h($tk['descripcion'])) ?>
                </div>
            </div>
        </div>

        <!-- Resolución -->
        <?php if ($tk['workaround'] || $tk['solucion']): ?>
        <div class="card" style="margin-bottom:20px;border-left:3px solid #2E7D32;">
            <div class="card-header">
                <span class="card-title" style="color:#2E7D32;">
                    <i class="fa-solid fa-circle-check" style="margin-right:8px;"></i>Resolución
                </span>
            </div>
            <div class="card-body">
                <?php if ($tk['workaround']): ?>
                <div style="margin-bottom:14px;">
                    <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Workaround</div>
                    <div style="background:#F8FAFC;border-radius:8px;padding:12px;white-space:pre-wrap;font-size:13px;"><?= nl2br(h($tk['workaround'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($tk['solucion']): ?>
                <div>
                    <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Solución definitiva</div>
                    <div style="background:#E8F5E9;border-radius:8px;padding:12px;white-space:pre-wrap;font-size:13px;"><?= nl2br(h($tk['solucion'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Adjuntos -->
        <?php if (!empty($adjuntos)): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-paperclip" style="margin-right:8px;"></i>
                    Adjuntos (<?= count($adjuntos) ?>)
                </span>
            </div>
            <div class="card-body" style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($adjuntos as $adj): ?>
                <a href="<?= BASE_URL ?>/<?= h($adj['ruta_archivo']) ?>" target="_blank"
                   class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-file"></i>
                    <?= h(truncate($adj['nombre_original'], 30)) ?>
                    <span style="font-size:11px;color:#94A3B8;margin-left:4px;">(<?= formatBytes((int)($adj['tamano_bytes'] ?? 0)) ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline comentarios -->
        <div class="card" id="comentarios">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-comments" style="color:#1565C0;margin-right:8px;"></i>
                    Historial
                    <span class="badge" style="background:#E8EAF6;color:#1565C0;border:1px solid #c5cae9;margin-left:6px;"><?= count($comentarios) ?></span>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($comentarios)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-comment-dots"></i>
                    <p>Aún no hay comentarios en este ticket.</p>
                </div>
                <?php else: ?>
                <?php foreach ($comentarios as $com):
                    $esInterno = $com['tipo'] === 'Interno';
                    $esSistema = $com['tipo'] === 'Sistema';
                    $iniciales = strtoupper(substr($com['autor_nombre'] ?? 'S', 0, 2));
                    $bgAvatar  = $esSistema ? '#94A3B8' : ($esInterno ? '#6A1B9A' : '#1565C0');
                ?>
                <div style="display:flex;gap:12px;margin-bottom:16px;<?= $esInterno ? 'opacity:.8' : '' ?>">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $bgAvatar ?>;color:white;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <?= $esSistema ? '<i class="fa-solid fa-robot" style="font-size:12px;"></i>' : $iniciales ?>
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-weight:600;font-size:13px;"><?= h($com['autor_nombre'] ?? 'Sistema') ?></span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <?php if ($esInterno): ?>
                                <span class="badge" style="background:#F3E5F5;color:#6A1B9A;border:1px solid #6A1B9A44;font-size:10px;">
                                    <i class="fa-solid fa-lock" style="margin-right:3px;"></i>Interno
                                </span>
                                <?php endif; ?>
                                <span style="font-size:11px;color:#94A3B8;">
                                    <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div style="border-radius:10px;padding:12px;font-size:13px;white-space:pre-wrap;<?=
                            $esInterno ? 'background:#F3E5F5;border-left:3px solid #6A1B9A;' :
                            ($esSistema ? 'background:#F5F5F5;' : 'background:#EFF6FF;')
                        ?>">
                            <?= nl2br(h($com['contenido'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$cerrado): ?>
                <hr style="border:none;border-top:1px solid #F1F5F9;margin:16px 0;">
                <form method="post">
                    <?= Auth::csrfInput() ?>
                    <input type="hidden" name="action" value="comentar">
                    <?php if ($esAgente): ?>
                    <div style="display:flex;gap:16px;margin-bottom:12px;">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="radio" name="tipo_comentario" value="Publico" checked>
                            <i class="fa-solid fa-globe" style="color:#1565C0;font-size:12px;"></i> Público
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="radio" name="tipo_comentario" value="Interno">
                            <i class="fa-solid fa-lock" style="color:#6A1B9A;font-size:12px;"></i> Nota interna
                        </label>
                    </div>
                    <?php endif; ?>
                    <textarea name="contenido" class="form-control" rows="3"
                              placeholder="Escribe un comentario o actualización..." required
                              style="margin-bottom:10px;"></textarea>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-paper-plane"></i> Enviar comentario
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Columna lateral -->
    <div>
        <!-- SLA -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-clock" style="color:#F9A825;margin-right:8px;"></i>SLA
                </span>
            </div>
            <div class="card-body">
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;font-weight:600;">Primera respuesta</span>
                        <span class="badge" style="background:<?= $slaResp['color'] ?>22;color:<?= $slaResp['color'] ?>;border:1px solid <?= $slaResp['color'] ?>44;font-size:10px;">
                            <i class="fa-solid <?= $slaResp['icon'] ?>" style="margin-right:3px;"></i><?= $slaResp['label'] ?>
                        </span>
                    </div>
                    <?php if ($tk['sla_respuesta_limite']): ?>
                    <div style="font-size:11px;color:#94A3B8;">Límite: <?= date('d/m/Y H:i', strtotime($tk['sla_respuesta_limite'])) ?></div>
                    <?php endif; ?>
                    <?php if ($tk['fecha_primer_respuesta']): ?>
                    <div style="font-size:11px;color:#2E7D32;"><i class="fa-solid fa-check" style="margin-right:3px;"></i>Respondido: <?= date('d/m/Y H:i', strtotime($tk['fecha_primer_respuesta'])) ?></div>
                    <?php endif; ?>
                </div>
                <hr style="border:none;border-top:1px solid #F1F5F9;margin:10px 0;">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;font-weight:600;">Resolución</span>
                        <span class="badge" style="background:<?= $slaResol['color'] ?>22;color:<?= $slaResol['color'] ?>;border:1px solid <?= $slaResol['color'] ?>44;font-size:10px;">
                            <i class="fa-solid <?= $slaResol['icon'] ?>" style="margin-right:3px;"></i><?= $slaResol['label'] ?>
                        </span>
                    </div>
                    <?php if ($tk['sla_resolucion_limite']): ?>
                    <div style="font-size:11px;color:#94A3B8;">Límite: <?= date('d/m/Y H:i', strtotime($tk['sla_resolucion_limite'])) ?></div>
                    <?php endif; ?>
                    <?php if ($tk['fecha_resolucion']): ?>
                    <div style="font-size:11px;color:#2E7D32;"><i class="fa-solid fa-check" style="margin-right:3px;"></i>Resuelto: <?= date('d/m/Y H:i', strtotime($tk['fecha_resolucion'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <?php if (!$cerrado && $esAgente): ?>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-sliders" style="color:#1565C0;margin-right:8px;"></i>
                    Acciones rápidas
                </span>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfInput() ?>
                    <input type="hidden" name="action" value="cambio_rapido">
                    <div class="form-group">
                        <label class="form-label">Cambiar estado</label>
                        <select name="estado" class="form-control">
                            <?php foreach(['Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado'] as $e): ?>
                            <option value="<?= $e ?>" <?= $tk['estado']===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reasignar agente</label>
                        <select name="agente_id" class="form-control">
                            <option value="0">— Sin asignar —</option>
                            <?php foreach ($agentes as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $tk['agente_id']==$a['id']?'selected':'' ?>>
                                <?= h($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">
                        <i class="fa-solid fa-check"></i> Aplicar cambios
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detalles -->
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-circle-info" style="margin-right:8px;"></i>Detalles
                </span>
            </div>
            <div class="card-body">
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
                <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px;">
                    <i class="fa-solid <?= $d['icon'] ?>" style="color:#94A3B8;font-size:12px;margin-top:3px;width:14px;flex-shrink:0;"></i>
                    <div>
                        <div style="font-size:10px;color:#94A3B8;text-transform:uppercase;letter-spacing:.04em;"><?= $d['label'] ?></div>
                        <div style="font-size:13px;font-weight:500;color:#1E293B;"><?= h($d['val']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Relacionados -->
        <?php if (!empty($relacionados) || !empty($hijos)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="fa-solid fa-diagram-project" style="margin-right:8px;"></i>Relacionados
                </span>
            </div>
            <div class="card-body">
                <?php foreach (array_merge($relacionados, $hijos) as $rel): if (!$rel) continue; ?>
                <a href="ticket_detalle.php?id=<?= $rel['id'] ?>"
                   style="display:flex;gap:8px;text-decoration:none;margin-bottom:8px;align-items:center;">
                    <span class="mono" style="color:#1565C0;font-size:12px;"><?= h($rel['numero']) ?></span>
                    <span style="color:#334155;font-size:13px;"><?= h(truncate($rel['titulo'], 35)) ?></span>
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
