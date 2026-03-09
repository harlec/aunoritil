<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/proveedores/proveedores.php');

$p = DB::row("SELECT * FROM sup_proveedores WHERE id=?", [$id]);
if (!$p) { flash('error','Proveedor no encontrado'); redirect(BASE_URL.'/modules/proveedores/proveedores.php'); }

$contactos = DB::query("SELECT * FROM sup_contactos WHERE proveedor_id=? ORDER BY principal DESC, nombre", [$id]);
$contratos = DB::query(
    "SELECT *, DATEDIFF(fecha_fin,CURDATE()) dias FROM sup_contratos WHERE proveedor_id=? ORDER BY estado='Activo' DESC, fecha_fin ASC",
    [$id]
);
$facturas = DB::query(
    "SELECT *, COALESCE(monto_pen, monto_total) monto_base FROM fin_facturas WHERE proveedor_id=? ORDER BY fecha_emision DESC LIMIT 10",
    [$id]
);
$cis = DB::query("SELECT id, codigo_ci, nombre, tipo_ci, estado FROM cmdb_cis WHERE proveedor_id=? ORDER BY nombre", [$id]);

$stats = DB::row(
    "SELECT COUNT(*) total_contratos,
     SUM(estado='Activo') contratos_activos,
     SUM(COALESCE(monto_mensual,0)) gasto_mensual
     FROM sup_contratos WHERE proveedor_id=?", [$id]
);
$total_facturado = DB::value("SELECT SUM(COALESCE(monto_pen,monto_total)) FROM fin_facturas WHERE proveedor_id=?", [$id]);

