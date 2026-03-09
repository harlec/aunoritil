<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$pageTitle  = 'CMDB — Elementos de Configuración';
$pageModule = 'cmdb';

// ── Filtros ───────────────────────────────────────────────────
$buscar    = clean($_GET['q']          ?? '');
$filTipo   = clean($_GET['tipo']       ?? '');
$filCat    = clean($_GET['categoria']  ?? '');
$filEstado = clean($_GET['estado']     ?? '');
$filSede   = (int)($_GET['sede']       ?? 0);
$filCrit   = clean($_GET['criticidad'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));

// ── Query base ────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($buscar) {
    $where[]  = '(c.nombre LIKE ? OR c.codigo_ci LIKE ? OR c.ip_address LIKE ? OR c.numero_serie LIKE ? OR c.hostname LIKE ?)';
    $like     = "%{$buscar}%";
    $params   = array_merge($params, [$like,$like,$like,$like,$like]);
}
if ($filTipo)   { $where[] = 'c.tipo_ci = ?';     $params[] = $filTipo; }
if ($filCat)    { $where[] = 'c.categoria = ?';   $params[] = $filCat; }
if ($filEstado) { $where[] = 'c.estado = ?';       $params[] = $filEstado; }
if ($filSede)   { $where[] = 'c.ubicacion_id = ?'; $params[] = $filSede; }
if ($filCrit)   { $where[] = 'c.criticidad = ?';   $params[] = $filCrit; }

$whereStr = implode(' AND ', $where);

$sql = "SELECT c.*,
        u.nombre AS ubicacion_nombre,
        us.nombre AS propietario_nombre,
        ag.nombre AS responsable_nombre,
        DATEDIFF(c.fecha_garantia_fin, CURDATE()) AS dias_garantia
        FROM cmdb_cis c
        LEFT JOIN cmdb_ubicaciones u  ON u.id = c.ubicacion_id
        LEFT JOIN adm_usuarios us     ON us.id = c.propietario_id
        LEFT JOIN adm_usuarios ag     ON ag.id = c.responsable_ti
        WHERE {$whereStr}
        ORDER BY c.criticidad = 'Critico' DESC, c.criticidad = 'Alto' DESC, c.nombre ASC";

$resultado = DB::paginate($sql, $params, $page, 20);
$cis        = $resultado['data'];

// ── Totales por estado (para KPIs) ───────────────────────────
$totales = DB::query("SELECT estado, COUNT(*) total FROM cmdb_cis GROUP BY estado");
$totMap  = array_column($totales, 'total', 'estado');

$totalActivos   = $totMap['Activo']        ?? 0;
$totalInactivos = ($totMap['Inactivo']     ?? 0) + ($totMap['En_reparacion'] ?? 0);
$totalBaja      = $totMap['Dado_de_baja']  ?? 0;
$totalAlmacen   = $totMap['En_almacen']    ?? 0;

// Garantías por vencer (30 días)
$garantiasAlert = (int)DB::value(
    "SELECT COUNT(*) FROM cmdb_garantias
     WHERE fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
);

// ── Datos para filtros ────────────────────────────────────────
$sedes = DB::query("SELECT id, nombre FROM cmdb_ubicaciones WHERE activa=1 ORDER BY nombre");

require_once __DIR__ . '/../../includes/layout.php';
?>

<!-- ── Page header ──────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-server" style="color:#00695C;margin-right:10px;"></i>CMDB</h1>
    <p class="page-subtitle">Elementos de Configuración · <?= numero($resultado['total']) ?> registros</p>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= BASE_URL ?>/modules/cmdb/importar.php" class="btn btn-ghost">
      <i class="fas fa-file-excel"></i> Importar Excel
    </a>
    <a href="<?= BASE_URL ?>/modules/cmdb/ci_form.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Nuevo CI
    </a>
  </div>
</div>

