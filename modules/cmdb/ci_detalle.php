<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/cmdb/cis.php');

$ci = DB::row(
    "SELECT c.*,
     u.nombre  AS ubicacion_nombre,
     us.nombre AS propietario_nombre, us.email AS propietario_email,
     ag.nombre AS responsable_nombre, ag.email  AS responsable_email,
     p.nombre  AS proveedor_nombre,
     ct.numero AS contrato_numero, ct.nombre AS contrato_nombre,
     ct.fecha_fin AS contrato_fin,
     DATEDIFF(c.fecha_garantia_fin, CURDATE()) AS dias_garantia
     FROM cmdb_cis c
     LEFT JOIN cmdb_ubicaciones u  ON u.id  = c.ubicacion_id
     LEFT JOIN adm_usuarios us     ON us.id = c.propietario_id
     LEFT JOIN adm_usuarios ag     ON ag.id = c.responsable_ti
     LEFT JOIN sup_proveedores p   ON p.id  = c.proveedor_id
     LEFT JOIN sup_contratos ct    ON ct.id = c.contrato_id
     WHERE c.id = ?", [$id]
);
if (!$ci) { flash('error','CI no encontrado'); redirect(BASE_URL.'/modules/cmdb/cis.php'); }

// ── Datos relacionados ────────────────────────────────────────
$relaciones = DB::query(
    "SELECT r.*, ci.nombre AS ci_nombre, ci.codigo_ci, ci.estado AS ci_estado, ci.tipo_ci,
            ci2.nombre AS ci_origen_nombre
     FROM cmdb_relaciones r
     JOIN cmdb_cis ci  ON ci.id  = r.ci_destino_id
     JOIN cmdb_cis ci2 ON ci2.id = r.ci_origen_id
     WHERE r.ci_origen_id = ? OR r.ci_destino_id = ?",
    [$id, $id]
);

$garantias = DB::query(
    "SELECT * FROM cmdb_garantias WHERE ci_id=? ORDER BY fecha_fin ASC", [$id]
);

$historial = DB::query(
    "SELECT h.*, u.nombre AS usuario_nombre
     FROM cmdb_historial_ci h
     LEFT JOIN adm_usuarios u ON u.id = h.usuario_id
     WHERE h.ci_id=? ORDER BY h.created_at DESC LIMIT 20",
    [$id]
);

$tickets = DB::query(
    "SELECT t.numero, t.titulo, t.estado, t.prioridad, t.tipo, t.fecha_apertura
     FROM itsm_tickets t
     WHERE t.ci_id=? ORDER BY t.fecha_apertura DESC LIMIT 10",
    [$id]
);

$accesorios = json_decode($ci['accesorios'] ?? '{}', true) ?: [];
$tab = clean($_GET['tab'] ?? 'info');

$pageTitle  = $ci['nombre'];
$pageModule = 'cmdb';

// Colores criticidad
$critColors = ['Critico'=>['#FFEBEE','#EF5350'],'Alto'=>['#FFF3E0','#E65100'],'Medio'=>['#FFF9C4','#F57F17'],'Bajo'=>['#E8F5E9','#2E7D32']];
[$cbg,$cc] = $critColors[$ci['criticidad']] ?? ['#F5F5F5','#616161'];
$monColor = match($ci['ultimo_estado_mon']) {
    'Up'=>'#4CAF50','Down'=>'#EF5350','Degradado'=>'#FF6B00',default=>'#94A3B8'
};
$diasGar  = $ci['dias_garantia'];
$garColor = $diasGar === null ? '#94A3B8' : ($diasGar < 0 ? '#EF5350' : ($diasGar <= 30 ? '#FF6B00' : ($diasGar <= 90 ? '#F9A825' : '#4CAF50')));

require_once __DIR__ . '/../../includes/layout.php';
?>

