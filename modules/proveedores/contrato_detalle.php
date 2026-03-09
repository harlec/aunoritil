<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/modules/proveedores/contratos.php');

$c = DB::row(
    "SELECT c.*, p.nombre AS proveedor_nombre, p.tipo AS proveedor_tipo, p.ruc,
     s.nombre AS servicio_nombre
     FROM sup_contratos c
     JOIN sup_proveedores p ON p.id = c.proveedor_id
     LEFT JOIN itsm_catalogo_servicios s ON s.id = c.servicio_id
     WHERE c.id=?", [$id]
);
if (!$c) { flash('error','Contrato no encontrado'); redirect(BASE_URL.'/modules/proveedores/contratos.php'); }

$slas     = DB::query("SELECT * FROM sup_sla_proveedor WHERE contrato_id=?", [$id]);
$facturas = DB::query(
    "SELECT f.*, COALESCE(f.monto_pen, f.monto_total) AS monto_base
     FROM fin_facturas f WHERE f.contrato_id=? ORDER BY f.fecha_emision DESC LIMIT 12",
    [$id]
);
$contactos = DB::query(
    "SELECT ct.* FROM sup_contactos ct
     JOIN sup_proveedores p ON p.id = ct.proveedor_id
     WHERE p.id = ? ORDER BY ct.principal DESC",
    [$c['proveedor_id']]
);

$pageTitle  = $c['numero'] . ' — ' . $c['nombre'];
$pageModule = 'proveedores';

$dias   = $c['fecha_fin'] ? (int)((strtotime($c['fecha_fin']) - time()) / 86400) : null;
$dColor = $dias === null ? '#94A3B8' : ($dias < 0 ? '#EF5350' : ($dias <= 30 ? '#EF5350' : ($dias <= 90 ? '#F9A825' : '#4CAF50')));

