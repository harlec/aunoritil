<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$p      = [];

if ($editar) {
    $p = DB::row("SELECT * FROM sup_proveedores WHERE id=?", [$id]);
    if (!$p) { flash('error','Proveedor no encontrado'); redirect(BASE_URL.'/modules/proveedores/proveedores.php'); }
}

$pageTitle  = $editar ? 'Editar — '.$p['nombre'] : 'Nuevo Proveedor';
$pageModule = 'proveedores';

// Contactos existentes
$contactos = $editar ? DB::query("SELECT * FROM sup_contactos WHERE proveedor_id=? ORDER BY principal DESC, nombre", [$id]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $datos = [
        'nombre'       => postClean('nombre'),
        'nombre_corto' => postClean('nombre_corto'),
        'ruc'          => postClean('ruc'),
        'tipo'         => postClean('tipo'),
        'categoria'    => postClean('categoria'),
        'pais'         => postClean('pais') ?: 'Peru',
        'direccion'    => postClean('direccion'),
        'web'          => postClean('web'),
        'estado'       => postClean('estado'),
        'notas'        => postClean('notas'),
        'activo'       => 1,
        'updated_at'   => date('Y-m-d H:i:s'),
    ];

    if (empty($datos['nombre'])) { flash('error','El nombre es obligatorio.'); }
    else {
        if ($editar) {
            DB::updateRow('sup_proveedores', $datos, 'id=?', [$id]);
            Audit::log('sup_proveedores', $id, 'EDITAR', $p, $datos);
            flash('success', 'Proveedor actualizado.');
        } else {
            $datos['created_by'] = Auth::userId();
            $id = DB::insertRow('sup_proveedores', $datos);
            Audit::log('sup_proveedores', $id, 'CREAR', null, $datos);
            flash('success', 'Proveedor creado.');
        }
        redirect(BASE_URL . "/modules/proveedores/proveedor_detalle.php?id={$id}");
    }
}

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <i class="fas fa-<?= $editar?'pen':'plus-circle' ?>" style="color:#E65100;margin-right:10px;"></i>
      <?= $editar ? h($p['nombre']) : 'Nuevo Proveedor' ?>
    </h1>
    <p class="page-subtitle"><?= $editar ? 'Editar datos del proveedor' : 'Registrar nuevo proveedor o partner' ?></p>
  </div>
  <a href="<?= BASE_URL ?>/modules/proveedores/proveedores.php" class="btn btn-ghost">
    <i class="fas fa-arrow-left"></i> Volver
  </a>
</div>