<!-- ── KPIs ─────────────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltro('estado','')">
    <div class="kpi-icon" style="background:#E0F2F1;color:#00695C;"><i class="fas fa-server"></i></div>
    <div class="kpi-label">Total CIs</div>
    <div class="kpi-value" style="color:#00695C;"><?= numero(array_sum($totMap ?: [0])) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltro('estado','Activo')">
    <div class="kpi-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-circle-check"></i></div>
    <div class="kpi-label">Activos</div>
    <div class="kpi-value" style="color:#2E7D32;"><?= numero($totalActivos) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltro('estado','En_reparacion')">
    <div class="kpi-icon" style="background:#FFF3E0;color:#E65100;"><i class="fas fa-wrench"></i></div>
    <div class="kpi-label">En Reparación / Inactivo</div>
    <div class="kpi-value" style="color:#E65100;"><?= numero($totalInactivos) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltro('estado','En_almacen')">
    <div class="kpi-icon" style="background:#E3F2FD;color:#1565C0;"><i class="fas fa-box"></i></div>
    <div class="kpi-label">En Almacén</div>
    <div class="kpi-value" style="color:#1565C0;"><?= numero($totalAlmacen) ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="location.href='<?= BASE_URL ?>/modules/cmdb/garantias.php'">
    <div class="kpi-icon" style="background:<?= $garantiasAlert>0?'#FFEBEE':'#E8F5E9' ?>;color:<?= $garantiasAlert>0?'#EF5350':'#2E7D32' ?>;"><i class="fas fa-shield-halved"></i></div>
    <div class="kpi-label">Garantías vencen (30d)</div>
    <div class="kpi-value" style="color:<?= $garantiasAlert>0?'#EF5350':'#2E7D32' ?>;"><?= $garantiasAlert ?></div>
  </div>
</div>

