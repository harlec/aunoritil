<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$pageTitle  = 'Contratos';
$pageModule = 'proveedores';

$buscar     = clean($_GET['q']         ?? '');
$filProv    = (int)($_GET['proveedor'] ?? 0);
$filTipo    = clean($_GET['tipo']      ?? '');
$filEstado  = clean($_GET['estado']    ?? '');
$alerta     = (int)($_GET['alerta']    ?? 0);
$page       = max(1,(int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($buscar)   { $where[] = '(c.numero LIKE ? OR c.nombre LIKE ? OR p.nombre LIKE ?)'; $like="%{$buscar}%"; $params=array_merge($params,[$like,$like,$like]); }
if ($filProv)  { $where[] = 'c.proveedor_id = ?'; $params[] = $filProv; }
if ($filTipo)  { $where[] = 'c.tipo = ?';          $params[] = $filTipo; }
if ($filEstado){ $where[] = 'c.estado = ?';         $params[] = $filEstado; }
if ($alerta)   { $where[] = 'c.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL ? DAY) AND c.estado=\'Activo\''; $params[] = $alerta; }

$sql = "SELECT c.*, p.nombre AS proveedor_nombre, p.tipo AS proveedor_tipo,
        DATEDIFF(c.fecha_fin, CURDATE()) AS dias_vencimiento
        FROM sup_contratos c
        JOIN sup_proveedores p ON p.id = c.proveedor_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.fecha_fin ASC, c.estado = 'Activo' DESC";

$resultado = DB::paginate($sql, $params, $page, 20);
$contratos  = $resultado['data'];

$proveedores = DB::query("SELECT id, nombre FROM sup_proveedores WHERE activo=1 ORDER BY nombre");

// KPIs
$stats = DB::row("SELECT
    SUM(estado='Activo' AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())) vigentes,
    SUM(fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND estado='Activo') d30,
    SUM(fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY) AND estado='Activo') d90,
    SUM(estado='Vencido' OR (estado='Activo' AND fecha_fin < CURDATE())) vencidos
    FROM sup_contratos");

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-file-contract" style="color:#1565C0;margin-right:10px;"></i>Contratos</h1>
    <p class="page-subtitle">Gestión de contratos, SLA y acuerdos con proveedores</p>
  </div>
  <a href="<?= BASE_URL ?>/modules/proveedores/contrato_form.php" class="btn btn-primary">
    <i class="fas fa-plus"></i> Nuevo contrato
  </a>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltroEstado('Activo')">
    <div class="kpi-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-circle-check"></i></div>
    <div class="kpi-label">Contratos vigentes</div>
    <div class="kpi-value" style="color:#2E7D32;"><?= (int)($stats['vigentes']??0) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='?alerta=30'">
    <div class="kpi-icon" style="background:<?= ($stats['d30']??0)>0?'#FFEBEE':'#E8F5E9' ?>;color:<?= ($stats['d30']??0)>0?'#EF5350':'#2E7D32' ?>;"><i class="fas fa-triangle-exclamation"></i></div>
    <div class="kpi-label">Vencen en 30 días</div>
    <div class="kpi-value" style="color:<?= ($stats['d30']??0)>0?'#EF5350':'#2E7D32' ?>;"><?= (int)($stats['d30']??0) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='?alerta=90'">
    <div class="kpi-icon" style="background:#FFF9C4;color:#F57F17;"><i class="fas fa-clock"></i></div>
    <div class="kpi-label">Vencen en 90 días</div>
    <div class="kpi-value" style="color:#F57F17;"><?= (int)($stats['d90']??0) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltroEstado('Vencido')">
    <div class="kpi-icon" style="background:#FFEBEE;color:#EF5350;"><i class="fas fa-calendar-xmark"></i></div>
    <div class="kpi-label">Vencidos</div>
    <div class="kpi-value" style="color:#EF5350;"><?= (int)($stats['vencidos']??0) ?></div>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:14px 16px;">
    <form method="GET" id="formFiltros" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:2;min-width:200px;position:relative;">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;pointer-events:none;"></i>
        <input type="text" name="q" value="<?= h($buscar) ?>" placeholder="Número, nombre, proveedor..." class="form-control" style="padding-left:34px;">
      </div>
      <div style="min-width:180px;">
        <select name="proveedor" class="form-control" onchange="this.form.submit()">
          <option value="">Todos los proveedores</option>
          <?php foreach($proveedores as $pv): ?>
          <option value="<?= $pv['id'] ?>" <?= $filProv===$pv['id']?'selected':'' ?>><?= h($pv['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px;">
        <select name="tipo" class="form-control" onchange="this.form.submit()">
          <option value="">Todos los tipos</option>
          <?php foreach(['Soporte','Mantenimiento','Licencia','Servicio','SaaS','ISP','Otro'] as $t): ?>
          <option value="<?= $t ?>" <?= $filTipo===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:120px;">
        <select name="estado" class="form-control" id="selectEstado" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach(['Activo','Vencido','En_renovacion','Cancelado','Borrador'] as $e): ?>
          <option value="<?= $e ?>" <?= $filEstado===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-magnifying-glass"></i></button>
        <?php if($buscar||$filProv||$filTipo||$filEstado||$alerta): ?>
        <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-xmark"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Tabla contratos -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= numero($resultado['total']) ?> contratos</span>
    <button class="btn btn-ghost btn-sm" onclick="exportarCSV()"><i class="fas fa-download"></i> Exportar</button>
  </div>
  <div class="table-wrap">
    <?php if(empty($contratos)): ?>
    <div class="empty-state"><i class="fas fa-file-contract"></i><p>No se encontraron contratos</p></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Número</th>
          <th>Nombre / Descripción</th>
          <th>Proveedor</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th>Vencimiento</th>
          <th>Monto mensual</th>
          <th>Alerta</th>
          <th style="width:90px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($contratos as $c):
          $dias = $c['dias_vencimiento'];
          $vColor = $dias === null ? '#94A3B8' : ($dias < 0 ? '#EF5350' : ($dias <= 30 ? '#EF5350' : ($dias <= 90 ? '#F9A825' : '#4CAF50')));
          $vLabel = $dias === null ? '—' : ($dias < 0 ? 'Vencido' : ($dias === 0 ? 'Hoy' : $dias.'d'));
        ?>
        <tr style="cursor:pointer;" onclick="location.href='contrato_detalle.php?id=<?= $c['id'] ?>'">
          <td>
            <span class="mono" style="font-size:12px;font-weight:600;color:#4A90C4;"><?= h($c['numero']) ?></span>
          </td>
          <td style="max-width:220px;">
            <div style="font-weight:600;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($c['nombre']) ?></div>
          </td>
          <td style="font-size:13px;color:#475569;"><?= h($c['proveedor_nombre']) ?></td>
          <td>
            <span style="font-size:11px;background:#F1F5F9;color:#475569;padding:3px 8px;border-radius:6px;"><?= $c['tipo'] ?></span>
          </td>
          <td><?= badgeEstado($c['estado']) ?></td>
          <td style="font-size:12px;color:#64748B;">
            <?= fechaHumana($c['fecha_fin']) ?>
          </td>
          <td style="font-size:13px;font-weight:600;">
            <?php if($c['monto_mensual']): ?>
              <?= moneda($c['monto_mensual'], $c['moneda']) ?>
            <?php else: ?>
              <span style="color:#94A3B8;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($c['fecha_fin']): ?>
            <span style="background:<?= $vColor ?>20;color:<?= $vColor ?>;font-size:12px;font-weight:700;padding:3px 10px;border-radius:6px;">
              <?= $vLabel ?>
            </span>
            <?php endif; ?>
          </td>
          <td onclick="event.stopPropagation()">
            <div style="display:flex;gap:4px;">
              <a href="contrato_detalle.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Ver"><i class="fas fa-eye"></i></a>
              <a href="contrato_form.php?id=<?= $c['id'] ?>"    class="btn btn-ghost btn-sm btn-icon" title="Editar"><i class="fas fa-pen"></i></a>
              <button onclick="confirmarEliminar(<?= $c['id'] ?>,'<?= h(addslashes($c['nombre'])) ?>')"
                      class="btn btn-ghost btn-sm btn-icon" style="color:#EF5350;" title="Eliminar"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if($resultado['total_pages']>1): ?>
  <div style="padding:0 16px 4px;"><?= paginator($resultado['total'],20,$page,BASE_URL.'/modules/proveedores/contratos.php?'.http_build_query(array_filter(['q'=>$buscar,'proveedor'=>$filProv,'tipo'=>$filTipo,'estado'=>$filEstado]))) ?></div>
  <?php endif; ?>
</div>

<script>
function setFiltroEstado(e) {
    document.getElementById('selectEstado').value = e;
    document.getElementById('formFiltros').submit();
}
function confirmarEliminar(id, nombre) {
    if (confirm(`¿Eliminar contrato "${nombre}"?`)) {
        window.location.href = 'contrato_action.php?accion=eliminar&id=' + id;
    }
}
function exportarCSV() {
    const p = new URLSearchParams(window.location.search);
    p.set('export','csv');
    window.location.href = 'contrato_export.php?' + p.toString();
}
</script>

<?php endLayout(); ?>