<form method="POST" id="formProv">
  <?= Auth::csrfInput() ?>
  <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Datos generales -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-building" style="color:#E65100;margin-right:8px;"></i>Datos Generales</span></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Razón social / Nombre <span class="req">*</span></label>
              <input type="text" name="nombre" class="form-control" value="<?= h($p['nombre']??'') ?>" required placeholder="Nombre completo de la empresa">
            </div>
            <div class="form-group">
              <label class="form-label">Nombre corto</label>
              <input type="text" name="nombre_corto" class="form-control" value="<?= h($p['nombre_corto']??'') ?>" placeholder="Alias o abreviatura">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">RUC / NIF</label>
              <input type="text" name="ruc" class="form-control mono" value="<?= h($p['ruc']??'') ?>" placeholder="20XXXXXXXXXXX">
            </div>
            <div class="form-group">
              <label class="form-label">Tipo <span class="req">*</span></label>
              <select name="tipo" class="form-control">
                <?php foreach(['Fabricante','Distribuidor','ISP','Soporte','SaaS','Consultor','Otro'] as $t): ?>
                <option value="<?= $t ?>" <?= ($p['tipo']??'')===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">País</label>
              <input type="text" name="pais" class="form-control" value="<?= h($p['pais']??'Peru') ?>">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Categoría / Especialidad</label>
              <input type="text" name="categoria" class="form-control" value="<?= h($p['categoria']??'') ?>" placeholder="Networking, Cómputo, Seguridad...">
            </div>
            <div class="form-group">
              <label class="form-label">Sitio web</label>
              <input type="url" name="web" class="form-control" value="<?= h($p['web']??'') ?>" placeholder="https://proveedor.com">
            </div>
            <div class="form-group" style="grid-column:span 2;">
              <label class="form-label">Dirección</label>
              <input type="text" name="direccion" class="form-control" value="<?= h($p['direccion']??'') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="2"><?= h($p['notas']??'') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Contactos -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-address-book" style="color:#4A90C4;margin-right:8px;"></i>Contactos</span>
          <?php if($editar): ?>
          <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modalContacto')">
            <i class="fas fa-plus"></i> Agregar
          </button>
          <?php endif; ?>
        </div>
        <?php if($editar && !empty($contactos)): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Nombre</th><th>Cargo</th><th>Tipo</th><th>Email</th><th>Teléfono</th><th>Principal</th><th></th></tr></thead>
            <tbody>
              <?php foreach($contactos as $c): ?>
              <tr>
                <td style="font-weight:600;"><?= h($c['nombre']) ?></td>
                <td style="font-size:12px;color:#64748B;"><?= h($c['cargo']??'') ?></td>
                <td><span style="font-size:11px;background:#F1F5F9;padding:2px 8px;border-radius:6px;"><?= $c['tipo'] ?></span></td>
                <td style="font-size:12px;"><?= h($c['email']??'') ?></td>
                <td style="font-size:12px;" class="mono"><?= h($c['telefono']??'') ?></td>
                <td><?php if($c['principal']): ?><span style="color:#4CAF50;font-size:18px;">★</span><?php endif; ?></td>
                <td>
                  <button onclick="eliminarContacto(<?= $c['id'] ?>)" class="btn btn-ghost btn-sm btn-icon" type="button" style="color:#EF5350;"><i class="fas fa-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php elseif($editar): ?>
        <div class="empty-state" style="padding:24px;"><i class="fas fa-address-book"></i><p>Sin contactos registrados</p></div>
        <?php else: ?>
        <div class="card-body" style="padding:12px 16px;">
          <p style="font-size:13px;color:#94A3B8;">Guarda el proveedor primero para agregar contactos.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:80px;">
      <div class="card">
        <div class="card-body" style="padding:16px;">
          <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:10px;">
            <i class="fas fa-<?= $editar?'floppy-disk':'plus' ?>"></i>
            <?= $editar ? 'Guardar cambios' : 'Crear proveedor' ?>
          </button>
          <a href="<?= BASE_URL ?>/modules/proveedores/proveedores.php" class="btn btn-ghost" style="width:100%;">Cancelar</a>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Estado</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-control">
              <?php foreach(['Activo','Inactivo','Bloqueado'] as $e): ?>
              <option value="<?= $e ?>" <?= ($p['estado']??'Activo')===$e?'selected':'' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <?php if($editar): ?>
      <div class="card">
        <div class="card-body" style="padding:14px 16px;display:flex;flex-direction:column;gap:8px;">
          <a href="contratos.php?proveedor=<?= $id ?>" class="btn btn-ghost" style="width:100%;">
            <i class="fas fa-file-contract"></i> Ver contratos
          </a>
          <a href="<?= BASE_URL ?>/modules/finanzas/facturas.php?proveedor=<?= $id ?>" class="btn btn-ghost" style="width:100%;">
            <i class="fas fa-file-invoice"></i> Ver facturas
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<!-- Modal contacto -->
<?php if($editar): ?>
<div class="modal-overlay" id="modalContacto">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">Agregar Contacto</span>
      <button class="modal-close" onclick="closeModal('modalContacto')"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST" action="contacto_action.php">
      <?= Auth::csrfInput() ?>
      <input type="hidden" name="accion" value="crear">
      <input type="hidden" name="proveedor_id" value="<?= $id ?>">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group" style="grid-column:span 2;">
            <label class="form-label">Nombre <span class="req">*</span></label>
            <input type="text" name="nombre" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Cargo</label>
            <input type="text" name="cargo" class="form-control" placeholder="Account Manager">
          </div>
          <div class="form-group">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-control">
              <?php foreach(['Comercial','Tecnico','Emergencia','Facturacion','General'] as $t): ?>
              <option value="<?= $t ?>"><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control">
          </div>
          <div class="form-group" style="grid-column:span 2;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:500;">
              <input type="checkbox" name="principal" value="1" style="width:16px;height:16px;accent-color:#1A3A5C;"> Contacto principal
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalContacto')">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Agregar</button>
      </div>
    </form>
  </div>
</div>
<script>
function eliminarContacto(id) {
    if (confirm('¿Eliminar este contacto?')) {
        window.location.href = 'contacto_action.php?accion=eliminar&id='+id+'&proveedor=<?= $id ?>';
    }
}
</script>
<?php endif; ?>

<?php endLayout(); ?>