<!-- ── Header ───────────────────────────────────────────────── -->
<div class="page-header">
  <div style="display:flex;align-items:center;gap:16px;">
    <div style="width:52px;height:52px;border-radius:14px;background:#E0F2F1;color:#00695C;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
      <?php
      $catIcons=['Hardware'=>'fa-server','Software'=>'fa-code','Red'=>'fa-network-wired','Telefonia'=>'fa-phone','ITS'=>'fa-road'];
      echo '<i class="fas '.($catIcons[$ci['categoria']]??'fa-cube').'"></i>';
      ?>
    </div>
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <h1 class="page-title" style="margin:0;"><?= h($ci['nombre']) ?></h1>
        <?= badgeEstado($ci['estado']) ?>
        <span class="badge" style="background:<?= $cbg ?>;color:<?= $cc ?>;border:1px solid <?= $cc ?>30;"><?= $ci['criticidad'] ?></span>
        <?php if($ci['monitor_id']): ?>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:<?= $monColor ?>;">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= $monColor ?>;"></span>
          <?= $ci['ultimo_estado_mon'] ?>
        </span>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:4px;font-size:13px;color:#64748B;">
        <span class="mono" style="color:#4A90C4;font-weight:600;"><?= h($ci['codigo_ci']) ?></span>
        <span>·</span>
        <span><?= h(str_replace('_',' ',$ci['tipo_ci'])) ?></span>
        <?php if($ci['ubicacion_nombre']): ?>
        <span>·</span>
        <span><i class="fas fa-map-pin" style="font-size:10px;"></i> <?= h($ci['ubicacion_nombre']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= BASE_URL ?>/modules/cmdb/ci_form.php?id=<?= $id ?>" class="btn btn-primary">
      <i class="fas fa-pen"></i> Editar
    </a>
    <a href="<?= BASE_URL ?>/modules/cmdb/cis.php" class="btn btn-ghost">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
  </div>
</div>

<!-- ── Tabs ──────────────────────────────────────────────────── -->
<div style="display:flex;gap:2px;margin-bottom:20px;background:white;border:1px solid #EEF2F7;border-radius:12px;padding:4px;width:fit-content;">
  <?php
  $tabs = [
    'info'      => ['fa-circle-info',  'Información'],
    'relaciones'=> ['fa-diagram-project','Relaciones ('.count($relaciones).')'],
    'garantias' => ['fa-shield-halved','Garantías ('.count($garantias).')'],
    'tickets'   => ['fa-ticket',       'Tickets ('.count($tickets).')'],
    'historial' => ['fa-clock-rotate-left','Historial'],
  ];
  foreach($tabs as $key=>[$icon,$label]):
    $active = $tab === $key;
  ?>
  <a href="?id=<?= $id ?>&tab=<?= $key ?>"
     style="display:flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;font-size:13px;font-weight:<?= $active?'600':'500' ?>;text-decoration:none;
            background:<?= $active?'#1A3A5C':'transparent' ?>;color:<?= $active?'white':'#64748B' ?>;transition:all .15s;"
     onmouseenter="if(this.style.background==='transparent')this.style.background='#F8FAFC'"
     onmouseleave="if('<?= $key ?>'!=='<?= $tab ?>')this.style.background='transparent'">
    <i class="fas <?= $icon ?>"></i><?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ════════════════════════ TAB INFO ════════════════════════ -->
<?php if($tab==='info'): ?>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Datos técnicos -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-microchip" style="color:#4A90C4;mr:8px;margin-right:8px;"></i>Datos Técnicos</span></div>
      <div class="card-body">
        <?php
        $campos = [
          ['Marca',         $ci['marca']],
          ['Modelo',        $ci['modelo']],
          ['Número de Serie',$ci['numero_serie']],
          ['Número de Parte',$ci['numero_parte']],
          ['Activo Fijo',   $ci['numero_activo']],
          ['Sistema Operativo',$ci['version_so']],
          ['Firmware',      $ci['version_firmware']],
          ['Procesador',    $ci['procesador']],
          ['RAM',           $ci['ram_gb'] ? $ci['ram_gb'].' GB' : null],
          ['Almacenamiento',$ci['disco_gb'] ? $ci['disco_gb'].' GB' : null],
        ];
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:x;row-gap:0;">
          <?php foreach($campos as [$label,$val]):
            if(!$val) continue; ?>
          <div style="padding:10px 0;border-bottom:1px solid #F8FAFC;">
            <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.04em;"><?= $label ?></div>
            <div style="font-size:13px;color:#0F172A;margin-top:2px;"><?= h($val) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if(!empty(array_filter($accesorios))): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid #F1F5F9;">
          <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">Accesorios</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach($accesorios as $k=>$v): if(!$v) continue; ?>
            <span style="background:#E8F5E9;color:#2E7D32;font-size:12px;font-weight:600;padding:4px 10px;border-radius:6px;">
              <i class="fas fa-check" style="font-size:10px;margin-right:4px;"></i><?= ucfirst($k) ?>
            </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Red -->
    <?php if($ci['ip_address']||$ci['mac_address']||$ci['hostname']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-network-wired" style="color:#00695C;margin-right:8px;"></i>Red</span></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:x;row-gap:0;">
          <?php foreach([['IP Address',$ci['ip_address']],['Hostname',$ci['hostname']],['MAC LAN',$ci['mac_address']],['MAC WiFi',$ci['mac_wifi']]] as [$l,$v]):
            if(!$v) continue; ?>
          <div style="padding:10px 0;border-bottom:1px solid #F8FAFC;">
            <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.04em;"><?= $l ?></div>
            <div class="mono" style="font-size:13px;color:#0F172A;margin-top:2px;"><?= h($v) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Monitoreo -->
    <?php if($ci['monitor_id']): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-chart-line" style="color:<?= $monColor ?>;margin-right:8px;"></i>Monitoreo</span>
        <span style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:<?= $monColor ?>;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $monColor ?>;<?= $ci['ultimo_estado_mon']==='Up'?'box-shadow:0 0 0 4px '.$monColor.'30;':'' ?>"></span>
          <?= $ci['ultimo_estado_mon'] ?>
        </span>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;row-gap:0;">
          <?php foreach([['ID Monitor',$ci['monitor_id']],['IP Monitoreada',$ci['monitor_ip']],['Último check',fechaHumana($ci['ultimo_check_mon'],true)]] as [$l,$v]):
            if(!$v) continue; ?>
          <div style="padding:10px 0;border-bottom:1px solid #F8FAFC;">
            <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.04em;"><?= $l ?></div>
            <div class="mono" style="font-size:13px;margin-top:2px;"><?= h($v) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if($ci['notas']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-note-sticky" style="color:#94A3B8;margin-right:8px;"></i>Notas</span></div>
      <div class="card-body"><p style="font-size:13px;color:#475569;line-height:1.7;"><?= nl2br(h($ci['notas'])) ?></p></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar info -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Estado garantía -->
    <div class="card" style="border:2px solid <?= $garColor ?>30;">
      <div class="card-body" style="padding:20px;text-align:center;">
        <div style="font-size:32px;color:<?= $garColor ?>;margin-bottom:8px;">
          <i class="fas <?= $diasGar===null?'fa-shield':'fa-shield-halved' ?>"></i>
        </div>
        <div style="font-size:12px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Garantía</div>
        <?php if($ci['fecha_garantia_fin']): ?>
        <div style="font-size:22px;font-weight:800;color:<?= $garColor ?>;">
          <?php if($diasGar < 0): ?>Vencida
          elseif($diasGar === 0): ?>Vence hoy
          <?php else: ?><?= $diasGar ?> días
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#64748B;margin-top:4px;"><?= fechaHumana($ci['fecha_garantia_fin']) ?></div>
        <?php else: ?>
        <div style="font-size:14px;color:#94A3B8;">No registrada</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Asignación -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-users" style="color:#6A1B9A;margin-right:6px;"></i>Asignación</span></div>
      <div class="card-body">
        <?php if($ci['propietario_nombre']): ?>
        <div style="margin-bottom:12px;">
          <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;margin-bottom:4px;">Usuario asignado</div>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:#E8EAF6;color:#3949AB;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
              <?= strtoupper(substr($ci['propietario_nombre'],0,2)) ?>
            </div>
            <div>
              <div style="font-size:13px;font-weight:600;color:#0F172A;"><?= h($ci['propietario_nombre']) ?></div>
              <div style="font-size:11px;color:#94A3B8;"><?= h($ci['propietario_email']) ?></div>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <?php if($ci['responsable_nombre']): ?>
        <div>
          <div style="font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;margin-bottom:4px;">Responsable TI</div>
          <div style="font-size:13px;color:#0F172A;"><?= h($ci['responsable_nombre']) ?></div>
        </div>
        <?php endif; ?>
        <?php if(!$ci['propietario_nombre']&&!$ci['responsable_nombre']): ?>
        <p style="font-size:13px;color:#94A3B8;">Sin asignación</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Proveedor y contrato -->
    <?php if($ci['proveedor_nombre']||$ci['contrato_numero']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-handshake" style="color:#E65100;margin-right:6px;"></i>Proveedor</span></div>
      <div class="card-body">
        <?php if($ci['proveedor_nombre']): ?>
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:8px;"><?= h($ci['proveedor_nombre']) ?></div>
        <?php endif; ?>
        <?php if($ci['contrato_numero']): ?>
        <div style="font-size:11px;color:#94A3B8;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Contrato</div>
        <div style="font-size:13px;color:#0F172A;"><?= h($ci['contrato_numero']) ?></div>
        <div style="font-size:12px;color:#64748B;"><?= h(substr($ci['contrato_nombre']??'',0,40)) ?></div>
        <?php if($ci['contrato_fin']): ?>
        <div style="font-size:11px;color:#94A3B8;margin-top:4px;">Vence: <?= fechaHumana($ci['contrato_fin']) ?></div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Fechas y costo -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-receipt" style="color:#558B2F;margin-right:6px;"></i>Adquisición</span></div>
      <div class="card-body">
        <?php foreach([
          ['Fecha compra', fechaHumana($ci['fecha_compra'])],
          ['Tipo', $ci['tipo_adquisicion']],
          ['Costo', $ci['costo_adquisicion'] ? moneda($ci['costo_adquisicion'], $ci['moneda_adquisicion']).' '.$ci['moneda_adquisicion'] : null],
          ['Registrado', fechaHumana($ci['created_at'], true)],
        ] as [$l,$v]): if(!$v||$v==='—') continue; ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #F8FAFC;font-size:13px;">
          <span style="color:#64748B;"><?= $l ?></span>
          <span style="font-weight:500;color:#0F172A;"><?= h($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<!-- ════════════════════ TAB RELACIONES ════════════════════ -->
<?php elseif($tab==='relaciones'): ?>
<div style="display:flex;justify-content:space-between;margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:700;color:#0F172A;">Relaciones con otros CIs</h3>
  <button class="btn btn-primary btn-sm" onclick="openModal('modalRelacion')">
    <i class="fas fa-plus"></i> Agregar relación
  </button>
</div>
<?php if(empty($relaciones)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-diagram-project"></i><p>Sin relaciones registradas</p></div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Dirección</th><th>CI</th><th>Código</th><th>Tipo CI</th><th>Estado</th><th>Tipo relación</th><th>Descripción</th><th></th></tr></thead>
      <tbody>
        <?php foreach($relaciones as $r):
          $esSaliente = $r['ci_origen_id'] == $id;
          $ciNombre   = $esSaliente ? $r['ci_nombre'] : $r['ci_origen_nombre'];
          $ciId2      = $esSaliente ? $r['ci_destino_id'] : $r['ci_origen_id'];
        ?>
        <tr>
          <td>
            <span style="font-size:11px;background:<?= $esSaliente?'#E3F2FD':'#E8F5E9' ?>;color:<?= $esSaliente?'#1565C0':'#2E7D32' ?>;padding:3px 8px;border-radius:6px;font-weight:600;">
              <?= $esSaliente ? '→ Depende de' : '← Dependiente' ?>
            </span>
          </td>
          <td><a href="?id=<?= $ciId2 ?>&tab=info" style="color:#4A90C4;font-weight:600;"><?= h($ciNombre) ?></a></td>
          <td><span class="mono" style="font-size:12px;"><?= h($r['codigo_ci']) ?></span></td>
          <td style="font-size:12px;color:#64748B;"><?= str_replace('_',' ',$r['tipo_ci']) ?></td>
          <td><?= badgeEstado($r['ci_estado']) ?></td>
          <td><span style="font-size:12px;background:#F1F5F9;padding:3px 8px;border-radius:6px;"><?= str_replace('_',' ',$r['tipo_relacion']) ?></span></td>
          <td style="font-size:12px;color:#64748B;"><?= h($r['descripcion']??'') ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/cmdb/ci_action.php?accion=del_relacion&id=<?= $r['id'] ?>&ci=<?= $id ?>"
               onclick="return confirm('¿Eliminar esta relación?')"
               style="color:#EF5350;font-size:12px;"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Modal agregar relación -->
<div class="modal-overlay" id="modalRelacion">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">Agregar Relación</span>
      <button class="modal-close" onclick="closeModal('modalRelacion')"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/modules/cmdb/ci_action.php">
      <div class="modal-body">
        <?= Auth::csrfInput() ?>
        <input type="hidden" name="accion" value="agregar_relacion">
        <input type="hidden" name="ci_origen_id" value="<?= $id ?>">
        <input type="hidden" name="redirect" value="<?= $id ?>">
        <div class="form-group">
          <label class="form-label">Este CI <span style="font-size:11px;color:#94A3B8;">(<?= h($ci['nombre']) ?>)</span></label>
          <select name="tipo_relacion" class="form-control">
            <?php foreach(['Depende_de','Contiene','Conectado_a','Respaldado_por','Virtualizado_en','Licenciado_en'] as $tr): ?>
            <option value="<?= $tr ?>"><?= str_replace('_',' ',$tr) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">CI destino <span class="req">*</span></label>
          <select name="ci_destino_id" class="form-control" required>
            <option value="">Seleccionar CI...</option>
            <?php
            $otrosCIs = DB::query("SELECT id, codigo_ci, nombre FROM cmdb_cis WHERE id!=? ORDER BY nombre", [$id]);
            foreach($otrosCIs as $oc): ?>
            <option value="<?= $oc['id'] ?>"><?= h($oc['codigo_ci']) ?> — <?= h($oc['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <input type="text" name="descripcion" class="form-control" placeholder="Describe la relación...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalRelacion')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-link"></i> Agregar</button>
      </div>
    </form>
  </div>
</div>

<!-- ════════════════════ TAB GARANTÍAS ════════════════════ -->
<?php elseif($tab==='garantias'): ?>
<div style="display:flex;justify-content:space-between;margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:700;color:#0F172A;">Garantías y Contratos de Soporte</h3>
  <a href="<?= BASE_URL ?>/modules/cmdb/garantia_form.php?ci_id=<?= $id ?>" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> Agregar garantía
  </a>
</div>
<?php if(empty($garantias)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-shield-halved"></i><p>Sin garantías registradas</p></div></div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
  <?php foreach($garantias as $g):
    $dg = (int)((strtotime($g['fecha_fin']) - time()) / 86400);
    $gc = $dg < 0 ? '#EF5350' : ($dg <= 30 ? '#FF6B00' : ($dg <= 90 ? '#F9A825' : '#4CAF50'));
  ?>
  <div class="card" style="border-left:4px solid <?= $gc ?>;">
    <div class="card-body" style="padding:16px 20px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
        <div>
          <div style="font-weight:700;color:#0F172A;font-size:14px;"><?= h($g['tipo']) ?></div>
          <?php if($g['proveedor_texto']): ?>
          <div style="font-size:13px;color:#64748B;"><?= h($g['proveedor_texto']) ?></div>
          <?php endif; ?>
          <?php if($g['cobertura']): ?>
          <div style="font-size:12px;color:#94A3B8;margin-top:4px;"><?= h($g['cobertura']) ?></div>
          <?php endif; ?>
          <div style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:#64748B;">
            <span><i class="fas fa-calendar-check" style="margin-right:4px;"></i>Inicio: <?= fechaHumana($g['fecha_inicio']) ?></span>
            <span><i class="fas fa-calendar-xmark" style="margin-right:4px;"></i>Fin: <?= fechaHumana($g['fecha_fin']) ?></span>
            <?php if($g['sla_respuesta_hrs']): ?>
            <span><i class="fas fa-clock" style="margin-right:4px;"></i>SLA: <?= $g['sla_respuesta_hrs'] ?>h</span>
            <?php endif; ?>
          </div>
          <?php if($g['contacto_soporte']||$g['telefono_soporte']||$g['email_soporte']): ?>
          <div style="margin-top:8px;padding:8px 12px;background:#F8FAFC;border-radius:8px;font-size:12px;color:#475569;">
            <i class="fas fa-headset" style="margin-right:6px;color:#4A90C4;"></i>
            <?= h($g['contacto_soporte']) ?>
            <?php if($g['telefono_soporte']): ?> · <?= h($g['telefono_soporte']) ?><?php endif; ?>
            <?php if($g['email_soporte']): ?> · <?= h($g['email_soporte']) ?><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="text-align:right;">
          <div style="font-size:24px;font-weight:800;color:<?= $gc ?>;">
            <?= abs($dg) ?>d
          </div>
          <div style="font-size:11px;color:<?= $gc ?>;font-weight:600;">
            <?= $dg < 0 ? 'vencida' : 'restantes' ?>
          </div>
          <a href="<?= BASE_URL ?>/modules/cmdb/garantia_form.php?id=<?= $g['id'] ?>&ci_id=<?= $id ?>"
             class="btn btn-ghost btn-sm" style="margin-top:8px;"><i class="fas fa-pen"></i></a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════════════ TAB TICKETS ════════════════════ -->
<?php elseif($tab==='tickets'): ?>
<div style="display:flex;justify-content:space-between;margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:700;color:#0F172A;">Tickets relacionados a este CI</h3>
  <a href="<?= BASE_URL ?>/modules/itsm/tickets.php?ci_id=<?= $id ?>" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> Nuevo ticket para este CI
  </a>
</div>
<?php if(empty($tickets)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-ticket"></i><p>Sin tickets para este CI</p></div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Número</th><th>Tipo</th><th>Asunto</th><th>Prioridad</th><th>Estado</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach($tickets as $t): ?>
        <tr onclick="location.href='<?= BASE_URL ?>/modules/itsm/tickets.php?id=<?= $t['id'] ?? '' ?>'" style="cursor:pointer;">
          <td><span class="mono" style="font-size:12px;color:#4A90C4;font-weight:600;"><?= h($t['numero']) ?></span></td>
          <td><?= badgeTipo($t['tipo']) ?></td>
          <td style="font-size:13px;font-weight:500;"><?= h($t['titulo']) ?></td>
          <td><?= badgePrioridad($t['prioridad']) ?></td>
          <td><?= badgeEstado($t['estado']) ?></td>
          <td style="font-size:12px;color:#64748B;"><?= tiempoRelativo($t['fecha_apertura']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ════════════════════ TAB HISTORIAL ════════════════════ -->
<?php elseif($tab==='historial'): ?>
<div class="card">
  <div class="card-header"><span class="card-title"><i class="fas fa-clock-rotate-left" style="color:#6A1B9A;margin-right:8px;"></i>Historial de cambios</span></div>
  <?php if(empty($historial)): ?>
  <div class="empty-state"><i class="fas fa-clock"></i><p>Sin historial registrado</p></div>
  <?php else: ?>
  <div style="padding:8px 20px;">
    <?php foreach($historial as $h): ?>
    <div style="display:flex;gap:14px;padding:12px 0;border-bottom:1px solid #F8FAFC;">
      <div style="width:8px;height:8px;border-radius:50%;background:#4A90C4;margin-top:5px;flex-shrink:0;"></div>
      <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
          <div>
            <span style="font-size:13px;font-weight:600;color:#0F172A;"><?= h($h['tipo_cambio']) ?></span>
            <?php if($h['valor_anterior']&&$h['valor_nuevo']): ?>
            <span style="font-size:12px;color:#94A3B8;margin-left:8px;">
              <span style="text-decoration:line-through;"><?= h($h['valor_anterior']) ?></span>
              → <strong style="color:#0F172A;"><?= h($h['valor_nuevo']) ?></strong>
            </span>
            <?php endif; ?>
            <?php if($h['descripcion']): ?>
            <div style="font-size:12px;color:#64748B;margin-top:2px;"><?= h($h['descripcion']) ?></div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:11px;color:#94A3B8;"><?= tiempoRelativo($h['created_at']) ?></div>
            <?php if($h['usuario_nombre']): ?>
            <div style="font-size:11px;color:#64748B;"><?= h($h['usuario_nombre']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php endLayout(); ?>
