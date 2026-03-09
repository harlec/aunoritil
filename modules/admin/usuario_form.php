<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
Auth::requireCan('admin.*');

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$u      = [];

if ($editar) {
    $u = DB::row("SELECT * FROM adm_usuarios WHERE id=?", [$id]);
    if (!$u) { flash('error','Usuario no encontrado'); redirect(BASE_URL.'/modules/admin/usuarios.php'); }
}

$pageTitle  = $editar ? 'Editar — '.$u['nombre'] : 'Nuevo Usuario';
$pageModule = 'admin';

$roles  = DB::query("SELECT id, nombre, descripcion FROM adm_roles ORDER BY nombre");
$grupos = DB::query("SELECT id, nombre FROM adm_grupos ORDER BY nombre");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $nombre   = postClean('nombre');
    $email    = postClean('email');
    $tipo     = postClean('tipo');
    $rolId    = postInt('rol_id');
    $grupoId  = postInt('grupo_id') ?: null;
    $password = post('password');
    $password2= post('password2');
    $activo   = isset($_POST['activo']) ? 1 : 0;
    $telefono = postClean('telefono');
    $cargo    = postClean('cargo');
    // sede es FK, se omite en form básico

    $errores = [];
    if (empty($nombre))  $errores[] = 'El nombre es obligatorio.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido.';
    if (!$rolId)         $errores[] = 'Selecciona un rol.';

    // Email único
    $existe = DB::value("SELECT id FROM adm_usuarios WHERE email=? AND id!=?", [$email, $id]);
    if ($existe) $errores[] = "El email '{$email}' ya está en uso.";

    // Contraseña
    if (!$editar) {
        if (empty($password))        $errores[] = 'La contraseña es obligatoria para nuevos usuarios.';
        elseif (strlen($password)<8) $errores[] = 'La contraseña debe tener mínimo 8 caracteres.';
        elseif ($password!==$password2) $errores[] = 'Las contraseñas no coinciden.';
    } elseif (!empty($password)) {
        if (strlen($password)<8)     $errores[] = 'La contraseña debe tener mínimo 8 caracteres.';
        elseif ($password!==$password2) $errores[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errores)) {
        $datos = [
            'nombre'   => $nombre,
            'email'    => $email,
            'tipo'     => $tipo,
            'rol_id'   => $rolId,
            'grupo_id' => $grupoId,
            'activo'   => $activo,
            'telefono' => $telefono,
            'cargo'    => $cargo,
            // sede_id se asigna desde CMDB
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($password)) {
            $datos['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
        }

        if ($editar) {
            DB::updateRow('adm_usuarios', $datos, 'id=?', [$id]);
            Audit::log('adm_usuarios', $id, 'EDITAR');
            flash('success', 'Usuario actualizado correctamente.');
        } else {
            $datos['created_at'] = date('Y-m-d H:i:s');
            $newId = DB::insertRow('adm_usuarios', $datos);
            Audit::log('adm_usuarios', $newId, 'CREAR');
            flash('success', "Usuario {$nombre} creado. Ya puede iniciar sesión.");
            redirect(BASE_URL . "/modules/admin/usuario_detalle.php?id={$newId}");
        }
        redirect(BASE_URL . '/modules/admin/usuarios.php');
    } else {
        foreach ($errores as $e) flash('error', $e);
    }
}

