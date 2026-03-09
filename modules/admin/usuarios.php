<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
Auth::requireCan('admin.*');

$pageTitle  = 'Usuarios';
$pageModule = 'admin';

$buscar   = clean($_GET['q']      ?? '');
$filRol   = (int)($_GET['rol']    ?? 0);
$filTipo  = clean($_GET['tipo']   ?? '');
$filActivo= clean($_GET['activo'] ?? '');
$page     = max(1,(int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($buscar)    { $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)'; $like="%{$buscar}%"; $params=array_merge($params,[$like,$like]); }
if ($filRol)    { $where[] = 'u.rol_id = ?';   $params[] = $filRol; }
if ($filTipo)   { $where[] = 'u.tipo = ?';     $params[] = $filTipo; }
if ($filActivo !== '') { $where[] = 'u.activo = ?'; $params[] = (int)$filActivo; }

$sql = "SELECT u.*,
        r.nombre AS rol_nombre,
        g.nombre AS grupo_nombre,
        COUNT(DISTINCT c.id) AS total_equipos
        FROM adm_usuarios u
        LEFT JOIN adm_roles r        ON r.id = u.rol_id
        LEFT JOIN adm_grupos g ON g.id = u.grupo_id
        LEFT JOIN cmdb_cis c          ON c.propietario_id = u.id AND c.estado = 'Activo'
        WHERE " . implode(' AND ', $where) . "
        GROUP BY u.id
        ORDER BY u.activo DESC, u.nombre ASC";

$resultado = DB::paginate($sql, $params, $page, 20);
$usuarios  = $resultado['data'];

$roles  = DB::query("SELECT id, nombre FROM adm_roles ORDER BY nombre");
$grupos = DB::query("SELECT id, nombre FROM adm_grupos ORDER BY nombre");

// KPIs
$kpis = [
    'total'    => DB::value("SELECT COUNT(*) FROM adm_usuarios"),
    'activos'  => DB::value("SELECT COUNT(*) FROM adm_usuarios WHERE activo=1"),
    'agentes'  => DB::value("SELECT COUNT(*) FROM adm_usuarios WHERE tipo IN ('Agente','Admin') AND activo=1"),
    'sin_login'=> DB::value("SELECT COUNT(*) FROM adm_usuarios WHERE ultimo_login IS NULL AND activo=1"),
];

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-users" style="color:#6A1B9A;margin-right:10px;"></i>Usuarios</h1>
    <p class="page-subtitle">Gestión de accesos, roles y asignación de equipos</p>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= BASE_URL ?>/modules/admin/roles.php" class="btn btn-ghost">
      <i class="fas fa-shield-halved"></i> Roles y permisos
    </a>
    <a href="<?= BASE_URL ?>/modules/admin/usuario_form.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Nuevo usuario
    </a>
  </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#F3E5F5;color:#6A1B9A;"><i class="fas fa-users"></i></div>
    <div class="kpi-label">Total usuarios</div>
    <div class="kpi-value" style="color:#6A1B9A;"><?= $kpis['total'] ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltroActivo(1)">
    <div class="kpi-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-circle-check"></i></div>
    <div class="kpi-label">Activos</div>
    <div class="kpi-value" style="color:#2E7D32;"><?= $kpis['activos'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#E3F2FD;color:#1565C0;"><i class="fas fa-headset"></i></div>
    <div class="kpi-label">Agentes / Admin</div>
    <div class="kpi-value" style="color:#1565C0;"><?= $kpis['agentes'] ?></div>
  </div>
  <div class="kpi-card" style="cursor:pointer;" onclick="setFiltroActivo(1)">
    <div class="kpi-icon" style="background:<?= $kpis['sin_login']>0?'#FFF3E0':'#F5F5F5' ?>;color:<?= $kpis['sin_login']>0?'#E65100':'#616161' ?>;"><i class="fas fa-clock"></i></div>
    <div class="kpi-label">Sin primer login</div>
    <div class="kpi-value"><?= $kpis['sin_login'] ?></div>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:14px 16px;">
    <form method="GET" id="formFiltros" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div style="flex:2;min-width:200px;position:relative;">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;pointer-events:none;"></i>
        <input type="text" name="q" value="<?= h($buscar) ?>" placeholder="Nombre o email..." class="form-control" style="padding-left:34px;">
      </div>
      <div style="min-width:150px;">
        <select name="rol" class="form-control" onchange="this.form.submit()">
          <option value="">Todos los roles</option>
          <?php foreach($roles as $r): ?>
          <option value="<?= $r['id'] ?>" <?= $filRol===$r['id']?'selected':'' ?>><?= h($r['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:140px;">
        <select name="tipo" class="form-control" onchange="this.form.submit()">
          <option value="">Todos los tipos</option>
          <?php foreach(['Admin','Agente','Supervisor','Usuario_Final'] as $t): ?>
          <option value="<?= $t ?>" <?= $filTipo===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:120px;">
        <select name="activo" class="form-control" id="selActivo" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="1" <?= $filActivo==='1'?'selected':'' ?>>Activos</option>
          <option value="0" <?= $filActivo==='0'?'selected':'' ?>>Inactivos</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-magnifying-glass"></i></button>
        <?php if($buscar||$filRol||$filTipo||$filActivo!==''): ?>
        <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-xmark"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Tabla -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= numero($resultado['total']) ?> usuarios</span>
  </div>
  <div class="table-wrap">
    <?php if(empty($usuarios)): ?>
    <div class="empty-state"><i class="fas fa-users"></i><p>No se encontraron usuarios</p></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Tipo</th>
          <th>Rol</th>
          <th>Grupo</th>
          <th>Equipos asignados</th>
          <th>Último login</th>
          <th>Estado</th>
          <th style="width:100px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($usuarios as $u):
          $inicial = strtoupper(substr($u['nombre'],0,2));
          $avatarColors = ['#1A3A5C','#00695C','#6A1B9A','#E65100','#1565C0','#2E7D32'];
          $aColor = $avatarColors[crc32($u['email']) % count($avatarColors)];
        ?>
        <tr style="cursor:pointer;" onclick="location.href='usuario_detalle.php?id=<?= $u['id'] ?>'">
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;border-radius:10px;background:<?= $aColor ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">
                <?= $inicial ?>
              </div>
              <div>
                <div style="font-weight:600;color:#0F172A;"><?= h($u['nombre']) ?></div>
                <div style="font-size:12px;color:#94A3B8;"><?= h($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <?php
            $tipoStyle = match($u['tipo']) {
                'Admin'         => ['#F3E5F5','#6A1B9A'],
                'Agente'        => ['#E3F2FD','#1565C0'],
                'Supervisor'    => ['#E0F2F1','#00695C'],
                'Usuario_Final' => ['#F5F5F5','#616161'],
                default         => ['#F5F5F5','#616161'],
            };
            ?>
            <span class="badge" style="background:<?= $tipoStyle[0] ?>;color:<?= $tipoStyle[1] ?>;border:1px solid <?= $tipoStyle[1] ?>30;">
              <?= str_replace('_',' ',$u['tipo']) ?>
            </span>
          </td>
          <td style="font-size:13px;color:#475569;"><?= h($u['rol_nombre'] ?? '—') ?></td>
          <td style="font-size:12px;color:#64748B;"><?= h($u['grupo_nombre'] ?? '—') ?></td>
          <td>
            <?php if($u['total_equipos'] > 0): ?>
            <span style="display:inline-flex;align-items:center;gap:6px;background:#E0F2F1;color:#00695C;font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px;cursor:pointer;"
                  onclick="event.stopPropagation();location.href='usuario_detalle.php?id=<?= $u['id'] ?>&tab=equipos'">
              <i class="fas fa-server" style="font-size:10px;"></i> <?= $u['total_equipos'] ?>
            </span>
            <?php else: ?>
            <span style="color:#CBD5E1;font-size:12px;">Sin equipos</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#64748B;">
            <?= $u['ultimo_login'] ? tiempoRelativo($u['ultimo_login']) : '<span style="color:#F9A825;">Nunca</span>' ?>
          </td>
          <td>
            <?php if($u['activo']): ?>
            <span class="badge" style="background:#E8F5E9;color:#2E7D32;border:1px solid #A5D6A730;">Activo</span>
            <?php else: ?>
            <span class="badge" style="background:#FFEBEE;color:#EF5350;border:1px solid #FFCDD230;">Inactivo</span>
            <?php endif; ?>
          </td>
          <td onclick="event.stopPropagation()">
            <div style="display:flex;gap:4px;">
              <a href="usuario_detalle.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Ver"><i class="fas fa-eye"></i></a>
              <a href="usuario_form.php?id=<?= $u['id'] ?>"   class="btn btn-ghost btn-sm btn-icon" title="Editar"><i class="fas fa-pen"></i></a>
              <?php if($u['id'] !== Auth::userId()): ?>
              <button onclick="toggleActivo(<?= $u['id'] ?>, <?= $u['activo'] ?>, '<?= h(addslashes($u['nombre'])) ?>')"
                      class="btn btn-ghost btn-sm btn-icon" title="<?= $u['activo']?'Desactivar':'Activar' ?>"
                      style="color:<?= $u['activo']?'#EF5350':'#4CAF50' ?>;">
                <i class="fas fa-<?= $u['activo']?'ban':'circle-check' ?>"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if($resultado['total_pages']>1): ?>
  <div style="padding:0 16px 4px;"><?= paginator($resultado['total'],20,$page,BASE_URL.'/modules/admin/usuarios.php?'.http_build_query(array_filter(['q'=>$buscar,'rol'=>$filRol,'tipo'=>$filTipo,'activo'=>$filActivo]))) ?></div>
  <?php endif; ?>
</div>

<script>
function setFiltroActivo(val) {
    document.getElementById('selActivo').value = val;
    document.getElementById('formFiltros').submit();
}
function toggleActivo(id, activo, nombre) {
    const accion = activo ? 'desactivar' : 'activar';
    if (confirm(`¿${accion} al usuario "${nombre}"?`)) {
        window.location.href = 'usuario_action.php?accion=toggle_activo&id=' + id;
    }
}
</script>

<?php endLayout(); ?>
