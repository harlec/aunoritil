<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/admin/usuarios.php');

// Cualquier usuario puede ver su propio perfil; admin puede ver todos
if ($id !== Auth::userId() && !Auth::can('admin.*')) {
    http_response_code(403); die('Sin acceso.');
}

$u = DB::row(
    "SELECT u.*, r.nombre AS rol_nombre, r.permisos AS rol_permisos,
     g.nombre AS grupo_nombre
     FROM adm_usuarios u
     LEFT JOIN adm_roles r          ON r.id = u.rol_id
     LEFT JOIN adm_grupos g ON g.id = u.grupo_id
     WHERE u.id=?", [$id]
);
if (!$u) { flash('error','Usuario no encontrado'); redirect(BASE_URL.'/modules/admin/usuarios.php'); }

$tab = clean($_GET['tab'] ?? 'info');

// ── Datos por tab ──────────────────────────────────────────
$equipos = [];
$tickets = [];
$auditoria = [];

if ($tab === 'equipos' || $tab === 'info') {
    $equipos = DB::query(
        "SELECT c.*, ub.nombre AS sede
         FROM cmdb_cis c
         LEFT JOIN cmdb_ubicaciones ub ON ub.id = c.ubicacion_id
         WHERE c.propietario_id = ?
         ORDER BY c.estado = 'Activo' DESC, c.nombre ASC",
        [$id]
    );
}
if ($tab === 'tickets') {
    $tickets = DB::query(
        "SELECT t.numero, t.titulo, t.tipo, t.estado, t.prioridad,
         t.fecha_apertura, t.fecha_cierre,
         a.nombre AS agente_nombre
         FROM itsm_tickets t
         LEFT JOIN adm_usuarios a ON a.id = t.agente_id
         WHERE t.solicitante_id = ? OR t.agente_id = ?
         ORDER BY t.fecha_apertura DESC LIMIT 20",
        [$id, $id]
    );
}
if ($tab === 'actividad') {
    $auditoria = DB::query(
        "SELECT * FROM adm_auditoria
         WHERE usuario_id = ?
         ORDER BY created_at DESC LIMIT 30",
        [$id]
    );
}

// Estadísticas del usuario
$stats = [
    'equipos'         => DB::value("SELECT COUNT(*) FROM cmdb_cis WHERE propietario_id=? AND estado='Activo'", [$id]),
    'tickets_abiertos'=> DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE agente_id=? AND estado NOT IN ('Cerrado','Cancelado')", [$id]),
    'tickets_mes'     => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE agente_id=? AND MONTH(fecha_apertura)=MONTH(NOW())", [$id]),
    'resueltos'       => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE agente_id=? AND estado IN ('Resuelto','Cerrado')", [$id]),
];

$permisos   = json_decode($u['rol_permisos'] ?? '[]', true) ?: [];
$pageTitle  = $u['nombre'];
$pageModule = 'admin';

$avatarColors = ['#1A3A5C','#00695C','#6A1B9A','#E65100','#1565C0','#2E7D32'];
$aColor = $avatarColors[crc32($u['email']) % count($avatarColors)];

require_once __DIR__ . '/../../includes/layout.php';
?>

