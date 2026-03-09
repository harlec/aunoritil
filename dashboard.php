<?php
require_once __DIR__ . '/config.php';
Auth::requireLogin();

$pageTitle  = 'Dashboard';
$pageModule = 'dashboard';

// ── KPIs ──────────────────────────────────────────────────────
$kpis = [
    'tickets_abiertos'  => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE estado NOT IN ('Cerrado','Cancelado')"),
    'tickets_criticos'  => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE prioridad='Critica' AND estado NOT IN ('Cerrado','Cancelado')"),
    'sla_riesgo'        => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE sla_resolucion_limite < DATE_ADD(NOW(),INTERVAL 2 HOUR) AND estado NOT IN ('Resuelto','Cerrado','Cancelado')"),
    'cis_total'         => DB::value("SELECT COUNT(*) FROM cmdb_cis WHERE estado='Activo'"),
    'garantias_vencen'  => DB::value("SELECT COUNT(*) FROM cmdb_garantias WHERE fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)"),
    'contratos_vencen'  => DB::value("SELECT COUNT(*) FROM sup_contratos WHERE fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND estado='Activo'"),
    'facturas_pendientes'=> DB::value("SELECT COUNT(*) FROM fin_facturas WHERE estado='Pendiente'"),
    'tickets_hoy'       => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE DATE(fecha_apertura)=CURDATE()"),
];

// ── Tickets recientes ─────────────────────────────────────────
$ticketsRecientes = DB::query(
    "SELECT t.*, u.nombre as solicitante_nombre, a.nombre as agente_nombre,
            c.nombre as categoria_nombre
     FROM itsm_tickets t
     LEFT JOIN adm_usuarios u ON u.id = t.solicitante_id
     LEFT JOIN adm_usuarios a ON a.id = t.agente_id
     LEFT JOIN itsm_categorias c ON c.id = t.categoria_id
     ORDER BY t.fecha_apertura DESC LIMIT 8"
);

// ── Tickets por estado ────────────────────────────────────────
$porEstado = DB::query(
    "SELECT estado, COUNT(*) as total FROM itsm_tickets
     WHERE estado NOT IN ('Cerrado','Cancelado')
     GROUP BY estado ORDER BY total DESC"
);

// ── Actividad semanal (7 días) ────────────────────────────────
$actividadSemanal = DB::query(
    "SELECT DATE(fecha_apertura) as dia, COUNT(*) as total
     FROM itsm_tickets
     WHERE fecha_apertura >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY dia ORDER BY dia"
);

// ── Garantías próximas a vencer ───────────────────────────────
$garantiasAlert = DB::query(
    "SELECT g.*, c.nombre as ci_nombre, c.codigo_ci
     FROM cmdb_garantias g
     JOIN cmdb_cis c ON c.id = g.ci_id
     WHERE g.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 60 DAY)
     ORDER BY g.fecha_fin ASC LIMIT 5"
);

// ── Agentes y carga ───────────────────────────────────────────
$cargaAgentes = DB::query(
    "SELECT u.nombre, COUNT(t.id) as tickets
     FROM adm_usuarios u
     JOIN itsm_tickets t ON t.agente_id = u.id AND t.estado NOT IN ('Cerrado','Cancelado')
     GROUP BY u.id ORDER BY tickets DESC LIMIT 5"
);

require_once __DIR__ . '/includes/layout.php';
?>

<!-- ── Page header ──────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Vista general del sistema ITSM · <?= date('d/m/Y H:i') ?></p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn btn-ghost btn-sm" onclick="location.reload()">
      <i class="fas fa-arrows-rotate"></i> Actualizar
    </button>
    <a href="<?= BASE_URL ?>/modules/reportes/dashboard.php" class="btn btn-primary btn-sm">
      <i class="fas fa-chart-pie"></i> Reportes
    </a>
  </div>
</div>

