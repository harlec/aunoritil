<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$tk     = [];

if ($editar) {
    $tk = DB::row("SELECT * FROM itsm_tickets WHERE id=?", [$id]);
    if (!$tk) { flash('error','Ticket no encontrado'); redirect(BASE_URL.'/modules/itsm/tickets.php'); }
}

$pageTitle  = $editar ? 'Editar Ticket — ' . ($tk['numero'] ?? '') : 'Nuevo Ticket';
$pageModule = 'tickets';

// ── Datos auxiliares ──────────────────────────────────────────
$usuarios    = DB::query("SELECT id, nombre, email FROM adm_usuarios WHERE activo=1 ORDER BY nombre");
$categorias  = DB::query("SELECT id, nombre, padre_id, icono, color FROM itsm_categorias WHERE activa=1 ORDER BY padre_id IS NULL DESC, nombre");
$cis         = DB::query("SELECT id, nombre, codigo_ci FROM cmdb_cis WHERE estado='Activo' ORDER BY nombre") ?: [];
$servicios   = DB::query("SELECT id, codigo, nombre FROM itsm_catalogo_servicios WHERE estado='Activo' ORDER BY nombre") ?: [];
$sedes       = DB::query("SELECT id, nombre, tipo FROM cmdb_ubicaciones WHERE activa=1 ORDER BY nombre") ?: [];
$grupos      = DB::query("SELECT id, nombre FROM adm_grupos WHERE activo=1 ORDER BY nombre") ?: [];

// ── Procesar formulario ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $urgencia  = postClean('urgencia');
    $impacto   = postClean('impacto');
    $prioridad = ITIL::calcularPrioridad($urgencia, $impacto);
    $catId     = postInt('categoria_id') ?: null;
    $sla       = !$editar ? ITIL::calcularSLA($prioridad, $catId) : null;

    $datos = [
        'tipo'           => postClean('tipo'),
        'titulo'         => postClean('titulo'),
        'descripcion'    => postClean('descripcion'),
        'estado'         => postClean('estado'),
        'prioridad'      => $prioridad,
        'urgencia'       => $urgencia,
        'impacto'        => $impacto,
        'categoria_id'   => $catId,
        'servicio_id'    => postInt('servicio_id') ?: null,
        'ci_id'          => postInt('ci_id') ?: null,
        'solicitante_id' => postInt('solicitante_id') ?: null,
        'agente_id'      => postInt('agente_id') ?: null,
        'grupo_id'       => postInt('grupo_id') ?: null,
        'sede_id'        => postInt('sede_id') ?: null,
        'origen'         => postClean('origen'),
        'workaround'     => postClean('workaround'),
        'solucion'       => postClean('solucion'),
    ];

    if (!$editar) {
        $datos['numero']                = ITIL::siguiente('TKT', 'itsm_tickets');
        $datos['fecha_apertura']        = date('Y-m-d H:i:s');
        $datos['sla_respuesta_limite']  = $sla['respuesta']  ?? null;
        $datos['sla_resolucion_limite'] = $sla['resolucion'] ?? null;
        $datos['created_by']            = Auth::userId();
    }

    if (!$editar && !empty($datos['agente_id'])) {
        if ($datos['estado'] === 'Nuevo') $datos['estado'] = 'Asignado';
        $datos['fecha_asignacion'] = date('Y-m-d H:i:s');
    }

    if (in_array($datos['estado'], ['Resuelto','Cerrado']) && !($tk['fecha_resolucion'] ?? null)) {
        $datos['fecha_resolucion'] = date('Y-m-d H:i:s');
        if ($editar && !empty($tk['sla_resolucion_limite'])) {
            $datos['sla_resolucion_cumplido'] = (strtotime($tk['sla_resolucion_limite']) >= time()) ? 1 : 0;
        }
    }

    try {
        if ($editar) {
            DB::updateRow('itsm_tickets', $datos, 'id = ?', [$id]);
            Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} actualizado");
            flash('success', "Ticket {$tk['numero']} actualizado correctamente.");
            redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}");
        } else {
            $newId = DB::insertRow('itsm_tickets', $datos);
            if (!empty($datos['agente_id'])) {
                ITIL::notificar(
                    $datos['agente_id'],
                    'ticket_asignado',
                    "Ticket asignado: {$datos['numero']}",
                    $datos['titulo'],
                    BASE_URL . "/modules/itsm/ticket_detalle.php?id={$newId}",
                    'ticket', $newId
                );
            }
            Audit::log('itsm_tickets', $newId, 'CREAR', null, null, "Ticket {$datos['numero']} creado");
            flash('success', "Ticket {$datos['numero']} creado exitosamente.");
            redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$newId}");
        }
    } catch (Exception $e) {
        flash('error', 'Error al guardar: ' . $e->getMessage());
    }
}