<!-- ── Header ───────────────────────────────────────────────── -->
<div class="page-header">
  <div style="display:flex;align-items:center;gap:16px;">
    <div style="width:60px;height:60px;border-radius:16px;background:<?= $aColor ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex-shrink:0;">
      <?= strtoupper(substr($u['nombre'],0,2)) ?>
    </div>
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <h1 class="page-title" style="margin:0;"><?= h($u['nombre']) ?></h1>
        <?php if($u['activo']): ?>
        <span class="badge" style="background:#E8F5E9;color:#2E7D32;border:1px solid #A5D6A730;">Activo</span>
        <?php else: ?>
        <span class="badge" style="background:#FFEBEE;color:#EF5350;border:1px solid #FFCDD230;">Inactivo</span>
        <?php endif; ?>
        <?php
        $tipoStyle = match($u['tipo']) {
            'Admin'=>['#F3E5F5','#6A1B9A'],'Agente'=>['#E3F2FD','#1565C0'],
            'Supervisor'=>['#E0F2F1','#00695C'],default=>['#F5F5F5','#616161']
        };
        ?>
        <span class="badge" style="background:<?= $tipoStyle[0] ?>;color:<?= $tipoStyle[1] ?>;border:1px solid <?= $tipoStyle[1] ?>30;">
          <?= str_replace('_',' ',$u['tipo']) ?>
        </span>
      </div>
      <div style="font-size:13px;color:#64748B;margin-top:3px;display:flex;gap:12px;flex-wrap:wrap;">
        <span><?= h($u['email']) ?></span>
        <?php if($u['cargo']): ?><span>· <?= h($u['cargo']) ?></span><?php endif; ?>
        <?php if($u['sede']): ?><span>· <i class="fas fa-map-pin" style="font-size:10px;"></i> <?= h($u['sede']) ?></span><?php endif; ?>
      </div>
    </div>
  </div>
  <?php if(Auth::can('admin.*')): ?>
  <div style="display:flex;gap:10px;">
    <a href="usuario_form.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-pen"></i> Editar</a>
    <a href="usuarios.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
  <?php endif; ?>
</div>