<!-- ── KPI Grid ──────────────────────────────────────────────── -->
<div class="kpi-grid">

  <div class="kpi-card" onclick="location.href='<?= BASE_URL ?>/modules/itsm/tickets.php'" style="cursor:pointer;">
    <div class="kpi-icon" style="background:#E3F2FD;color:#1565C0;">
      <i class="fas fa-ticket"></i>
    </div>
    <div class="kpi-label">Tickets Abiertos</div>
    <div class="kpi-value" style="color:#1565C0;"><?= numero($kpis['tickets_abiertos']) ?></div>
    <div class="kpi-sub"><i class="fas fa-calendar-day" style="color:#94A3B8;"></i> +<?= $kpis['tickets_hoy'] ?> hoy</div>
  </div>

  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='<?= BASE_URL ?>/modules/itsm/tickets.php?prioridad=Critica'">
    <?php if($kpis['tickets_criticos'] > 0): ?>
    <div class="kpi-icon" style="background:#FFEBEE;color:#EF5350;">
      <i class="fas fa-triangle-exclamation"></i>
    </div>
    <div class="kpi-label">Críticos</div>
    <div class="kpi-value" style="color:#EF5350;"><?= $kpis['tickets_criticos'] ?></div>
    <div class="kpi-sub" style="color:#EF5350;"><i class="fas fa-circle-exclamation"></i> Requieren atención</div>
    <?php else: ?>
    <div class="kpi-icon" style="background:#E8F5E9;color:#4CAF50;"><i class="fas fa-circle-check"></i></div>
    <div class="kpi-label">Críticos</div>
    <div class="kpi-value" style="color:#4CAF50;">0</div>
    <div class="kpi-sub" style="color:#4CAF50;"><i class="fas fa-check"></i> Sin críticos</div>
    <?php endif; ?>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon" style="background:<?= $kpis['sla_riesgo'] > 0 ? '#FFF3E0' : '#E8F5E9' ?>;color:<?= $kpis['sla_riesgo'] > 0 ? '#E65100' : '#4CAF50' ?>;">
      <i class="fas fa-clock"></i>
    </div>
    <div class="kpi-label">SLA en Riesgo</div>
    <div class="kpi-value" style="color:<?= $kpis['sla_riesgo'] > 0 ? '#E65100' : '#4CAF50' ?>;"><?= $kpis['sla_riesgo'] ?></div>
    <div class="kpi-sub">próximas 2 horas</div>
  </div>

  <div class="kpi-card" onclick="location.href='<?= BASE_URL ?>/modules/cmdb/cis.php'" style="cursor:pointer;">
    <div class="kpi-icon" style="background:#E0F2F1;color:#00695C;">
      <i class="fas fa-server"></i>
    </div>
    <div class="kpi-label">CIs Activos (CMDB)</div>
    <div class="kpi-value" style="color:#00695C;"><?= numero($kpis['cis_total']) ?></div>
    <div class="kpi-sub"><i class="fas fa-database" style="color:#94A3B8;"></i> Elementos de configuración</div>
  </div>

  <div class="kpi-card" onclick="location.href='<?= BASE_URL ?>/modules/finanzas/facturas.php?estado=Pendiente'" style="cursor:pointer;">
    <div class="kpi-icon" style="background:<?= $kpis['facturas_pendientes'] > 0 ? '#FFF3E0' : '#F5F5F5' ?>;color:<?= $kpis['facturas_pendientes'] > 0 ? '#E65100' : '#616161' ?>;">
      <i class="fas fa-file-invoice-dollar"></i>
    </div>
    <div class="kpi-label">Facturas Pendientes</div>
    <div class="kpi-value"><?= $kpis['facturas_pendientes'] ?></div>
    <div class="kpi-sub">por pagar</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon" style="background:<?= $kpis['garantias_vencen'] > 0 ? '#FFEBEE' : '#E8F5E9' ?>;color:<?= $kpis['garantias_vencen'] > 0 ? '#EF5350' : '#4CAF50' ?>;">
      <i class="fas fa-shield-halved"></i>
    </div>
    <div class="kpi-label">Garantías por Vencer</div>
    <div class="kpi-value"><?= $kpis['garantias_vencen'] ?></div>
    <div class="kpi-sub">próximos 30 días</div>
  </div>

</div>

