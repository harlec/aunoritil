<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$pageTitle  = 'Garantías';
$pageModule = 'cmdb';

$filtro = clean($_GET['filtro'] ?? '');
$page   = max(1,(int)($_GET['page']??1));

$where  = ['1=1'];
$params = [];
if ($filtro === 'vencidas')  { $where[] = 'g.fecha_fin < CURDATE()'; }
elseif ($filtro === '30d')   { $where[] = 'g.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)'; }
elseif ($filtro === '90d')   { $where[] = 'g.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY)'; }
elseif ($filtro === 'ok')    { $where[] = 'g.fecha_fin > DATE_ADD(CURDATE(),INTERVAL 90 DAY)'; }

$sql = "SELECT g.*, c.nombre AS ci_nombre, c.codigo_ci, c.tipo_ci, c.categoria,
        u.nombre AS sede_nombre,
        DATEDIFF(g.fecha_fin, CURDATE()) AS dias_restantes
        FROM cmdb_garantias g
        JOIN cmdb_cis c ON c.id = g.ci_id
        LEFT JOIN cmdb_ubicaciones u ON u.id = c.ubicacion_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY g.fecha_fin ASC";

$resultado = DB::paginate($sql, $params, $page);
$garantias  = $resultado['data'];

// Contadores para KPIs
$stats = DB::row("SELECT
    SUM(fecha_fin < CURDATE()) vencidas,
    SUM(fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)) d30,
    SUM(fecha_fin BETWEEN DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND DATE_ADD(CURDATE(),INTERVAL 90 DAY)) d90,
    SUM(fecha_fin > DATE_ADD(CURDATE(),INTERVAL 90 DAY)) ok
    FROM cmdb_garantias");

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-shield-halved" style="color:#FF6B00;margin-right:10px;"></i>Garantías</h1>
    <p class="page-subtitle">Control de vencimiento de garantías y contratos de soporte</p>
  </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <?php $kpiData = [
    ['vencidas','Vencidas','#EF5350','#FFEBEE','fa-circle-xmark'],
    ['d30','Vencen en 30d','#FF6B00','#FFF3E0','fa-triangle-exclamation'],
    ['d90','Vencen en 90d','#F9A825','#FFF9C4','fa-clock'],
    ['ok','Vigentes +90d','#4CAF50','#E8F5E9','fa-shield-check'],
  ];
  foreach($kpiData as [$key,$label,$color,$bg,$icon]):
    $val = $stats[$key] ?? 0;
  ?>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='?filtro=<?= $key ?>'">
    <div class="kpi-icon" style="background:<?= $bg ?>;color:<?= $color ?>;"><i class="fas <?= $icon ?>"></i></div>
    <div class="kpi-label"><?= $label ?></div>
    <div class="kpi-value" style="color:<?= $color ?>;"><?= (int)$val ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtros rápidos -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
  <?php foreach([
    [''         ,'Todas'],
    ['vencidas' ,'🔴 Vencidas'],
    ['30d'      ,'🟠 30 días'],
    ['90d'      ,'🟡 90 días'],
    ['ok'       ,'🟢 Vigentes'],
  ] as [$val,$label]):
    $active = $filtro === $val;
  ?>
  <a href="?filtro=<?= $val ?>" class="btn btn-sm <?= $active?'btn-primary':'btn-ghost' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if(empty($garantias)): ?>
    <div class="empty-state"><i class="fas fa-shield-halved"></i><p>Sin garantías para mostrar</p></div>
    <?php else: ?>
    <table>
      <thead><tr><th>CI</th><th>Código</th><th>Tipo</th><th>Sede</th><th>Proveedor</th><th>Vence</th><th>Estado</th><th>Contacto</th><th></th></tr></thead>
      <tbody>
        <?php foreach($garantias as $g):
          $d = (int)$g['dias_restantes'];
          $color = $d < 0 ? '#EF5350' : ($d <= 30 ? '#FF6B00' : ($d <= 90 ? '#F9A825' : '#4CAF50'));
          $label = $d < 0 ? 'Vencida' : ($d === 0 ? 'Hoy' : ($d < 30 ? $d.'d' : ($d < 365 ? round($d/30).'m' : round($d/365,1).'a')));
        ?>
        <tr onclick="location.href='<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $g['ci_id'] ?>&tab=garantias'" style="cursor:pointer;">
          <td style="font-weight:600;color:#0F172A;"><?= h($g['ci_nombre']) ?></td>
          <td><span class="mono" style="font-size:12px;color:#4A90C4;"><?= h($g['codigo_ci']) ?></span></td>
          <td><span style="font-size:12px;background:#F1F5F9;padding:3px 8px;border-radius:6px;"><?= str_replace('_',' ',$g['tipo']) ?></span></td>
          <td style="font-size:12px;color:#64748B;"><?= h($g['sede_nombre']??'—') ?></td>
          <td style="font-size:12px;"><?= h($g['proveedor_texto']??'—') ?></td>
          <td style="font-size:12px;color:#64748B;"><?= fechaHumana($g['fecha_fin']) ?></td>
          <td>
            <span style="background:<?= $color ?>20;color:<?= $color ?>;font-size:12px;font-weight:700;padding:3px 10px;border-radius:6px;">
              <?= $label ?>
            </span>
          </td>
          <td style="font-size:12px;color:#64748B;"><?= h($g['contacto_soporte']??'') ?><?php if($g['telefono_soporte']): ?> · <?= h($g['telefono_soporte']) ?><?php endif; ?></td>
          <td onclick="event.stopPropagation()">
            <a href="<?= BASE_URL ?>/modules/cmdb/garantia_form.php?id=<?= $g['id'] ?>&ci_id=<?= $g['ci_id'] ?>"
               class="btn btn-ghost btn-sm btn-icon"><i class="fas fa-pen"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if($resultado['total_pages']>1): ?>
    <div style="padding:0 16px 4px;"><?= paginator($resultado['total'],PER_PAGE,$page,BASE_URL.'/modules/cmdb/garantias.php?filtro='.$filtro) ?></div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php endLayout(); ?>