<!-- KPIs del usuario -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='?id=<?= $id ?>&tab=equipos'">
    <div class="kpi-icon" style="background:#E0F2F1;color:#00695C;"><i class="fas fa-server"></i></div>
    <div class="kpi-label">Equipos asignados</div>
    <div class="kpi-value" style="color:#00695C;"><?= $stats['equipos'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#E3F2FD;color:#1565C0;"><i class="fas fa-ticket"></i></div>
    <div class="kpi-label">Tickets abiertos</div>
    <div class="kpi-value" style="color:#1565C0;"><?= $stats['tickets_abiertos'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-circle-check"></i></div>
    <div class="kpi-label">Resueltos (total)</div>
    <div class="kpi-value" style="color:#2E7D32;"><?= $stats['resueltos'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#FBE9E7;color:#E65100;"><i class="fas fa-calendar-check"></i></div>
    <div class="kpi-label">Tickets este mes</div>
    <div class="kpi-value" style="color:#E65100;"><?= $stats['tickets_mes'] ?></div>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:2px;margin-bottom:20px;background:white;border:1px solid #EEF2F7;border-radius:12px;padding:4px;width:fit-content;">
  <?php $tabs = [
    'info'     => ['fa-circle-info',     'Información'],
    'equipos'  => ['fa-server',          'Equipos ('.count($equipos).')'],
    'tickets'  => ['fa-ticket',          'Tickets'],
    'actividad'=> ['fa-clock-rotate-left','Actividad'],
  ];
  foreach($tabs as $key=>[$icon,$label]):
    $act = $tab===$key;
  ?>
  <a href="?id=<?= $id ?>&tab=<?= $key ?>"
     style="display:flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;font-size:13px;font-weight:<?= $act?'600':'500' ?>;text-decoration:none;
            background:<?= $act?'#1A3A5C':'transparent' ?>;color:<?= $act?'white':'#64748B' ?>;transition:all .15s;">
    <i class="fas <?= $icon ?>"></i><?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if($tab==='info'): ?>
<!-- ════════════ TAB INFO ════════════ -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Datos de contacto -->
    <div class="card">
      <div class="card-header"><span class="card-title">Datos de contacto</span></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;row-gap:0;">
          <?php foreach([
            ['Email',         $u['email'],       false],
            ['Teléfono/Anexo',$u['telefono'],    true],
            ['Cargo',         $u['cargo'],       false],
            ['Sede',          $u['sede'],        false],
            ['Grupo soporte', $u['grupo_nombre'],false],
            ['Último login',  $u['ultimo_login']?tiempoRelativo($u['ultimo_login']):'Nunca', false],
            ['Registro',      fechaHumana($u['created_at'],true), false],
          ] as [$l,$v,$mono]): if(!$v) continue; ?>
          <div style="padding:10px 0;border-bottom:1px solid #F8FAFC;">
            <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.04em;"><?= $l ?></div>
            <div style="font-size:13px;color:#0F172A;margin-top:2px;" class="<?= $mono?'mono':'' ?>"><?= h($v) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Accesos y permisos -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-shield-halved" style="color:#1565C0;margin-right:8px;"></i>Rol y permisos</span>
        <?php if(Auth::can('admin.*')): ?>
        <a href="roles.php" class="btn btn-ghost btn-sm"><i class="fas fa-pen"></i> Gestionar roles</a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div style="margin-bottom:14px;">
          <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:6px;">Rol asignado</div>
          <span style="background:#EDE7F6;color:#4A148C;font-size:13px;font-weight:600;padding:6px 14px;border-radius:8px;display:inline-block;">
            <i class="fas fa-shield-halved" style="margin-right:6px;"></i><?= h($u['rol_nombre']??'Sin rol') ?>
          </span>
        </div>
        <?php if(!empty($permisos)): ?>
        <div>
          <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:8px;">Permisos</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php if(in_array('*',$permisos)): ?>
            <span style="background:#4CAF50;color:white;font-size:12px;font-weight:600;padding:4px 12px;border-radius:6px;">
              <i class="fas fa-crown" style="margin-right:4px;"></i>Acceso total
            </span>
            <?php else: foreach($permisos as $perm): ?>
            <span style="background:#E8EAF6;color:#283593;font-size:11px;font-weight:600;padding:3px 10px;border-radius:6px;" class="mono">
              <?= h($perm) ?>
            </span>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Sidebar: resumen equipos -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-server" style="color:#00695C;margin-right:6px;"></i>Equipos</span>
        <a href="?id=<?= $id ?>&tab=equipos" class="btn btn-ghost btn-sm">Ver todos</a>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <?php if(empty($equipos)): ?>
        <p style="font-size:13px;color:#94A3B8;text-align:center;padding:12px 0;">Sin equipos asignados</p>
        <?php else: ?>
        <?php foreach(array_slice($equipos,0,5) as $eq): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F8FAFC;cursor:pointer;"
             onclick="location.href='<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $eq['id'] ?>'">
          <div style="width:32px;height:32px;border-radius:8px;background:#E0F2F1;color:#00695C;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;">
            <?php $iconos=['Laptop'=>'fa-laptop','Desktop'=>'fa-desktop','Servidor'=>'fa-server','Telefono_IP'=>'fa-phone','Switch'=>'fa-network-wired'];
            echo '<i class="fas '.($iconos[$eq['tipo_ci']]??'fa-cube').'"></i>'; ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:600;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($eq['nombre']) ?></div>
            <div style="font-size:11px;color:#94A3B8;" class="mono"><?= h($eq['codigo_ci']) ?></div>
          </div>
          <?= badgeEstado($eq['estado']) ?>
        </div>
        <?php endforeach; ?>
        <?php if(count($equipos)>5): ?>
        <a href="?id=<?= $id ?>&tab=equipos" style="display:block;text-align:center;margin-top:10px;font-size:12px;color:#4A90C4;">
          Ver <?= count($equipos)-5 ?> más...
        </a>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif($tab==='equipos'): ?>
<!-- ════════════ TAB EQUIPOS ════════════ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:700;color:#0F172A;">Equipos asignados a <?= h($u['nombre']) ?></h3>
  <?php if(Auth::can('cmdb.*')): ?>
  <button class="btn btn-primary btn-sm" onclick="openModal('modalAsignarEquipo')">
    <i class="fas fa-plus"></i> Asignar equipo
  </button>
  <?php endif; ?>
</div>

<?php if(empty($equipos)): ?>
<div class="card">
  <div class="empty-state">
    <i class="fas fa-server"></i>
    <p>Este usuario no tiene equipos asignados</p>
    <?php if(Auth::can('cmdb.*')): ?>
    <button class="btn btn-primary btn-sm" style="margin-top:12px;" onclick="openModal('modalAsignarEquipo')">
      <i class="fas fa-plus"></i> Asignar primer equipo
    </button>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>

<!-- Grid de equipos -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:20px;">
  <?php foreach($equipos as $eq):
    $iconos=['Laptop'=>'fa-laptop','Desktop'=>'fa-desktop','Servidor'=>'fa-server','Telefono_IP'=>'fa-phone','Switch'=>'fa-network-wired','Router'=>'fa-network-wired','UPS'=>'fa-bolt','Impresora'=>'fa-print'];
    $icon = $iconos[$eq['tipo_ci']] ?? 'fa-cube';
    $eColor = match($eq['estado']) {
      'Activo'=>'#2E7D32','Inactivo'=>'#616161','En_reparacion'=>'#E65100',
      'En_almacen'=>'#1565C0','Dado_de_baja'=>'#EF5350',default=>'#94A3B8'
    };
  ?>
  <div class="card" style="transition:box-shadow .15s;"
       onmouseenter="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
       onmouseleave="this.style.boxShadow=''">
    <div style="padding:16px 18px;">
      <!-- Header card -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div style="width:42px;height:42px;border-radius:11px;background:#E0F2F1;color:#00695C;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas <?= $icon ?>"></i>
        </div>
        <?= badgeEstado($eq['estado']) ?>
      </div>

      <!-- Nombre y código -->
      <div style="font-weight:700;color:#0F172A;font-size:14px;margin-bottom:2px;"><?= h($eq['nombre']) ?></div>
      <div style="font-size:11px;color:#94A3B8;margin-bottom:10px;" class="mono"><?= h($eq['codigo_ci']) ?></div>

      <!-- Detalles -->
      <div style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#64748B;">
        <?php if($eq['marca']||$eq['modelo']): ?>
        <div><i class="fas fa-tag" style="width:14px;color:#CBD5E1;margin-right:4px;"></i><?= h(trim($eq['marca'].' '.$eq['modelo'])) ?></div>
        <?php endif; ?>
        <?php if($eq['numero_serie']): ?>
        <div><i class="fas fa-barcode" style="width:14px;color:#CBD5E1;margin-right:4px;"></i><span class="mono"><?= h($eq['numero_serie']) ?></span></div>
        <?php endif; ?>
        <?php if($eq['ip_address']): ?>
        <div><i class="fas fa-network-wired" style="width:14px;color:#CBD5E1;margin-right:4px;"></i><span class="mono"><?= h($eq['ip_address']) ?></span></div>
        <?php endif; ?>
        <?php if($eq['sede']): ?>
        <div><i class="fas fa-map-pin" style="width:14px;color:#CBD5E1;margin-right:4px;"></i><?= h($eq['sede']) ?></div>
        <?php endif; ?>
        <?php if($eq['fecha_garantia_fin']): ?>
        <?php $dg = (int)((strtotime($eq['fecha_garantia_fin'])-time())/86400); $gc = $dg<0?'#EF5350':($dg<=30?'#FF6B00':'#4CAF50'); ?>
        <div style="color:<?= $gc ?>;"><i class="fas fa-shield-halved" style="width:14px;margin-right:4px;"></i>Garantía: <?= $dg<0?'Vencida':$dg.'d' ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer acciones -->
    <div style="border-top:1px solid #F8FAFC;padding:8px 14px;background:#FAFBFF;display:flex;gap:6px;justify-content:space-between;align-items:center;">
      <a href="<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $eq['id'] ?>" class="btn btn-ghost btn-sm">
        <i class="fas fa-eye"></i> Ver CI
      </a>
      <?php if(Auth::can('cmdb.*')): ?>
      <div style="display:flex;gap:4px;">
        <a href="<?= BASE_URL ?>/modules/cmdb/ci_form.php?id=<?= $eq['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Editar"><i class="fas fa-pen"></i></a>
        <button onclick="desasignarEquipo(<?= $eq['id'] ?>, '<?= h(addslashes($eq['nombre'])) ?>')"
                class="btn btn-ghost btn-sm btn-icon" title="Desasignar" style="color:#EF5350;">
          <i class="fas fa-user-minus"></i>
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Resumen rápido -->
<div class="card">
  <div class="card-body" style="padding:16px 20px;">
    <div style="display:flex;gap:24px;flex-wrap:wrap;">
      <?php
      $tiposCount = array_count_values(array_column($equipos,'tipo_ci'));
      arsort($tiposCount);
      foreach(array_slice($tiposCount,0,6,true) as $tipo=>$cnt):
      ?>
      <div style="text-align:center;">
        <div style="font-size:20px;font-weight:800;color:#1A3A5C;"><?= $cnt ?></div>
        <div style="font-size:11px;color:#94A3B8;"><?= str_replace('_',' ',$tipo) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal asignar equipo -->
<?php if(Auth::can('cmdb.*')): ?>
<div class="modal-overlay" id="modalAsignarEquipo">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-server" style="color:#00695C;margin-right:8px;"></i>Asignar Equipo a <?= h($u['nombre']) ?></span>
      <button class="modal-close" onclick="closeModal('modalAsignarEquipo')"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST" action="usuario_action.php">
      <?= Auth::csrfInput() ?>
      <input type="hidden" name="accion" value="asignar_equipo">
      <input type="hidden" name="usuario_id" value="<?= $id ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Buscar equipo por nombre o código</label>
          <input type="text" id="buscadorCI" class="form-control" placeholder="Ej: Laptop Dell, CI-0042..." oninput="filtrarCIs(this.value)" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">Seleccionar CI <span class="req">*</span></label>
          <select name="ci_id" class="form-control" id="selectCI" required size="8" style="height:auto;">
            <option value="">Escribe arriba para filtrar...</option>
            <?php
            $cisSinAsignar = DB::query(
                "SELECT id, codigo_ci, nombre, tipo_ci, marca, modelo
                 FROM cmdb_cis
                 WHERE (propietario_id IS NULL OR propietario_id != ?)
                   AND estado = 'Activo'
                 ORDER BY nombre
                 LIMIT 200",
                [$id]
            );
            foreach($cisSinAsignar as $ci): ?>
            <option value="<?= $ci['id'] ?>" data-search="<?= h(strtolower($ci['nombre'].' '.$ci['codigo_ci'].' '.$ci['tipo_ci'].' '.$ci['marca'].' '.$ci['modelo'])) ?>">
              <?= h($ci['codigo_ci']) ?> — <?= h($ci['nombre']) ?>
              <?php if($ci['marca']||$ci['modelo']): ?>(<?= h(trim($ci['marca'].' '.$ci['modelo'])) ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <p style="font-size:12px;color:#94A3B8;margin-top:4px;">
          <i class="fas fa-info-circle"></i> Solo muestra CIs activos sin propietario asignado (o con propietario diferente).
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalAsignarEquipo')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Asignar equipo</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php elseif($tab==='tickets'): ?>
<!-- ════════════ TAB TICKETS ════════════ -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Tickets relacionados a <?= h($u['nombre']) ?></span>
  </div>
  <?php if(empty($tickets)): ?>
  <div class="empty-state"><i class="fas fa-ticket"></i><p>Sin tickets</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Número</th><th>Asunto</th><th>Tipo</th><th>Rol</th><th>Prioridad</th><th>Estado</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach($tickets as $t): ?>
        <tr style="cursor:pointer;" onclick="location.href='<?= BASE_URL ?>/modules/itsm/tickets.php?id=<?= $t['id'] ?? '' ?>'">
          <td><span class="mono" style="font-size:12px;color:#4A90C4;font-weight:600;"><?= h($t['numero']) ?></span></td>
          <td style="font-weight:500;max-width:250px;">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($t['titulo']) ?></div>
            <?php if($t['agente_nombre']): ?><div style="font-size:11px;color:#94A3B8;">Agente: <?= h($t['agente_nombre']) ?></div><?php endif; ?>
          </td>
          <td><?= badgeTipo($t['tipo']) ?></td>
          <td style="font-size:12px;color:#64748B;"><?= $t['agente_nombre'] === $u['nombre'] ? 'Agente' : 'Solicitante' ?></td>
          <td><?= badgePrioridad($t['prioridad']) ?></td>
          <td><?= badgeEstado($t['estado']) ?></td>
          <td style="font-size:12px;color:#64748B;"><?= tiempoRelativo($t['fecha_apertura']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php elseif($tab==='actividad'): ?>
<!-- ════════════ TAB ACTIVIDAD ════════════ -->
<div class="card">
  <div class="card-header"><span class="card-title"><i class="fas fa-clock-rotate-left" style="color:#6A1B9A;margin-right:8px;"></i>Registro de actividad reciente</span></div>
  <?php if(empty($auditoria)): ?>
  <div class="empty-state"><i class="fas fa-clock"></i><p>Sin actividad registrada</p></div>
  <?php else: ?>
  <div style="padding:8px 20px;">
    <?php foreach($auditoria as $a): ?>
    <div style="display:flex;gap:14px;padding:10px 0;border-bottom:1px solid #F8FAFC;">
      <?php
      $aIcon = match($a['accion']) {
        'LOGIN'=>'fa-arrow-right-to-bracket','LOGOUT'=>'fa-arrow-right-from-bracket',
        'CREAR'=>'fa-plus','EDITAR'=>'fa-pen','ELIMINAR'=>'fa-trash',default=>'fa-circle-dot'
      };
      $aColor2 = match($a['accion']) {
        'LOGIN'=>'#4CAF50','LOGOUT'=>'#94A3B8','CREAR'=>'#1565C0','EDITAR'=>'#FF6B00','ELIMINAR'=>'#EF5350',default=>'#4A90C4'
      };
      ?>
      <div style="width:28px;height:28px;border-radius:7px;background:<?= $aColor2 ?>15;color:<?= $aColor2 ?>;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;">
        <i class="fas <?= $aIcon ?>"></i>
      </div>
      <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <span style="font-size:13px;font-weight:600;color:#0F172A;"><?= $a['accion'] ?></span>
            <span style="font-size:12px;color:#64748B;margin-left:8px;"><?= h($a['tabla']) ?><?= $a['registro_id']?' #'.$a['registro_id']:'' ?></span>
            <?php if($a['descripcion']): ?><div style="font-size:12px;color:#94A3B8;margin-top:1px;"><?= h($a['descripcion']) ?></div><?php endif; ?>
          </div>
          <div style="font-size:11px;color:#94A3B8;flex-shrink:0;text-align:right;">
            <?= tiempoRelativo($a['created_at']) ?>
            <?php if($a['ip']): ?><div><?= h($a['ip']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
// ── Filtrar CIs en modal ───────────────────────────────────
function filtrarCIs(q) {
    const sel  = document.getElementById('selectCI');
    const opts = sel.querySelectorAll('option[data-search]');
    const term = q.toLowerCase().trim();
    opts.forEach(o => {
        o.style.display = !term || o.dataset.search.includes(term) ? '' : 'none';
    });
}

// ── Desasignar equipo ──────────────────────────────────────
function desasignarEquipo(ciId, nombre) {
    if (confirm(`¿Desasignar "${nombre}" de <?= h(addslashes($u['nombre'])) ?>?`)) {
        window.location.href = 'usuario_action.php?accion=desasignar_equipo&ci_id=' + ciId + '&usuario_id=<?= $id ?>';
    }
}
</script>

<?php endLayout(); ?>