$totalFacturado = array_sum(array_column($facturas, 'monto_base'));

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <h1 class="page-title"><?= h($c['nombre']) ?></h1>
      <?= badgeEstado($c['estado']) ?>
    </div>
    <div style="font-size:13px;color:#64748B;margin-top:4px;display:flex;gap:12px;">
      <span class="mono" style="color:#4A90C4;font-weight:600;"><?= h($c['numero']) ?></span>
      <span>·</span>
      <span><?= h($c['proveedor_nombre']) ?></span>
      <span>·</span>
      <span><?= $c['tipo'] ?></span>
    </div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="contrato_form.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-pen"></i> Editar</a>
    <a href="contratos.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

  <!-- Principal -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Fechas y montos -->
    <div class="card">
      <div class="card-body" style="padding:20px;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;text-align:center;">
          <?php
          $items = [
            ['Inicio',      fechaHumana($c['fecha_inicio']), '#4A90C4'],
            ['Vencimiento', fechaHumana($c['fecha_fin']),    $dColor],
            ['Monto mensual', $c['monto_mensual'] ? moneda($c['monto_mensual'],$c['moneda']) : '—', '#2E7D32'],
            ['Total facturado', moneda($totalFacturado, 'PEN'), '#1A3A5C'],
          ];
          foreach($items as [$l,$v,$col]): ?>
          <div style="padding:16px;background:#F8FAFC;border-radius:12px;">
            <div style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:6px;"><?= $l ?></div>
            <div style="font-size:17px;font-weight:800;color:<?= $col ?>;"><?= $v ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if($dias !== null): ?>
        <div style="margin-top:16px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;">
            <span style="color:#64748B;">Tiempo transcurrido del contrato</span>
            <span style="font-weight:600;color:<?= $dColor ?>;">
              <?= $dias < 0 ? 'Vencido hace '.abs($dias).' días' : 'Vence en '.$dias.' días' ?>
            </span>
          </div>
          <?php if($c['fecha_inicio'] && $c['fecha_fin']):
            $total_dias = max(1,(strtotime($c['fecha_fin'])-strtotime($c['fecha_inicio']))/86400);
            $trans = max(0,min(100,round((time()-strtotime($c['fecha_inicio']))/$total_dias/86400*100)));
          ?>
          <div style="height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden;">
            <div style="height:8px;background:<?= $dColor ?>;border-radius:4px;width:<?= $trans ?>%;transition:width .3s;"></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- SLAs -->
    <?php if(!empty($slas)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-gauge-high" style="color:#00695C;margin-right:8px;"></i>Métricas SLA</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Métrica</th><th>Valor comprometido</th><th>Período</th></tr></thead>
          <tbody>
            <?php foreach($slas as $s): ?>
            <tr>
              <td style="font-weight:600;"><?= h($s['nombre_metrica']) ?></td>
              <td>
                <span style="font-size:18px;font-weight:800;color:#1A3A5C;"><?= h($s['valor_comprometido']) ?></span>
                <span style="font-size:12px;color:#64748B;margin-left:4px;"><?= h($s['unidad']) ?></span>
              </td>
              <td><span style="font-size:12px;background:#F1F5F9;padding:3px 8px;border-radius:6px;"><?= $s['periodo_medicion'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Facturas recientes -->
    <?php if(!empty($facturas)): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-file-invoice" style="color:#558B2F;margin-right:8px;"></i>Facturas de este contrato</span>
        <a href="<?= BASE_URL ?>/modules/finanzas/facturas.php?contrato=<?= $id ?>" class="btn btn-ghost btn-sm">Ver todas</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Documento</th><th>Período</th><th>Monto</th><th>Estado</th><th>Vencimiento</th></tr></thead>
          <tbody>
            <?php foreach($facturas as $f): ?>
            <tr onclick="location.href='<?= BASE_URL ?>/modules/finanzas/facturas.php?id=<?= $f['id'] ?>'" style="cursor:pointer;">
              <td><span class="mono" style="font-size:12px;"><?= h($f['numero_documento']) ?></span></td>
              <td style="font-size:12px;color:#64748B;"><?= h($f['periodo']??'') ?></td>
              <td style="font-weight:600;"><?= moneda($f['monto_total'],$f['moneda']) ?></td>
              <td><?= badgeEstado($f['estado']) ?></td>
              <td style="font-size:12px;color:#64748B;"><?= fechaHumana($f['fecha_vencimiento']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Proveedor info -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-building" style="color:#E65100;margin-right:6px;"></i>Proveedor</span></div>
      <div class="card-body">
        <div style="font-weight:700;font-size:14px;color:#0F172A;margin-bottom:4px;"><?= h($c['proveedor_nombre']) ?></div>
        <?php if($c['ruc']): ?><div style="font-size:12px;color:#64748B;" class="mono">RUC: <?= h($c['ruc']) ?></div><?php endif; ?>
        <a href="proveedor_detalle.php?id=<?= $c['proveedor_id'] ?>" class="btn btn-ghost btn-sm" style="margin-top:10px;width:100%;"><i class="fas fa-eye"></i> Ver proveedor</a>
      </div>
    </div>

    <!-- Contactos -->
    <?php if(!empty($contactos)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-address-book" style="color:#4A90C4;margin-right:6px;"></i>Contactos</span></div>
      <div class="card-body" style="padding:12px 16px;">
        <?php foreach($contactos as $ct): ?>
        <div style="padding:8px 0;border-bottom:1px solid #F8FAFC;">
          <div style="display:flex;justify-content:space-between;">
            <span style="font-size:13px;font-weight:600;"><?= h($ct['nombre']) ?></span>
            <?php if($ct['principal']): ?><span style="color:#F9A825;font-size:14px;">★</span><?php endif; ?>
          </div>
          <div style="font-size:11px;color:#94A3B8;"><?= h($ct['tipo']) ?><?php if($ct['cargo']): ?> · <?= h($ct['cargo']) ?><?php endif; ?></div>
          <?php if($ct['email']): ?><div style="font-size:12px;color:#4A90C4;margin-top:2px;"><?= h($ct['email']) ?></div><?php endif; ?>
          <?php if($ct['telefono']): ?><div style="font-size:12px;color:#64748B;" class="mono"><?= h($ct['telefono']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Descripción y notas -->
    <?php if($c['descripcion']||$c['notas']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Notas</span></div>
      <div class="card-body">
        <?php if($c['descripcion']): ?><p style="font-size:13px;color:#475569;margin-bottom:8px;"><?= nl2br(h($c['descripcion'])) ?></p><?php endif; ?>
        <?php if($c['notas']): ?><p style="font-size:13px;color:#64748B;"><?= nl2br(h($c['notas'])) ?></p><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Acciones -->
    <div class="card">
      <div class="card-body" style="padding:14px 16px;display:flex;flex-direction:column;gap:8px;">
        <a href="<?= BASE_URL ?>/modules/finanzas/factura_form.php?contrato=<?= $id ?>&proveedor=<?= $c['proveedor_id'] ?>" class="btn btn-ghost" style="width:100%;">
          <i class="fas fa-plus"></i> Registrar factura
        </a>
        <button onclick="confirmar('¿Eliminar este contrato?','contrato_action.php?accion=eliminar&id=<?= $id ?>')"
                class="btn btn-ghost" style="width:100%;color:#EF5350;">
          <i class="fas fa-trash"></i> Eliminar contrato
        </button>
      </div>
    </div>

  </div>
</div>

<?php endLayout(); ?>