<!-- ── Filtros ───────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="padding:16px;">
    <form method="GET" id="formFiltros" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">

      <div style="flex:2;min-width:200px;">
        <label class="form-label" style="margin-bottom:4px;">Buscar</label>
        <div style="position:relative;">
          <i class="fas fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;"></i>
          <input type="text" name="q" value="<?= h($buscar) ?>"
                 placeholder="Nombre, código, IP, serie, hostname..."
                 class="form-control" style="padding-left:34px;">
        </div>
      </div>

      <div style="min-width:130px;">
        <label class="form-label" style="margin-bottom:4px;">Categoría</label>
        <select name="categoria" class="form-control" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach(['Hardware','Software','Red','Telefonia','ITS','Servicio','Otro'] as $cat): ?>
          <option value="<?= $cat ?>" <?= $filCat===$cat?'selected':'' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:150px;">
        <label class="form-label" style="margin-bottom:4px;">Tipo</label>
        <select name="tipo" class="form-control" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach(['Servidor','Laptop','Desktop','Switch','Router','AP','Firewall','Poste_ITS','Camara_IP','Modulo_SOS','UPS','Telefono_IP','Impresora','Licencia_SW','Otro'] as $t): ?>
          <option value="<?= $t ?>" <?= $filTipo===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:120px;">
        <label class="form-label" style="margin-bottom:4px;">Estado</label>
        <select name="estado" class="form-control" id="selectEstado" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach(['Activo','Inactivo','En_reparacion','En_almacen','Dado_de_baja','En_transito'] as $e): ?>
          <option value="<?= $e ?>" <?= $filEstado===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:120px;">
        <label class="form-label" style="margin-bottom:4px;">Criticidad</label>
        <select name="criticidad" class="form-control" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach(['Critico','Alto','Medio','Bajo'] as $c): ?>
          <option value="<?= $c ?>" <?= $filCrit===$c?'selected':'' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:140px;">
        <label class="form-label" style="margin-bottom:4px;">Sede</label>
        <select name="sede" class="form-control" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach($sedes as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filSede===$s['id']?'selected':'' ?>><?= h($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;gap:8px;align-items:flex-end;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-magnifying-glass"></i> Buscar</button>
        <?php if($buscar||$filTipo||$filCat||$filEstado||$filSede||$filCrit): ?>
        <a href="<?= BASE_URL ?>/modules/cmdb/cis.php" class="btn btn-ghost btn-sm"><i class="fas fa-xmark"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Tabla CIs ──────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <?php if($buscar||$filTipo||$filCat||$filEstado): ?>
        <i class="fas fa-filter" style="color:#FF6B00;margin-right:6px;"></i>Filtrado: <?= numero($resultado['total']) ?> resultados
      <?php else: ?>
        Todos los CIs
      <?php endif; ?>
    </span>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-ghost btn-sm" onclick="exportarCSV()">
        <i class="fas fa-download"></i> Exportar
      </button>
      <!-- Vista toggle -->
      <div style="display:flex;border:1px solid #E2E8F0;border-radius:8px;overflow:hidden;">
        <button id="btnTabla" onclick="setVista('tabla')" style="padding:6px 10px;border:none;background:#1A3A5C;color:white;cursor:pointer;font-size:12px;"><i class="fas fa-list"></i></button>
        <button id="btnGrid"  onclick="setVista('grid')"  style="padding:6px 10px;border:none;background:white;color:#64748B;cursor:pointer;font-size:12px;"><i class="fas fa-grid-2"></i></button>
      </div>
    </div>
  </div>

  <!-- Vista tabla -->
  <div id="vistaTabla" class="table-wrap">
    <?php if(empty($cis)): ?>
    <div class="empty-state">
      <i class="fas fa-server"></i>
      <p>No se encontraron elementos de configuración</p>
      <?php if($buscar||$filTipo||$filCat||$filEstado): ?>
      <a href="<?= BASE_URL ?>/modules/cmdb/cis.php" style="color:#4A90C4;font-size:13px;margin-top:8px;display:inline-block;">Limpiar filtros</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table id="tablaCIs">
      <thead>
        <tr>
          <th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
          <th>Código</th>
          <th>Nombre</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th>Criticidad</th>
          <th>IP / Hostname</th>
          <th>Sede</th>
          <th>Garantía</th>
          <th>Monitor</th>
          <th style="width:100px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($cis as $ci):
          $diasGar = $ci['dias_garantia'];
          $garColor = $diasGar === null ? '#94A3B8' : ($diasGar < 0 ? '#EF5350' : ($diasGar <= 30 ? '#FF6B00' : ($diasGar <= 90 ? '#F9A825' : '#4CAF50')));
          $monColor = match($ci['ultimo_estado_mon']) {
            'Up'        => '#4CAF50',
            'Down'      => '#EF5350',
            'Degradado' => '#FF6B00',
            default     => '#CBD5E1',
          };
          $critColors = ['Critico'=>['#FFEBEE','#EF5350'],'Alto'=>['#FFF3E0','#E65100'],'Medio'=>['#FFF9C4','#F57F17'],'Bajo'=>['#E8F5E9','#2E7D32']];
          [$cbg,$cc] = $critColors[$ci['criticidad']] ?? ['#F5F5F5','#616161'];
        ?>
        <tr class="ci-row" data-id="<?= $ci['id'] ?>">
          <td onclick="event.stopPropagation()"><input type="checkbox" class="ci-check" value="<?= $ci['id'] ?>"></td>
          <td>
            <span class="mono" style="font-size:12px;color:#4A90C4;font-weight:600;"><?= h($ci['codigo_ci']) ?></span>
          </td>
          <td>
            <div style="font-weight:600;color:#0F172A;"><?= h($ci['nombre']) ?></div>
            <?php if($ci['marca']||$ci['modelo']): ?>
            <div style="font-size:11px;color:#94A3B8;"><?= h(trim($ci['marca'].' '.$ci['modelo'])) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-size:12px;background:#F1F5F9;color:#475569;padding:3px 8px;border-radius:6px;">
              <?= h(str_replace('_',' ',$ci['tipo_ci'])) ?>
            </span>
          </td>
          <td><?= badgeEstado($ci['estado']) ?></td>
          <td>
            <span class="badge" style="background:<?= $cbg ?>;color:<?= $cc ?>;border:1px solid <?= $cc ?>30;">
              <?= $ci['criticidad'] ?>
            </span>
          </td>
          <td>
            <?php if($ci['ip_address']): ?>
            <span class="mono" style="font-size:12px;"><?= h($ci['ip_address']) ?></span>
            <?php endif; ?>
            <?php if($ci['hostname']): ?>
            <div style="font-size:11px;color:#94A3B8;"><?= h($ci['hostname']) ?></div>
            <?php endif; ?>
            <?php if(!$ci['ip_address']&&!$ci['hostname']): ?>
            <span style="color:#CBD5E1;">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#64748B;"><?= h($ci['ubicacion_nombre'] ?? '—') ?></td>
          <td>
            <?php if($ci['fecha_garantia_fin']): ?>
            <span style="color:<?= $garColor ?>;font-size:12px;font-weight:600;">
              <?php if($diasGar < 0): ?>
                <i class="fas fa-triangle-exclamation"></i> Vencida
              <?php elseif($diasGar <= 30): ?>
                <i class="fas fa-clock"></i> <?= $diasGar ?>d
              <?php else: ?>
                <i class="fas fa-shield-check"></i> <?= $diasGar ?>d
              <?php endif; ?>
            </span>
            <?php else: ?>
            <span style="color:#CBD5E1;font-size:12px;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($ci['monitor_id']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:<?= $monColor ?>;">
              <span style="width:8px;height:8px;border-radius:50%;background:<?= $monColor ?>;display:inline-block;<?= $ci['ultimo_estado_mon']==='Up'?'box-shadow:0 0 0 3px '.$monColor.'30;':'' ?>"></span>
              <?= $ci['ultimo_estado_mon'] ?>
            </span>
            <?php else: ?>
            <span style="color:#CBD5E1;font-size:12px;">—</span>
            <?php endif; ?>
          </td>
          <td onclick="event.stopPropagation()">
            <div style="display:flex;gap:4px;">
              <a href="<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $ci['id'] ?>"
                 class="btn btn-ghost btn-sm btn-icon" title="Ver detalle">
                <i class="fas fa-eye"></i>
              </a>
              <a href="<?= BASE_URL ?>/modules/cmdb/ci_form.php?id=<?= $ci['id'] ?>"
                 class="btn btn-ghost btn-sm btn-icon" title="Editar">
                <i class="fas fa-pen"></i>
              </a>
              <button onclick="confirmarEliminar(<?= $ci['id'] ?>, '<?= h(addslashes($ci['nombre'])) ?>')"
                      class="btn btn-ghost btn-sm btn-icon" title="Eliminar" style="color:#EF5350;">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Vista grid (cards) -->
  <div id="vistaGrid" style="display:none;padding:20px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
      <?php foreach($cis as $ci):
        $monColor = match($ci['ultimo_estado_mon']) {
          'Up'=>'#4CAF50','Down'=>'#EF5350','Degradado'=>'#FF6B00',default=>'#CBD5E1'
        };
        $catIcons = ['Hardware'=>'fa-server','Software'=>'fa-code','Red'=>'fa-network-wired','Telefonia'=>'fa-phone','ITS'=>'fa-road','Servicio'=>'fa-cloud'];
        $catColors = ['Hardware'=>'#1A3A5C','Software'=>'#4A90C4','Red'=>'#00695C','Telefonia'=>'#6A1B9A','ITS'=>'#FF6B00','Servicio'=>'#2E7D32'];
        $icon  = $catIcons[$ci['categoria']]  ?? 'fa-cube';
        $color = $catColors[$ci['categoria']] ?? '#475569';
      ?>
      <div class="card" style="padding:16px;cursor:pointer;transition:box-shadow .15s;"
           onmouseenter="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'"
           onmouseleave="this.style.boxShadow=''"
           onclick="location.href='<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $ci['id'] ?>'">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
          <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>15;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:18px;">
            <i class="fas <?= $icon ?>"></i>
          </div>
          <?= badgeEstado($ci['estado']) ?>
        </div>
        <div style="font-weight:700;color:#0F172A;margin-bottom:2px;font-size:14px;"><?= h($ci['nombre']) ?></div>
        <div style="font-size:11px;color:#94A3B8;margin-bottom:10px;" class="mono"><?= h($ci['codigo_ci']) ?></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748B;">
          <span><?= h($ci['ubicacion_nombre'] ?? '—') ?></span>
          <?php if($ci['monitor_id']): ?>
          <span style="color:<?= $monColor ?>;font-weight:600;">
            <i class="fas fa-circle" style="font-size:8px;"></i> <?= $ci['ultimo_estado_mon'] ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Paginador -->
  <?php if($resultado['total_pages'] > 1): ?>
  <div style="padding:0 16px 4px;">
    <?= paginator($resultado['total'], 20, $page, BASE_URL . '/modules/cmdb/cis.php?' . http_build_query(array_filter(['q'=>$buscar,'tipo'=>$filTipo,'categoria'=>$filCat,'estado'=>$filEstado,'sede'=>$filSede,'criticidad'=>$filCrit]))) ?>
  </div>
  <?php endif; ?>

</div>

<!-- Acciones masivas (aparece al seleccionar) -->
<div id="barraAcciones" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1E293B;color:white;border-radius:14px;padding:12px 20px;display:none;align-items:center;gap:16px;box-shadow:0 8px 32px rgba(0,0,0,.25);z-index:500;white-space:nowrap;">
  <span id="selCount" style="font-size:13px;font-weight:600;"></span>
  <div style="width:1px;height:20px;background:rgba(255,255,255,.2);"></div>
  <button onclick="accionMasiva('baja')" class="btn btn-sm" style="background:#EF5350;color:white;">
    <i class="fas fa-arrow-down"></i> Dar de baja
  </button>
  <button onclick="accionMasiva('almacen')" class="btn btn-sm" style="background:#4A90C4;color:white;">
    <i class="fas fa-box"></i> Mover a almacén
  </button>
  <button onclick="barraAcciones.style.display='none'" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:18px;">×</button>
</div>

<script>
// ── Navegación a fila ──────────────────────────────────────
document.querySelectorAll('.ci-row').forEach(tr => {
    tr.style.cursor = 'pointer';
    tr.addEventListener('click', () => {
        const id = tr.dataset.id;
        window.location.href = '<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=' + id;
    });
});

// ── Vista tabla / grid ─────────────────────────────────────
function setVista(v) {
    const tabla = document.getElementById('vistaTabla');
    const grid  = document.getElementById('vistaGrid');
    const btnT  = document.getElementById('btnTabla');
    const btnG  = document.getElementById('btnGrid');
    if (v === 'grid') {
        tabla.style.display = 'none'; grid.style.display = 'block';
        btnT.style.background = 'white'; btnT.style.color = '#64748B';
        btnG.style.background = '#1A3A5C'; btnG.style.color = 'white';
    } else {
        tabla.style.display = 'block'; grid.style.display = 'none';
        btnT.style.background = '#1A3A5C'; btnT.style.color = 'white';
        btnG.style.background = 'white'; btnG.style.color = '#64748B';
    }
    localStorage.setItem('cmdb_vista', v);
}
// Restaurar vista
const vistaGuardada = localStorage.getItem('cmdb_vista');
if (vistaGuardada) setVista(vistaGuardada);

// ── Select all ─────────────────────────────────────────────
function toggleAll(cb) {
    document.querySelectorAll('.ci-check').forEach(c => c.checked = cb.checked);
    actualizarBarra();
}
document.querySelectorAll('.ci-check').forEach(c => {
    c.addEventListener('change', actualizarBarra);
});
const barraAcciones = document.getElementById('barraAcciones');
function actualizarBarra() {
    const sel = document.querySelectorAll('.ci-check:checked').length;
    document.getElementById('selCount').textContent = sel + ' seleccionados';
    barraAcciones.style.display = sel > 0 ? 'flex' : 'none';
}

// ── Filtro rápido desde KPIs ───────────────────────────────
function setFiltro(campo, valor) {
    const url = new URL(window.location.href);
    url.searchParams.set(campo, valor);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// ── Confirmar eliminar ─────────────────────────────────────
function confirmarEliminar(id, nombre) {
    if (confirm(`¿Eliminar el CI "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
        window.location.href = '<?= BASE_URL ?>/modules/cmdb/ci_action.php?accion=eliminar&id=' + id;
    }
}

// ── Acción masiva ──────────────────────────────────────────
function accionMasiva(accion) {
    const ids = [...document.querySelectorAll('.ci-check:checked')].map(c => c.value);
    if (!ids.length) return;
    const msgs = { baja: 'dar de baja', almacen: 'mover a almacén' };
    if (confirm(`¿${msgs[accion]} los ${ids.length} CIs seleccionados?`)) {
        window.location.href = '<?= BASE_URL ?>/modules/cmdb/ci_action.php?accion=' + accion + '&ids=' + ids.join(',');
    }
}

// ── Exportar CSV ───────────────────────────────────────────
function exportarCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '<?= BASE_URL ?>/modules/cmdb/ci_export.php?' + params.toString();
}
</script>

<?php endLayout(); ?>
