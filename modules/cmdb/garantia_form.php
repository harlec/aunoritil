<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id    = (int)($_GET['id']    ?? 0);
$ciId  = (int)($_GET['ci_id'] ?? 0);
$g     = [];
$editar = $id > 0;

if ($editar) {
    $g = DB::row("SELECT * FROM cmdb_garantias WHERE id=?", [$id]);
    if (!$g) { flash('error','Garantía no encontrada'); redirect(BASE_URL.'/modules/cmdb/cis.php'); }
    $ciId = $g['ci_id'];
}
$ci = DB::row("SELECT id, nombre, codigo_ci FROM cmdb_cis WHERE id=?", [$ciId]);
if (!$ci) { flash('error','CI no encontrado'); redirect(BASE_URL.'/modules/cmdb/cis.php'); }

$pageTitle  = ($editar ? 'Editar' : 'Nueva') . ' Garantía';
$pageModule = 'cmdb';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $datos = [
        'ci_id'             => $ciId,
        'tipo'              => postClean('tipo'),
        'proveedor_texto'   => postClean('proveedor_texto'),
        'numero_caso'       => postClean('numero_caso'),
        'fecha_inicio'      => post('fecha_inicio') ?: null,
        'fecha_fin'         => post('fecha_fin'),
        'cobertura'         => postClean('cobertura'),
        'sla_respuesta_hrs' => postInt('sla_respuesta_hrs') ?: null,
        'contacto_soporte'  => postClean('contacto_soporte'),
        'telefono_soporte'  => postClean('telefono_soporte'),
        'email_soporte'     => postClean('email_soporte'),
        'url_portal'        => postClean('url_portal'),
        'notas'             => postClean('notas'),
        'updated_at'        => date('Y-m-d H:i:s'),
    ];
    if (empty($datos['fecha_fin'])) { flash('error','La fecha de fin es obligatoria.'); }
    else {
        if ($editar) {
            DB::updateRow('cmdb_garantias', $datos, 'id=?', [$id]);
            flash('success', 'Garantía actualizada.');
        } else {
            $datos['created_by'] = Auth::userId();
            DB::insertRow('cmdb_garantias', $datos);
            // Actualizar fecha_garantia_fin en el CI si es más reciente
            DB::exec("UPDATE cmdb_cis SET fecha_garantia_fin=? WHERE id=? AND (fecha_garantia_fin IS NULL OR fecha_garantia_fin < ?)",
                [$datos['fecha_fin'], $ciId, $datos['fecha_fin']]);
            flash('success', 'Garantía registrada.');
        }
        redirect(BASE_URL . "/modules/cmdb/ci_detalle.php?id={$ciId}&tab=garantias");
    }
}

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-shield-halved" style="color:#FF6B00;margin-right:10px;"></i><?= $editar ? 'Editar Garantía' : 'Nueva Garantía' ?></h1>
    <p class="page-subtitle">CI: <strong><?= h($ci['codigo_ci']) ?></strong> — <?= h($ci['nombre']) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $ciId ?>&tab=garantias" class="btn btn-ghost">
    <i class="fas fa-arrow-left"></i> Volver
  </a>
</div>

<div style="max-width:700px;">
<form method="POST">
  <?= Auth::csrfInput() ?>
  <div class="card">
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Tipo <span class="req">*</span></label>
          <select name="tipo" class="form-control">
            <?php foreach(['Fabricante','Extendida','Contrato_Mantenimiento','Soporte_SW'] as $t): ?>
            <option value="<?= $t ?>" <?= ($g['tipo']??'Fabricante')===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Proveedor de soporte</label>
          <input type="text" name="proveedor_texto" class="form-control" value="<?= h($g['proveedor_texto']??'') ?>" placeholder="Dell Support, HP Care Pack...">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha inicio</label>
          <input type="date" name="fecha_inicio" class="form-control" value="<?= h($g['fecha_inicio']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha fin <span class="req">*</span></label>
          <input type="date" name="fecha_fin" class="form-control" value="<?= h($g['fecha_fin']??'') ?>" required>
        </div>
        <div class="form-group" style="grid-column:span 2;">
          <label class="form-label">Cobertura</label>
          <input type="text" name="cobertura" class="form-control" value="<?= h($g['cobertura']??'') ?>" placeholder="Partes y mano de obra, reemplazo on-site...">
        </div>
        <div class="form-group">
          <label class="form-label">SLA respuesta (horas)</label>
          <input type="number" name="sla_respuesta_hrs" class="form-control" value="<?= h($g['sla_respuesta_hrs']??'') ?>" min="1" placeholder="24">
        </div>
        <div class="form-group">
          <label class="form-label">Número de caso / contrato</label>
          <input type="text" name="numero_caso" class="form-control mono" value="<?= h($g['numero_caso']??'') ?>">
        </div>
      </div>
      <hr style="border:none;border-top:1px solid #EEF2F7;margin:16px 0;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Contacto soporte</label>
          <input type="text" name="contacto_soporte" class="form-control" value="<?= h($g['contacto_soporte']??'') ?>" placeholder="Nombre del representante">
        </div>
        <div class="form-group">
          <label class="form-label">Teléfono soporte</label>
          <input type="text" name="telefono_soporte" class="form-control" value="<?= h($g['telefono_soporte']??'') ?>" placeholder="0800-...">
        </div>
        <div class="form-group">
          <label class="form-label">Email soporte</label>
          <input type="email" name="email_soporte" class="form-control" value="<?= h($g['email_soporte']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Portal web</label>
          <input type="url" name="url_portal" class="form-control" value="<?= h($g['url_portal']??'') ?>" placeholder="https://support.dell.com">
        </div>
        <div class="form-group" style="grid-column:span 2;">
          <label class="form-label">Notas</label>
          <textarea name="notas" class="form-control" rows="2"><?= h($g['notas']??'') ?></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid #EEF2F7;padding:16px 20px;display:flex;justify-content:flex-end;gap:10px;background:#FAFBFF;">
      <a href="<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $ciId ?>&tab=garantias" class="btn btn-ghost">Cancelar</a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-<?= $editar?'floppy-disk':'plus' ?>"></i>
        <?= $editar ? 'Guardar cambios' : 'Registrar garantía' ?>
      </button>
    </div>
  </div>
</form>
</div>

<?php endLayout(); ?>
