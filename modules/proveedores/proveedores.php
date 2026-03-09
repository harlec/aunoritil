<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$pageTitle  = 'Proveedores';
$pageModule = 'proveedores';

$buscar  = clean($_GET['q']    ?? '');
$filTipo = clean($_GET['tipo'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($buscar)  { $where[] = '(p.nombre LIKE ? OR p.ruc LIKE ? OR p.web LIKE ?)'; $like = "%{$buscar}%"; $params = array_merge($params,[$like,$like,$like]); }
if ($filTipo) { $where[] = 'p.tipo = ?'; $params[] = $filTipo; }
$where[] = 'p.activo = 1';

$sql = "SELECT p.*,
        COUNT(DISTINCT c.id)  AS total_contratos,
        COUNT(DISTINCT f.id)  AS total_facturas,
        SUM(CASE WHEN c.estado='Activo' AND c.fecha_fin >= CURDATE() THEN 1 ELSE 0 END) AS contratos_activos,
        SUM(CASE WHEN c.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS contratos_vencen
        FROM sup_proveedores p
        LEFT JOIN sup_contratos c ON c.proveedor_id = p.id
        LEFT JOIN fin_facturas f  ON f.proveedor_id = p.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p.id
        ORDER BY p.nombre ASC";

$resultado   = DB::paginate($sql, $params, $page, 20);
$proveedores = $resultado['data'];

// KPIs globales
$kpis = [
    'total'          => DB::value("SELECT COUNT(*) FROM sup_proveedores WHERE activo=1"),
    'contratos_act'  => DB::value("SELECT COUNT(*) FROM sup_contratos WHERE estado='Activo' AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())"),
    'contratos_venc' => DB::value("SELECT COUNT(*) FROM sup_contratos WHERE fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND estado='Activo'"),
    'facturas_pend'  => DB::value("SELECT COUNT(*) FROM fin_facturas WHERE estado='Pendiente'"),
];

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-handshake" style="color:#E65100;margin-right:10px;"></i>Proveedores</h1>
    <p class="page-subtitle">Directorio de proveedores y partners TI</p>
  </div>
  <a href="<?= BASE_URL ?>/modules/proveedores/proveedor_form.php" class="btn btn-primary">
    <i class="fas fa-plus"></i> Nuevo proveedor
  </a>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#FBE9E7;color:#E65100;"><i class="fas fa-handshake"></i></div>
    <div class="kpi-label">Proveedores activos</div>
    <div class="kpi-value" style="color:#E65100;"><?= numero($kpis['total']) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='contratos.php'">
    <div class="kpi-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-file-contract"></i></div>
    <div class="kpi-label">Contratos activos</div>
    <div class="kpi-value" style="color:#2E7D32;"><?= numero($kpis['contratos_act']) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='contratos.php?alerta=30'">
    <div class="kpi-icon" style="background:<?= $kpis['contratos_venc']>0?'#FFEBEE':'#E8F5E9' ?>;color:<?= $kpis['contratos_venc']>0?'#EF5350':'#2E7D32' ?>;"><i class="fas fa-calendar-xmark"></i></div>
    <div class="kpi-label">Contratos vencen (30d)</div>
    <div class="kpi-value" style="color:<?= $kpis['contratos_venc']>0?'#EF5350':'#2E7D32' ?>;"><?= $kpis['contratos_venc'] ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='<?= BASE_URL ?>/modules/finanzas/facturas.php?estado=Pendiente'">
    <div class="kpi-icon" style="background:<?= $kpis['facturas_pend']>0?'#FFF3E0':'#F5F5F5' ?>;color:<?= $kpis['facturas_pend']>0?'#E65100':'#616161' ?>;"><i class="fas fa-file-invoice-dollar"></i></div>
    <div class="kpi-label">Facturas pendientes</div>
    <div class="kpi-value"><?= $kpis['facturas_pend'] ?></div>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:14px 16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:2;min-width:200px;">
        <div style="position:relative;">
          <i class="fas fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;"></i>
          <input type="text" name="q" value="<?= h($buscar) ?>" placeholder="Nombre, RUC, web..." class="form-control" style="padding-left:34px;">
        </div>
      </div>
      <div style="min-width:160px;">
        <select name="tipo" class="form-control" onchange="this.form.submit()">
          <option value="">Todos los tipos</option>
          <?php foreach(['Fabricante','Distribuidor','ISP','Soporte','SaaS','Consultor','Otro'] as $t): ?>
          <option value="<?= $t ?>" <?= $filTipo===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-magnifying-glass"></i></button>
        <?php if($buscar||$filTipo): ?><a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-xmark"></i></a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Grid de proveedores -->
<?php if(empty($proveedores)): ?>
<div class="card"><div class="empty-state"><i class="fas fa-handshake"></i><p>No se encontraron proveedores</p></div></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;">
  <?php foreach($proveedores as $p):
    $tipoColors=['Fabricante'=>['#E3F2FD','#1565C0'],'ISP'=>['#E0F2F1','#00695C'],'SaaS'=>['#F3E5F5','#6A1B9A'],'Soporte'=>['#FBE9E7','#E65100'],'Distribuidor'=>['#FFF3E0','#E65100'],'Consultor'=>['#E8F5E9','#2E7D32'],'Otro'=>['#F5F5F5','#616161']];
    [$tbg,$tc] = $tipoColors[$p['tipo']] ?? ['#F5F5F5','#616161'];
  ?>
  <div class="card" style="cursor:pointer;transition:box-shadow .15s;"
       onmouseenter="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
       onmouseleave="this.style.boxShadow=''"
       onclick="location.href='proveedor_detalle.php?id=<?= $p['id'] ?>'">
    <div style="padding:18px 20px;">

      <!-- Header -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div style="flex:1;">
          <div style="font-size:15px;font-weight:700;color:#0F172A;margin-bottom:3px;"><?= h($p['nombre']) ?></div>
          <?php if($p['nombre_corto']&&$p['nombre_corto']!==$p['nombre']): ?>
          <div style="font-size:12px;color:#94A3B8;"><?= h($p['nombre_corto']) ?></div>
          <?php endif; ?>
        </div>
        <span class="badge" style="background:<?= $tbg ?>;color:<?= $tc ?>;border:1px solid <?= $tc ?>30;flex-shrink:0;margin-left:8px;"><?= $p['tipo'] ?></span>
      </div>

      <!-- Info -->
      <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:12px;">
        <?php if($p['ruc']): ?>
        <div style="font-size:12px;color:#64748B;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-id-badge" style="color:#94A3B8;width:14px;"></i> RUC: <span class="mono"><?= h($p['ruc']) ?></span>
        </div>
        <?php endif; ?>
        <?php if($p['web']): ?>
        <div style="font-size:12px;color:#4A90C4;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-globe" style="width:14px;"></i>
          <a href="<?= h($p['web']) ?>" target="_blank" onclick="event.stopPropagation()" style="color:#4A90C4;"><?= h($p['web']) ?></a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Stats -->
      <div style="display:flex;gap:10px;border-top:1px solid #F1F5F9;padding-top:12px;">
        <div style="flex:1;text-align:center;">
          <div style="font-size:20px;font-weight:800;color:<?= $p['contratos_activos']>0?'#2E7D32':'#94A3B8' ?>;"><?= $p['contratos_activos'] ?></div>
          <div style="font-size:10px;font-weight:600;color:#94A3B8;text-transform:uppercase;">contratos</div>
        </div>
        <div style="width:1px;background:#F1F5F9;"></div>
        <div style="flex:1;text-align:center;">
          <div style="font-size:20px;font-weight:800;color:<?= $p['total_facturas']>0?'#1565C0':'#94A3B8' ?>;"><?= numero($p['total_facturas']) ?></div>
          <div style="font-size:10px;font-weight:600;color:#94A3B8;text-transform:uppercase;">facturas</div>
        </div>
        <?php if($p['contratos_vencen']>0): ?>
        <div style="width:1px;background:#F1F5F9;"></div>
        <div style="flex:1;text-align:center;">
          <div style="font-size:20px;font-weight:800;color:#EF5350;"><?= $p['contratos_vencen'] ?></div>
          <div style="font-size:10px;font-weight:600;color:#EF5350;text-transform:uppercase;">⚠ vencen</div>
        </div>
        <?php endif; ?>
        <?php if($p['evaluacion']): ?>
        <div style="width:1px;background:#F1F5F9;"></div>
        <div style="flex:1;text-align:center;">
          <div style="font-size:16px;font-weight:800;color:#F9A825;">★ <?= number_format($p['evaluacion'],1) ?></div>
          <div style="font-size:10px;font-weight:600;color:#94A3B8;text-transform:uppercase;">rating</div>
        </div>
        <?php endif; ?>
      </div>

    </div>
    <!-- Acciones (aparecen en hover) -->
    <div style="border-top:1px solid #F8FAFC;padding:8px 14px;background:#FAFBFF;display:flex;gap:6px;" onclick="event.stopPropagation()">
      <a href="proveedor_detalle.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> Ver</a>
      <a href="proveedor_form.php?id=<?= $p['id'] ?>"   class="btn btn-ghost btn-sm"><i class="fas fa-pen"></i> Editar</a>
      <a href="contratos.php?proveedor=<?= $p['id'] ?>"  class="btn btn-ghost btn-sm"><i class="fas fa-file-contract"></i> Contratos</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php if($resultado['total_pages']>1): ?>
<div style="margin-top:16px;"><?= paginator($resultado['total'],20,$page,BASE_URL.'/modules/proveedores/proveedores.php?'.http_build_query(array_filter(['q'=>$buscar,'tipo'=>$filTipo]))) ?></div>
<?php endif; ?>
<?php endif; ?>

<?php endLayout(); ?>