// Permisos del rol seleccionado (para preview)
$rolActual = $editar && $u['rol_id'] ? DB::row("SELECT * FROM adm_roles WHERE id=?", [$u['rol_id']]) : null;

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <i class="fas fa-<?= $editar?'pen':'user-plus' ?>" style="color:#6A1B9A;margin-right:10px;"></i>
      <?= $editar ? h($u['nombre']) : 'Nuevo Usuario' ?>
    </h1>
    <p class="page-subtitle"><?= $editar ? 'Editar datos y accesos del usuario' : 'Crear nuevo usuario en el sistema' ?></p>
  </div>
  <a href="<?= BASE_URL ?>/modules/admin/usuarios.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<form method="POST" id="formUsuario">
  <?= Auth::csrfInput() ?>
  <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Datos personales -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-user" style="color:#6A1B9A;margin-right:8px;"></i>Datos personales</span></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group" style="grid-column:span 2;">
              <label class="form-label">Nombre completo <span class="req">*</span></label>
              <input type="text" name="nombre" class="form-control" value="<?= h($u['nombre']??'') ?>" required placeholder="Nombre y apellidos">
            </div>
            <div class="form-group">
              <label class="form-label">Email <span class="req">*</span></label>
              <input type="email" name="email" class="form-control" value="<?= h($u['email']??'') ?>" required placeholder="usuario@aleatica.pe">
            </div>
            <div class="form-group">
              <label class="form-label">Teléfono / Anexo</label>
              <input type="text" name="telefono" class="form-control" value="<?= h($u['telefono']??'') ?>" placeholder="Anexo o celular">
            </div>
            <div class="form-group">
              <label class="form-label">Cargo / Puesto</label>
              <input type="text" name="cargo" class="form-control" value="<?= h($u['cargo']??'') ?>" placeholder="Ej: Analista de Sistemas">
            </div>
            <div class="form-group">
              <label class="form-label">Sede</label>
              <input type="text" name="sede" class="form-control" value="<?= h($u['sede']??'') ?>" placeholder="Ej: Lima, Tramo Norte">
            </div>
          </div>
        </div>
      </div>

      <!-- Acceso y rol -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-shield-halved" style="color:#1565C0;margin-right:8px;"></i>Acceso al sistema</span></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Tipo de usuario <span class="req">*</span></label>
              <select name="tipo" class="form-control" id="selTipo" onchange="actualizarTipoInfo()">
                <?php foreach(['Admin','Supervisor','Agente','Usuario_Final'] as $t): ?>
                <option value="<?= $t ?>" <?= ($u['tipo']??'Agente')===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Rol <span class="req">*</span></label>
              <select name="rol_id" class="form-control" id="selRol" onchange="mostrarPermisos(this.value)">
                <option value="">Seleccionar rol...</option>
                <?php foreach($roles as $r): ?>
                <option value="<?= $r['id'] ?>"
                        data-desc="<?= h($r['descripcion']??'') ?>"
                        <?= ($u['rol_id']??0)===$r['id']?'selected':'' ?>>
                  <?= h($r['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Grupo de soporte</label>
              <select name="grupo_id" class="form-control">
                <option value="">Sin grupo asignado</option>
                <?php foreach($grupos as $g): ?>
                <option value="<?= $g['id'] ?>" <?= ($u['grupo_id']??0)===$g['id']?'selected':'' ?>>
                  <?= h($g['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:500;color:#475569;">
                <input type="checkbox" name="activo" value="1" <?= ($u['activo']??1)?'checked':'' ?>
                       style="width:18px;height:18px;accent-color:#1A3A5C;">
                Usuario activo (puede iniciar sesión)
              </label>
            </div>
          </div>

          <!-- Info del tipo -->
          <div id="tipoInfo" style="margin-top:8px;padding:10px 14px;border-radius:8px;font-size:12px;display:none;"></div>

          <!-- Info del rol -->
          <?php if($rolActual): ?>
          <div id="rolInfo" style="margin-top:10px;background:#EDE7F6;border-radius:10px;padding:12px 14px;font-size:12px;color:#4A148C;">
            <i class="fas fa-shield-halved" style="margin-right:6px;"></i>
            <strong><?= h($rolActual['nombre']) ?></strong>
            <?php if($rolActual['descripcion']): ?> — <?= h($rolActual['descripcion']) ?><?php endif; ?>
          </div>
          <?php else: ?>
          <div id="rolInfo" style="display:none;margin-top:10px;background:#EDE7F6;border-radius:10px;padding:12px 14px;font-size:12px;color:#4A148C;"></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Contraseña -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-lock" style="color:#E65100;margin-right:8px;"></i>Contraseña</span>
          <?php if($editar): ?>
          <span style="font-size:12px;color:#94A3B8;">Dejar vacío para mantener la actual</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">
                <?= $editar ? 'Nueva contraseña' : 'Contraseña' ?> <span class="req" <?= $editar?'style="display:none;"':'' ?>>*</span>
              </label>
              <div style="position:relative;">
                <input type="password" name="password" id="passInput" class="form-control"
                       placeholder="Mínimo 8 caracteres" <?= !$editar?'required':'' ?>>
                <button type="button" id="btnShowPass"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94A3B8;font-size:15px;">
                  <i class="fas fa-eye" id="passIcon"></i>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Confirmar contraseña <?php if(!$editar): ?><span class="req">*</span><?php endif; ?></label>
              <input type="password" name="password2" id="pass2Input" class="form-control"
                     placeholder="Repetir contraseña" <?= !$editar?'required':'' ?>>
            </div>
          </div>

          <!-- Indicador de fortaleza -->
          <div id="passStrength" style="display:none;margin-top:8px;">
            <div style="display:flex;gap:4px;margin-bottom:4px;">
              <?php for($i=0;$i<4;$i++): ?>
              <div class="strength-bar" style="flex:1;height:4px;background:#E2E8F0;border-radius:2px;transition:background .2s;"></div>
              <?php endfor; ?>
            </div>
            <span id="strengthLabel" style="font-size:11px;color:#94A3B8;"></span>
          </div>
        </div>
      </div>

    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:80px;">
      <div class="card">
        <div class="card-body" style="padding:16px;">
          <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:10px;">
            <i class="fas fa-<?= $editar?'floppy-disk':'user-plus' ?>"></i>
            <?= $editar ? 'Guardar cambios' : 'Crear usuario' ?>
          </button>
          <a href="usuarios.php" class="btn btn-ghost" style="width:100%;">Cancelar</a>
        </div>
      </div>

      <!-- Tipos de usuario explicados -->
      <div class="card">
        <div class="card-header"><span class="card-title" style="font-size:12px;">Tipos de usuario</span></div>
        <div class="card-body" style="padding:12px 16px;">
          <?php $tipos = [
            ['Admin',        '#6A1B9A','fa-crown',    'Acceso total al sistema'],
            ['Supervisor',   '#1565C0','fa-binoculars','Ve todos los tickets y reportes'],
            ['Agente',       '#00695C','fa-headset',  'Gestiona tickets asignados'],
            ['Usuario_Final','#616161','fa-user',     'Solo portal de autoservicio'],
          ]; ?>
          <?php foreach($tipos as [$t,$c,$i,$desc]): ?>
          <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #F8FAFC;">
            <div style="width:28px;height:28px;border-radius:7px;background:<?= $c ?>15;color:<?= $c ?>;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;">
              <i class="fas <?= $i ?>"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:600;color:#0F172A;"><?= str_replace('_',' ',$t) ?></div>
              <div style="font-size:11px;color:#94A3B8;"><?= $desc ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if($editar): ?>
      <div class="card">
        <div class="card-body" style="padding:14px 16px;display:flex;flex-direction:column;gap:8px;">
          <a href="usuario_detalle.php?id=<?= $id ?>" class="btn btn-ghost" style="width:100%;">
            <i class="fas fa-eye"></i> Ver perfil completo
          </a>
          <a href="usuario_detalle.php?id=<?= $id ?>&tab=equipos" class="btn btn-ghost" style="width:100%;">
            <i class="fas fa-server"></i> Ver equipos asignados
          </a>
          <?php if($id !== Auth::userId()): ?>
          <button onclick="toggleActivo(<?= $id ?>, <?= $u['activo'] ?>, '<?= h(addslashes($u['nombre'])) ?>')"
                  type="button"
                  class="btn btn-ghost" style="width:100%;color:<?= $u['activo']?'#EF5350':'#4CAF50' ?>;">
            <i class="fas fa-<?= $u['activo']?'ban':'circle-check' ?>"></i>
            <?= $u['activo'] ? 'Desactivar usuario' : 'Activar usuario' ?>
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<script>
// ── Mostrar / ocultar contraseña ───────────────────────────
const passInput = document.getElementById('passInput');
const passIcon  = document.getElementById('passIcon');
document.getElementById('btnShowPass').onclick = () => {
    const visible = passInput.type === 'text';
    passInput.type = visible ? 'password' : 'text';
    passIcon.className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
};

// ── Fortaleza de contraseña ────────────────────────────────
passInput.addEventListener('input', () => {
    const val = passInput.value;
    const div = document.getElementById('passStrength');
    div.style.display = val.length > 0 ? 'block' : 'none';
    const bars  = document.querySelectorAll('.strength-bar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const colors = ['#EF5350','#FF6B00','#F9A825','#4CAF50'];
    const labels = ['Muy débil','Débil','Buena','Fuerte'];
    bars.forEach((b,i) => b.style.background = i < score ? colors[score-1] : '#E2E8F0');
    label.textContent = labels[score-1] || '';
    label.style.color = colors[score-1] || '#94A3B8';
});

// ── Info del tipo de usuario ───────────────────────────────
const tipoDescripciones = {
    'Admin':         {color:'#6A1B9A', bg:'#F3E5F5', msg:'⚠️ Acceso total al sistema. Puede modificar configuraciones críticas.'},
    'Supervisor':    {color:'#1565C0', bg:'#E3F2FD', msg:'Puede ver todos los tickets y generar reportes. No puede cambiar configuración del sistema.'},
    'Agente':        {color:'#00695C', bg:'#E0F2F1', msg:'Gestiona los tickets asignados a su grupo. Acceso a CMDB y proveedores según rol.'},
    'Usuario_Final': {color:'#616161', bg:'#F5F5F5', msg:'Solo accede al portal de autoservicio. No ve el panel de administración.'},
};
function actualizarTipoInfo() {
    const tipo = document.getElementById('selTipo').value;
    const info = tipoDescripciones[tipo];
    const div  = document.getElementById('tipoInfo');
    if (info) {
        div.style.display = 'block';
        div.style.background = info.bg;
        div.style.color = info.color;
        div.innerHTML = `<i class="fas fa-circle-info" style="margin-right:6px;"></i>${info.msg}`;
    }
}
actualizarTipoInfo();

// ── Info del rol seleccionado ──────────────────────────────
function mostrarPermisos(rolId) {
    const sel  = document.getElementById('selRol');
    const opt  = sel.options[sel.selectedIndex];
    const desc = opt?.getAttribute('data-desc') || '';
    const div  = document.getElementById('rolInfo');
    if (desc) {
        div.style.display = 'block';
        div.innerHTML = `<i class="fas fa-shield-halved" style="margin-right:6px;"></i><strong>${opt.text}</strong> — ${desc}`;
    } else {
        div.style.display = 'none';
    }
}

// ── Toggle activo ──────────────────────────────────────────
function toggleActivo(id, activo, nombre) {
    const accion = activo ? 'desactivar' : 'activar';
    if (confirm(`¿${accion} al usuario "${nombre}"?`)) {
        window.location.href = 'usuario_action.php?accion=toggle_activo&id=' + id;
    }
}
</script>

<?php endLayout(); ?>