<!-- ── Grid principal ────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

  <!-- Tickets recientes -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-ticket" style="color:#1565C0;margin-right:8px;"></i>Tickets Recientes</span>
      <a href="<?= BASE_URL ?>/modules/itsm/tickets.php" class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Ticket</th>
            <th>Tipo</th>
            <th>Asunto</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>SLA</th>
            <th>Apertura</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ticketsRecientes)): ?>
          <tr><td colspan="7" style="text-align:center;color:#94A3B8;padding:32px;">
            <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
            Sin tickets registrados
          </td></tr>
          <?php else: ?>
          <?php foreach ($ticketsRecientes as $t):
            $sla = ITIL::estadoSLA($t['sla_resolucion_limite'], $t['sla_resolucion_cumplido']);
          ?>
          <tr onclick="location.href='<?= BASE_URL ?>/modules/itsm/tickets.php?id=<?= $t['id'] ?>'" style="cursor:pointer;">
            <td>
              <span class="mono" style="font-size:12px;color:#4A90C4;font-weight:600;"><?= h($t['numero']) ?></span>
            </td>
            <td><?= badgeTipo($t['tipo']) ?></td>
            <td style="max-width:200px;">
              <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($t['titulo']) ?></div>
              <?php if ($t['solicitante_nombre']): ?>
              <div style="font-size:11px;color:#94A3B8;"><?= h($t['solicitante_nombre']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= badgePrioridad($t['prioridad']) ?></td>
            <td><?= badgeEstado($t['estado']) ?></td>
            <td>
              <span style="color:<?= $sla['color'] ?>;font-size:12px;">
                <i class="fas <?= $sla['icon'] ?>"></i>
              </span>
            </td>
            <td style="font-size:12px;color:#64748B;"><?= tiempoRelativo($t['fecha_apertura']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sidebar derecho -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Tickets por estado -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Por Estado</span>
      </div>
      <div class="card-body" style="padding:16px;">
        <?php
        $estadoColors = [
            'Nuevo'      => ['#EDE7F6','#6A1B9A'],
            'Asignado'   => ['#E3F2FD','#1565C0'],
            'En_proceso' => ['#E0F2F1','#00695C'],
            'En_espera'  => ['#FFF3E0','#E65100'],
            'Resuelto'   => ['#E8F5E9','#2E7D32'],
        ];
        $totalAbiertos = array_sum(array_column($porEstado, 'total')) ?: 1;
        foreach ($porEstado as $e):
          [$bg, $color] = $estadoColors[$e['estado']] ?? ['#F5F5F5','#616161'];
          $pct = round($e['total'] / $totalAbiertos * 100);
        ?>
        <div style="margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:12px;font-weight:600;color:<?= $color ?>;"><?= str_replace('_',' ',$e['estado']) ?></span>
            <span style="font-size:12px;font-weight:700;color:#1E293B;"><?= $e['total'] ?></span>
          </div>
          <div style="height:6px;background:#F1F5F9;border-radius:3px;">
            <div style="height:6px;background:<?= $color ?>;border-radius:3px;width:<?= $pct ?>%;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($porEstado)): ?>
        <p style="text-align:center;color:#94A3B8;font-size:13px;padding:12px 0;">Sin tickets activos</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Carga de agentes -->
    <?php if (!empty($cargaAgentes)): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Carga de Agentes</span>
      </div>
      <div class="card-body" style="padding:16px;">
        <?php
        $maxCarga = max(array_column($cargaAgentes,'tickets')) ?: 1;
        foreach ($cargaAgentes as $ag):
          $pct = round($ag['tickets'] / $maxCarga * 100);
          $color = $pct >= 80 ? '#EF5350' : ($pct >= 50 ? '#FF6B00' : '#4CAF50');
        ?>
        <div style="margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:12px;font-weight:500;color:#334155;"><?= h($ag['nombre']) ?></span>
            <span style="font-size:12px;font-weight:700;color:<?= $color ?>;"><?= $ag['tickets'] ?></span>
          </div>
          <div style="height:5px;background:#F1F5F9;border-radius:3px;">
            <div style="height:5px;background:<?= $color ?>;border-radius:3px;width:<?= $pct ?>%;transition:width .3s;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Garantías por vencer -->
    <?php if (!empty($garantiasAlert)): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title" style="color:#E65100;"><i class="fas fa-shield-halved" style="margin-right:6px;"></i>Garantías</span>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <?php foreach ($garantiasAlert as $g):
          $dias = (int)((strtotime($g['fecha_fin']) - time()) / 86400);
          $color = $dias <= 7 ? '#EF5350' : ($dias <= 30 ? '#FF6B00' : '#F9A825');
        ?>
        <div style="padding:8px 0;border-bottom:1px solid #F8FAFC;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:12px;font-weight:600;color:#334155;"><?= h($g['ci_nombre']) ?></div>
            <div style="font-size:11px;color:#94A3B8;"><?= h($g['codigo_ci']) ?></div>
          </div>
          <span style="background:<?= $color ?>20;color:<?= $color ?>;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;">
            <?= $dias ?>d
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── Actividad semanal ──────────────────────────────────────── -->
<div class="card" style="margin-top:20px;">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-chart-bar" style="color:#4A90C4;margin-right:8px;"></i>Actividad — Últimos 7 días</span>
  </div>
  <div class="card-body">
    <canvas id="chartActividad" height="60"></canvas>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function() {
  const raw  = <?= json_encode($actividadSemanal) ?>;
  const dias  = [];
  const tots  = [];
  const today = new Date();
  for (let i = 6; i >= 0; i--) {
    const d = new Date(today); d.setDate(today.getDate() - i);
    const k = d.toISOString().slice(0,10);
    const dLabel = d.toLocaleDateString('es-PE', { weekday:'short', day:'2-digit' });
    dias.push(dLabel);
    const found = raw.find(r => r.dia === k);
    tots.push(found ? parseInt(found.total) : 0);
  }
  new Chart(document.getElementById('chartActividad'), {
    type: 'bar',
    data: {
      labels: dias,
      datasets: [{
        label: 'Tickets abiertos',
        data: tots,
        backgroundColor: '#1A3A5C20',
        borderColor: '#1A3A5C',
        borderWidth: 2,
        borderRadius: 8,
        hoverBackgroundColor: '#1A3A5C40',
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#94A3B8' } },
        x: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#64748B' } }
      }
    }
  });
})();
</script>

<?php endLayout(); ?>