ob_start();
?>
<div class="row justify-content-center">
<div class="col-12 col-xl-10">

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="tickets.php" class="text-decoration-none">Tickets</a></li>
                <li class="breadcrumb-item active"><?= $editar ? h($tk['numero']) : 'Nuevo' ?></li>
            </ol>
        </nav>
        <h5 class="mb-0 fw-bold" style="color:#1A3A5C">
            <i class="fa-solid fa-ticket me-2" style="color:#1565C0"></i>
            <?= $editar ? 'Editar Ticket' : 'Nuevo Ticket' ?>
        </h5>
    </div>
    <a href="tickets.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver
    </a>
</div>

<form method="post">
    <?= Auth::csrfInput() ?>
    <div class="row g-4">

        <!-- Columna principal -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <span class="fw-semibold small" style="color:#1A3A5C">
                        <i class="fa-solid fa-info-circle me-2 text-primary"></i>Informacion del Ticket
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select form-select-sm" required>
                                <?php foreach(['Solicitud','Incidente','Evento','Alerta'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($tk['tipo']??'Solicitud')===$t?'selected':'' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Estado <span class="text-danger">*</span></label>
                            <select name="estado" class="form-select form-select-sm" required>
                                <?php foreach(['Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado'] as $e): ?>
                                <option value="<?= $e ?>" <?= ($tk['estado']??'Nuevo')===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Titulo / Asunto <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control form-control-sm"
                               placeholder="Describe brevemente el problema o solicitud"
                               value="<?= h($tk['titulo'] ?? '') ?>" required maxlength="500">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Descripcion detallada <span class="text-danger">*</span></label>
                        <textarea name="descripcion" class="form-control form-control-sm" rows="5"
                                  placeholder="Describe el problema con el mayor detalle posible..." required><?= h($tk['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Categoria</label>
                            <select name="categoria_id" class="form-select form-select-sm">
                                <option value="">— Seleccionar —</option>
                                <?php
                                $catPadres = array_filter($categorias, fn($c) => !$c['padre_id']);
                                $catHijos  = array_filter($categorias, fn($c) => $c['padre_id']);
                                foreach ($catPadres as $pad):
                                    $hijos = array_filter($catHijos, fn($c) => $c['padre_id']==$pad['id']);
                                    if ($hijos): ?>
                                    <optgroup label="<?= h($pad['nombre']) ?>">
                                        <?php foreach ($hijos as $hijo): ?>
                                        <option value="<?= $hijo['id'] ?>" <?= ($tk['categoria_id']??0)==$hijo['id']?'selected':'' ?>>
                                            <?= h($hijo['nombre']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php else: ?>
                                    <option value="<?= $pad['id'] ?>" <?= ($tk['categoria_id']??0)==$pad['id']?'selected':'' ?>>
                                        <?= h($pad['nombre']) ?>
                                    </option>
                                    <?php endif;
                                endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Servicio afectado</label>
                            <select name="servicio_id" class="form-select form-select-sm">
                                <option value="">— Ninguno —</option>
                                <?php foreach ($servicios as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($tk['servicio_id']??0)==$s['id']?'selected':'' ?>>
                                    [<?= h($s['codigo']) ?>] <?= h($s['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">CI afectado (CMDB)</label>
                            <select name="ci_id" class="form-select form-select-sm">
                                <option value="">— No aplica —</option>
                                <?php foreach ($cis as $ci): ?>
                                <option value="<?= $ci['id'] ?>" <?= ($tk['ci_id']??0)==$ci['id']?'selected':'' ?>>
                                    [<?= h($ci['codigo_ci']) ?>] <?= h($ci['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Sede / Ubicacion</label>
                            <select name="sede_id" class="form-select form-select-sm">
                                <option value="">— Todas —</option>
                                <?php foreach ($sedes as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($tk['sede_id']??0)==$s['id']?'selected':'' ?>>
                                    <?= h($s['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($editar): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <span class="fw-semibold small" style="color:#1A3A5C">
                        <i class="fa-solid fa-circle-check me-2 text-success"></i>Resolucion
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Workaround (solucion temporal)</label>
                        <textarea name="workaround" class="form-control form-control-sm" rows="3"><?= h($tk['workaround'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Solucion definitiva</label>
                        <textarea name="solucion" class="form-control form-control-sm" rows="4"><?= h($tk['solucion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna lateral -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <span class="fw-semibold small" style="color:#1A3A5C">
                        <i class="fa-solid fa-gauge me-2 text-warning"></i>Prioridad ITIL
                    </span>
                </div>
                <div class="card-body p-3">
                    <p class="text-muted small mb-3">La prioridad se calcula segun urgencia e impacto.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Urgencia</label>
                        <div class="d-flex gap-2">
                            <?php foreach(['Alta','Media','Baja'] as $u): ?>
                            <div class="flex-fill">
                                <input type="radio" class="btn-check" name="urgencia" id="urg_<?= $u ?>"
                                       value="<?= $u ?>" <?= ($tk['urgencia']??'Media')===$u?'checked':'' ?> required>
                                <label class="btn btn-outline-primary btn-sm w-100" for="urg_<?= $u ?>"><?= $u ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Impacto</label>
                        <div class="d-flex gap-2">
                            <?php foreach(['Alto','Medio','Bajo'] as $i): ?>
                            <div class="flex-fill">
                                <input type="radio" class="btn-check" name="impacto" id="imp_<?= $i ?>"
                                       value="<?= $i ?>" <?= ($tk['impacto']??'Medio')===$i?'checked':'' ?> required>
                                <label class="btn btn-outline-secondary btn-sm w-100" for="imp_<?= $i ?>"><?= $i ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="priorIndicador" class="rounded-3 p-2 text-center fw-bold small" style="background:#E3F2FD;color:#1565C0">
                        Prioridad: <span id="priorValor">Media</span>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <span class="fw-semibold small" style="color:#1A3A5C">
                        <i class="fa-solid fa-users-gear me-2 text-primary"></i>Asignacion
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Solicitante</label>
                        <select name="solicitante_id" class="form-select form-select-sm">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($tk['solicitante_id']??0)==$u['id']?'selected':'' ?>>
                                <?= h($u['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Grupo de soporte</label>
                        <select name="grupo_id" class="form-select form-select-sm">
                            <option value="">— Sin grupo —</option>
                            <?php foreach ($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($tk['grupo_id']??0)==$g['id']?'selected':'' ?>>
                                <?= h($g['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Agente responsable</label>
                        <select name="agente_id" class="form-select form-select-sm">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($tk['agente_id']??0)==$u['id']?'selected':'' ?>>
                                <?= h($u['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <span class="fw-semibold small" style="color:#1A3A5C">
                        <i class="fa-solid fa-tower-cell me-2 text-secondary"></i>Origen
                    </span>
                </div>
                <div class="card-body p-3">
                    <select name="origen" class="form-select form-select-sm">
                        <?php foreach(['Portal','Email','Telefono','Monitoreo','Manual','API'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($tk['origen']??'Portal')===$o?'selected':'' ?>><?= $o ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-2"></i>
                    <?= $editar ? 'Guardar cambios' : 'Crear ticket' ?>
                </button>
                <a href="<?= $editar ? 'ticket_detalle.php?id='.$id : 'tickets.php' ?>" class="btn btn-outline-secondary">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
</form>
</div>
</div>

<script>
const matrizPrior = {
    'Alta':  {'Alto':'Critica','Medio':'Alta',  'Bajo':'Media'},
    'Media': {'Alto':'Alta',   'Medio':'Media', 'Bajo':'Media'},
    'Baja':  {'Alto':'Media',  'Medio':'Baja',  'Bajo':'Baja'},
};
const priorColors = {
    'Critica': ['#FFEBEE','#EF5350'],
    'Alta':    ['#FFF3E0','#FF6B00'],
    'Media':   ['#FFFDE7','#F9A825'],
    'Baja':    ['#E8F5E9','#2E7D32'],
};
function actualizarPrioridad() {
    const urg = document.querySelector('input[name="urgencia"]:checked')?.value;
    const imp = document.querySelector('input[name="impacto"]:checked')?.value;
    if (!urg || !imp) return;
    const prior = matrizPrior[urg]?.[imp] ?? 'Media';
    const [bg, color] = priorColors[prior] ?? ['#E3F2FD','#1565C0'];
    document.getElementById('priorIndicador').style.background = bg;
    document.getElementById('priorIndicador').style.color = color;
    document.getElementById('priorValor').textContent = prior;
}
document.querySelectorAll('input[name="urgencia"], input[name="impacto"]')
    .forEach(el => el.addEventListener('change', actualizarPrioridad));
actualizarPrioridad();
</script>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
