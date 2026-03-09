<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$c      = [];

if ($editar) {
    $c = DB::row("SELECT * FROM sup_contratos WHERE id=?", [$id]);
    if (!$c) { flash('error','Contrato no encontrado'); redirect(BASE_URL.'/modules/proveedores/contratos.php'); }
}

$pageTitle  = $editar ? 'Editar Contrato' : 'Nuevo Contrato';
$pageModule = 'proveedores';

$proveedores = DB::query("SELECT id, nombre FROM sup_proveedores WHERE activo=1 ORDER BY nombre");
$servicios   = DB::query("SELECT id, codigo, nombre FROM itsm_catalogo_servicios WHERE estado='Activo' ORDER BY nombre");
$slas        = $editar ? DB::query("SELECT * FROM sup_sla_proveedor WHERE contrato_id=?", [$id]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $datos = [
        'numero'          => postClean('numero'),
        'proveedor_id'    => postInt('proveedor_id'),
        'tipo'            => postClean('tipo'),
        'nombre'          => postClean('nombre'),
        'descripcion'     => postClean('descripcion'),
        'estado'          => postClean('estado'),
        'fecha_inicio'    => post('fecha_inicio') ?: null,
        'fecha_fin'       => post('fecha_fin') ?: null,
        'renovacion_auto' => isset($_POST['renovacion_auto']) ? 1 : 0,
        'alerta_dias'     => postInt('alerta_dias') ?: 30,
        'moneda'          => postClean('moneda'),
        'monto_total'     => post('monto_total') ?: null,
        'monto_mensual'   => post('monto_mensual') ?: null,
        'servicio_id'     => postInt('servicio_id') ?: null,
        'notas'           => postClean('notas'),
        'updated_at'      => date('Y-m-d H:i:s'),
    ];

    $errores = [];
    if (empty($datos['numero']))       $errores[] = 'El número de contrato es obligatorio.';
    if (empty($datos['proveedor_id'])) $errores[] = 'Selecciona un proveedor.';
    if (empty($datos['nombre']))       $errores[] = 'El nombre es obligatorio.';

    // Número único
    if (!empty($datos['numero'])) {
        $existe = DB::value("SELECT id FROM sup_contratos WHERE numero=? AND id!=?", [$datos['numero'], $id]);
        if ($existe) $errores[] = "El número '{$datos['numero']}' ya existe.";
    }

    if (empty($errores)) {
        if ($editar) {
            DB::updateRow('sup_contratos', $datos, 'id=?', [$id]);
            flash('success', 'Contrato actualizado.');
        } else {
            $datos['created_by'] = Auth::userId();
            $id = DB::insertRow('sup_contratos', $datos);
            flash('success', 'Contrato creado.');
        }
        // Guardar SLAs del POST
        $slaMetricas  = $_POST['sla_metrica']   ?? [];
        $slaValores   = $_POST['sla_valor']      ?? [];
        $slaUnidades  = $_POST['sla_unidad']     ?? [];
        $slaPeriodos  = $_POST['sla_periodo']    ?? [];
        DB::exec("DELETE FROM sup_sla_proveedor WHERE contrato_id=?", [$id]);
        foreach ($slaMetricas as $i => $metrica) {
            if (empty(trim($metrica))) continue;
            DB::insertRow('sup_sla_proveedor', [
                'contrato_id'        => $id,
                'nombre_metrica'     => trim($metrica),
                'valor_comprometido' => $slaValores[$i] ?? 0,
                'unidad'             => $slaUnidades[$i]  ?? '%',
                'periodo_medicion'   => $slaPeriodos[$i]  ?? 'Mensual',
            ]);
        }
        redirect(BASE_URL . "/modules/proveedores/contrato_detalle.php?id={$id}");
    } else {
        foreach ($errores as $e) flash('error', $e);
    }
}

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <i class="fas fa-file-contract" style="color:#1565C0;margin-right:10px;"></i>
      <?= $editar ? 'Editar Contrato' : 'Nuevo Contrato' ?>
    </h1>
    <?php if($editar): ?><p class="page-subtitle"><?= h($c['numero']??'') ?> — <?= h($c['nombre']??'') ?></p><?php endif; ?>
  </div>
  <a href="<?= BASE_URL ?>/modules/proveedores/contratos.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<form method="POST" id="formContrato">
  <?= Auth::csrfInput() ?>
  <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Datos principales -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-file-contract" style="color:#1565C0;margin-right:8px;"></i>Datos del Contrato</span></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Número <span class="req">*</span></label>
              <input type="text" name="numero" class="form-control mono" value="<?= h($c['numero']??'') ?>" placeholder="CTR-2025-001" required>
            </div>
            <div class="form-group">
              <label class="form-label">Nombre / Descripción <span class="req">*</span></label>
              <input type="text" name="nombre" class="form-control" value="<?= h($c['nombre']??'') ?>" required placeholder="Ej: Soporte Anual Dell ProSupport Plus">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Proveedor <span class="req">*</span></label>
              <select name="proveedor_id" class="form-control" required>
                <option value="">Seleccionar...</option>
                <?php foreach($proveedores as $pv): ?>
                <option value="<?= $pv['id'] ?>" <?= ($c['proveedor_id']??0)==$pv['id']?'selected':'' ?>><?= h($pv['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Tipo</label>
              <select name="tipo" class="form-control">
                <?php foreach(['Soporte','Mantenimiento','Licencia','Servicio','SaaS','ISP','Otro'] as $t): ?>
                <option value="<?= $t ?>" <?= ($c['tipo']??'Soporte')===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2"><?= h($c['descripcion']??'') ?></textarea>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Fecha inicio</label>
              <input type="date" name="fecha_inicio" class="form-control" value="<?= h($c['fecha_inicio']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Fecha fin / vencimiento</label>
              <input type="date" name="fecha_fin" class="form-control" value="<?= h($c['fecha_fin']??'') ?>">
            </div>
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:500;color:#475569;margin-bottom:14px;">
            <input type="checkbox" name="renovacion_auto" value="1" <?= !empty($c['renovacion_auto'])?'checked':'' ?> style="width:16px;height:16px;accent-color:#1A3A5C;">
            Renovación automática
          </label>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Monto total</label>
              <input type="number" name="monto_total" class="form-control" value="<?= h($c['monto_total']??'') ?>" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="form-group">
              <label class="form-label">Monto mensual</label>
              <input type="number" name="monto_mensual" class="form-control" value="<?= h($c['monto_mensual']??'') ?>" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="form-group">
              <label class="form-label">Moneda</label>
              <select name="moneda" class="form-control">
                <?php foreach(['PEN','USD','MXN'] as $m): ?>
                <option value="<?= $m ?>" <?= ($c['moneda']??'PEN')===$m?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Servicio TI relacionado</label>
            <select name="servicio_id" class="form-control">
              <option value="">Sin servicio asociado</option>
              <?php foreach($servicios as $sv): ?>
              <option value="<?= $sv['id'] ?>" <?= ($c['servicio_id']??0)==$sv['id']?'selected':'' ?>>
                <?= h($sv['codigo']) ?> — <?= h($sv['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="2"><?= h($c['notas']??'') ?></textarea>
          </div>
        </div>
      </div>

      <!-- SLAs del contrato -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-gauge-high" style="color:#00695C;margin-right:8px;"></i>Métricas SLA del Contrato</span>
          <button type="button" class="btn btn-primary btn-sm" onclick="agregarSLA()">
            <i class="fas fa-plus"></i> Agregar métrica
          </button>
        </div>
        <div class="card-body">
          <div id="slaContainer">
            <?php if(!empty($slas)): foreach($slas as $i => $s): ?>
            <div class="sla-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;">
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="<?= $i>0?'visibility:hidden;':''; ?>">Métrica</label>
                <input type="text" name="sla_metrica[]" class="form-control" value="<?= h($s['nombre_metrica']) ?>" placeholder="Ej: Uptime del servicio">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="<?= $i>0?'visibility:hidden;':''; ?>">Valor</label>
                <input type="number" name="sla_valor[]" class="form-control" value="<?= h($s['valor_comprometido']) ?>" step="0.01" placeholder="99.9">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="<?= $i>0?'visibility:hidden;':''; ?>">Unidad</label>
                <input type="text" name="sla_unidad[]" class="form-control" value="<?= h($s['unidad']) ?>" placeholder="% / hrs / min">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="<?= $i>0?'visibility:hidden;':''; ?>">Período</label>
                <select name="sla_periodo[]" class="form-control">
                  <?php foreach(['Mensual','Trimestral','Semanal','Diario'] as $p): ?>
                  <option value="<?= $p ?>" <?= $s['periodo_medicion']===$p?'selected':'' ?>><?= $p ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="<?= $i>0?'margin-top:0;':'margin-top:20px;' ?>">
                <button type="button" onclick="this.closest('.sla-row').remove()" class="btn btn-ghost btn-sm btn-icon" style="color:#EF5350;"><i class="fas fa-trash"></i></button>
              </div>
            </div>
            <?php endforeach; else: ?>
            <!-- Fila vacía inicial -->
            <div class="sla-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;">
              <div class="form-group" style="margin:0;">
                <label class="form-label">Métrica</label>
                <input type="text" name="sla_metrica[]" class="form-control" placeholder="Ej: Uptime del servicio">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label">Valor</label>
                <input type="number" name="sla_valor[]" class="form-control" step="0.01" placeholder="99.9">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label">Unidad</label>
                <input type="text" name="sla_unidad[]" class="form-control" placeholder="% / hrs">
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label">Período</label>
                <select name="sla_periodo[]" class="form-control">
                  <option>Mensual</option><option>Trimestral</option><option>Semanal</option><option>Diario</option>
                </select>
              </div>
              <div style="margin-top:20px;">
                <button type="button" onclick="this.closest('.sla-row').remove()" class="btn btn-ghost btn-sm btn-icon" style="color:#EF5350;"><i class="fas fa-trash"></i></button>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <p style="font-size:12px;color:#94A3B8;margin-top:4px;"><i class="fas fa-info-circle"></i> Define los compromisos de nivel de servicio que el proveedor debe cumplir.</p>
        </div>
      </div>

    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:80px;">
      <div class="card">
        <div class="card-body" style="padding:16px;">
          <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:10px;">
            <i class="fas fa-<?= $editar?'floppy-disk':'plus' ?>"></i>
            <?= $editar ? 'Guardar cambios' : 'Crear contrato' ?>
          </button>
          <a href="contratos.php" class="btn btn-ghost" style="width:100%;">Cancelar</a>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Estado del Contrato</span></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-control">
              <?php foreach(['Activo','Vencido','En_renovacion','Cancelado','Borrador'] as $e): ?>
              <option value="<?= $e ?>" <?= ($c['estado']??'Activo')===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Alertar con (días de anticipación)</label>
            <input type="number" name="alerta_dias" class="form-control" value="<?= h($c['alerta_dias']??30) ?>" min="1" max="365">
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function agregarSLA() {
    const cont = document.getElementById('slaContainer');
    const div  = document.createElement('div');
    div.className = 'sla-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;';
    div.innerHTML = `
      <div class="form-group" style="margin:0;"><input type="text" name="sla_metrica[]" class="form-control" placeholder="Ej: Tiempo respuesta"></div>
      <div class="form-group" style="margin:0;"><input type="number" name="sla_valor[]" class="form-control" step="0.01" placeholder="99.9"></div>
      <div class="form-group" style="margin:0;"><input type="text" name="sla_unidad[]" class="form-control" placeholder="% / hrs"></div>
      <div class="form-group" style="margin:0;">
        <select name="sla_periodo[]" class="form-control">
          <option>Mensual</option><option>Trimestral</option><option>Semanal</option><option>Diario</option>
        </select>
      </div>
      <div><button type="button" onclick="this.closest('.sla-row').remove()" class="btn btn-ghost btn-sm btn-icon" style="color:#EF5350;"><i class="fas fa-trash"></i></button></div>
    `;
    cont.appendChild(div);
}
</script>

<?php endLayout(); ?>