$pageTitle  = $p['nombre'];
$pageModule = 'proveedores';

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div style="display:flex;align-items:center;gap:16px;">
    <div style="width:52px;height:52px;border-radius:14px;background:#FBE9E7;color:#E65100;display:flex;align-items:center;justify-content:center;font-size:22px;">
      <i class="fas fa-building"></i>
    </div>
    <div>
      <div style="display:flex;align-items:center;gap:10px;">
        <h1 class="page-title" style="margin:0;"><?= h($p['nombre']) ?></h1>
        <?= badgeEstado($p['estado']) ?>
        <span style="font-size:11px;background:#F1F5F9;color:#475569;padding:3px 8px;border-radius:6px;"><?= h($p['tipo']) ?></span>
      </div>
      <div style="font-size:13px;color:#64748B;margin-top:3px;">
        <?php if($p['ruc']): ?><span class="mono">RUC: <?= h($p['ruc']) ?></span> · <?php endif; ?>
        <?php if($p['pais']): ?><span><?= h($p['pais']) ?></span><?php endif; ?>
        <?php if($p['web']): ?> · <a href="<?= h($p['web']) ?>" target="_blank" style="color:#4A90C4;"><?= h($p['web']) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="proveedor_form.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-pen"></i> Editar</a>
    <a href="proveedores.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="kpi-card"><div class="kpi-icon" style="background:#E3F2FD;color:#1565C0;"><i class="fas fa-file-contract"></i></div><div class="kpi-label">Contratos activos</div><div class="kpi-value" style="color:#1565C0;"><?= (int)($stats['contratos_activos']??0) ?></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-server"></i></div><div class="kpi-label">CIs bajo contrato</div><div class="kpi-value" style="color:#2E7D32;"><?= count($cis) ?></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#FBE9E7;color:#E65100;"><i class="fas fa-calendar-check"></i></div><div class="kpi-label">Gasto mensual</div><div class="kpi-value" style="font-size:22px;color:#E65100;"><?= moneda($stats['gasto_mensual']??0,'PEN') ?></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#E8F4FD;color:#0277BD;"><i class="fas fa-file-invoice-dollar"></i></div><div class="kpi-label">Total facturado</div><div class="kpi-value" style="font-size:22px;color:#0277BD;"><?= moneda($total_facturado??0,'PEN') ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Contratos -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-file-contract" style="color:#1565C0;margin-right:8px;"></i>Contratos</span>
        <a href="contrato_form.php?proveedor=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nuevo</a>
      </div>
      <?php if(empty($contratos)): ?>
      <div class="empty-state"><i class="fas fa-file-contract"></i><p>Sin contratos</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Número</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Vence</th><th>Mensual</th><th></th></tr></thead>
          <tbody>
            <?php foreach($contratos as $ct):
              $d = $ct['dias']; $dc = $d===null?'#94A3B8':($d<0?'#EF5350':($d<=30?'#FF6B00':($d<=90?'#F9A825':'#4CAF50')));
            ?>
            <tr onclick="location.href='contrato_detalle.php?id=<?= $ct['id'] ?>'" style="cursor:pointer;">
              <td><span class="mono" style="font-size:12px;color:#4A90C4;"><?= h($ct['numero']) ?></span></td>
              <td style="font-weight:500;"><?= h(substr($ct['nombre'],0,35)) ?></td>
              <td><span style="font-size:11px;background:#F1F5F9;padding:2px 7px;border-radius:5px;"><?= $ct['tipo'] ?></span></td>
              <td><?= badgeEstado($ct['estado']) ?></td>
              <td><span style="color:<?= $dc ?>;font-size:12px;font-weight:600;"><?= $d===null?'—':($d<0?'Vencido':$d.'d') ?></span></td>
              <td style="font-size:12px;font-weight:600;"><?= $ct['monto_mensual']?moneda($ct['monto_mensual'],$ct['moneda']):'—' ?></td>
              <td onclick="event.stopPropagation()"><a href="contrato_form.php?id=<?= $ct['id'] ?>" class="btn btn-ghost btn-sm btn-icon"><i class="fas fa-pen"></i></a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- CIs relacionados -->
    <?php if(!empty($cis)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-server" style="color:#00695C;margin-right:8px;"></i>Equipos bajo este proveedor</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Estado</th></tr></thead>
          <tbody>
            <?php foreach($cis as $ci): ?>
            <tr onclick="location.href='<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $ci['id'] ?>'" style="cursor:pointer;">
              <td><span class="mono" style="font-size:12px;color:#4A90C4;"><?= h($ci['codigo_ci']) ?></span></td>
              <td style="font-weight:500;"><?= h($ci['nombre']) ?></td>
              <td style="font-size:12px;color:#64748B;"><?= str_replace('_',' ',$ci['tipo_ci']) ?></td>
              <td><?= badgeEstado($ci['estado']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Sidebar contactos -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-address-book" style="color:#4A90C4;margin-right:6px;"></i>Contactos</span>
        <a href="proveedor_form.php?id=<?= $id ?>" class="btn btn-ghost btn-sm"><i class="fas fa-pen"></i></a>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <?php if(empty($contactos)): ?>
        <p style="font-size:13px;color:#94A3B8;text-align:center;padding:12px 0;">Sin contactos</p>
        <?php else: ?>
        <?php foreach($contactos as $ct): ?>
        <div style="padding:10px 0;border-bottom:1px solid #F8FAFC;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
              <div style="font-size:13px;font-weight:600;color:#0F172A;"><?= h($ct['nombre']) ?></div>
              <?php if($ct['cargo']): ?><div style="font-size:11px;color:#94A3B8;"><?= h($ct['cargo']) ?></div><?php endif; ?>
              <div style="font-size:11px;margin-top:2px;">
                <span style="background:#F1F5F9;color:#475569;padding:2px 6px;border-radius:4px;"><?= $ct['tipo'] ?></span>
              </div>
            </div>
            <?php if($ct['principal']): ?><span style="color:#F9A825;font-size:16px;">★</span><?php endif; ?>
          </div>
          <?php if($ct['email']): ?><div style="font-size:12px;color:#4A90C4;margin-top:4px;"><i class="fas fa-envelope" style="margin-right:4px;"></i><?= h($ct['email']) ?></div><?php endif; ?>
          <?php if($ct['telefono']): ?><div style="font-size:12px;color:#64748B;" class="mono"><i class="fas fa-phone" style="margin-right:4px;"></i><?= h($ct['telefono']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if($p['notas']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Notas</span></div>
      <div class="card-body"><p style="font-size:13px;color:#475569;"><?= nl2br(h($p['notas'])) ?></p></div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php endLayout(); ?>
