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

$usuarios   = DB::query("SELECT id, nombre FROM adm_usuarios WHERE activo=1 ORDER BY nombre");
$categorias = DB::query("SELECT id, nombre, padre_id FROM itsm_categorias WHERE activa=1 ORDER BY padre_id IS NULL DESC, nombre");
$cis        = DB::query("SELECT id, nombre, codigo_ci FROM cmdb_cis WHERE estado='Activo' ORDER BY nombre") ?: [];
$servicios  = DB::query("SELECT id, codigo, nombre FROM itsm_catalogo_servicios WHERE estado='Activo' ORDER BY nombre") ?: [];
$sedes      = DB::query("SELECT id, nombre FROM cmdb_ubicaciones WHERE activa=1 ORDER BY nombre") ?: [];
$grupos     = DB::query("SELECT id, nombre FROM adm_grupos WHERE activo=1 ORDER BY nombre") ?: [];

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
                ITIL::notificar($datos['agente_id'], 'ticket_asignado',
                    "Ticket asignado: {$datos['numero']}", $datos['titulo'],
                    BASE_URL . "/modules/itsm/ticket_detalle.php?id={$newId}", 'ticket', $newId
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
<!-- Encabezado -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <div style="font-size:12px;color:#94A3B8;margin-bottom:4px;">
            <a href="tickets.php" style="color:#94A3B8;text-decoration:none;">Tickets</a>
            <i class="fa-solid fa-chevron-right" style="font-size:10px;margin:0 6px;"></i>
            <span><?= $editar ? h($tk['numero']) : 'Nuevo' ?></span>
        </div>
        <h1 class="page-title">
            <i class="fa-solid fa-ticket" style="color:#1565C0;margin-right:8px;"></i>
            <?= $editar ? 'Editar Ticket' : 'Nuevo Ticket' ?>
        </h1>
    </div>
    <a href="tickets.php" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<form method="post">
    <?= Auth::csrfInput() ?>
    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

        <!-- Columna principal -->
        <div>
            <!-- Info del ticket -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fa-solid fa-info-circle" style="color:#1565C0;margin-right:8px;"></i>
                        Información del Ticket
                    </span>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">Tipo <span class="req">*</span></label>
                            <select name="tipo" class="form-control" required>
                                <?php foreach(['Solicitud','Incidente','Evento','Alerta'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($tk['tipo']??'Solicitud')===$t?'selected':'' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado <span class="req">*</span></label>
                            <select name="estado" class="form-control" required>
                                <?php foreach(['Nuevo','Asignado','En_proceso','En_espera','Resuelto','Cerrado','Cancelado'] as $e): ?>
                                <option value="<?= $e ?>" <?= ($tk['estado']??'Nuevo')===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Título / Asunto <span class="req">*</span></label>
                        <input type="text" name="titulo" class="form-control"
                               placeholder="Describe brevemente el problema o solicitud"
                               value="<?= h($tk['titulo'] ?? '') ?>" required maxlength="500">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción detallada <span class="req">*</span></label>
                        <textarea name="descripcion" class="form-control" rows="5"
                                  placeholder="Describe el problema con el mayor detalle posible..." required><?= h($tk['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-control">
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
                        <div class="form-group">
                            <label class="form-label">Servicio afectado</label>
                            <select name="servicio_id" class="form-control">
                                <option value="">— Ninguno —</option>
                                <?php foreach ($servicios as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($tk['servicio_id']??0)==$s['id']?'selected':'' ?>>
                                    [<?= h($s['codigo']) ?>] <?= h($s['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label class="form-label">CI afectado (CMDB)</label>
                            <select name="ci_id" class="form-control">
                                <option value="">— No aplica —</option>
                                <?php foreach ($cis as $ci): ?>
                                <option value="<?= $ci['id'] ?>" <?= ($tk['ci_id']??0)==$ci['id']?'selected':'' ?>>
                                    [<?= h($ci['codigo_ci']) ?>] <?= h($ci['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sede / Ubicación</label>
                            <select name="sede_id" class="form-control">
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
            <!-- Resolución -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fa-solid fa-circle-check" style="color:#2E7D32;margin-right:8px;"></i>
                        Resolución
                    </span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Workaround (solución temporal)</label>
                        <textarea name="workaround" class="form-control" rows="3"><?= h($tk['workaround'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Solución definitiva</label>
                        <textarea name="solucion" class="form-control" rows="4"><?= h($tk['solucion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna lateral -->
        <div>
            <!-- Prioridad ITIL -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fa-solid fa-gauge" style="color:#F9A825;margin-right:8px;"></i>
                        Prioridad ITIL
                    </span>
                </div>
                <div class="card-body">
                    <p style="font-size:12px;color:#94A3B8;margin-bottom:14px;">
                        La prioridad se calcula según urgencia e impacto.
                    </p>
                    <div class="form-group">
                        <label class="form-label">Urgencia</label>
                        <div style="display:flex;gap:8px;">
                            <?php foreach(['Alta','Media','Baja'] as $u): ?>
                            <label style="flex:1;text-align:center;">
                                <input type="radio" name="urgencia" value="<?= $u ?>"
                                       <?= ($tk['urgencia']??'Media')===$u?'checked':'' ?> required
                                       style="display:none;" class="radio-pill-input">
                                <span class="radio-pill"><?= $u ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Impacto</label>
                        <div style="display:flex;gap:8px;">
                            <?php foreach(['Alto','Medio','Bajo'] as $i): ?>
                            <label style="flex:1;text-align:center;">
                                <input type="radio" name="impacto" value="<?= $i ?>"
                                       <?= ($tk['impacto']??'Medio')===$i?'checked':'' ?> required
                                       style="display:none;" class="radio-pill-input">
                                <span class="radio-pill"><?= $i ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="priorIndicador" style="background:#E3F2FD;color:#1565C0;border-radius:8px;padding:10px;text-align:center;font-weight:600;font-size:13px;">
                        Prioridad: <span id="priorValor">Media</span>
                    </div>
                </div>
            </div>

            <!-- Asignación -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fa-solid fa-users-gear" style="color:#1565C0;margin-right:8px;"></i>
                        Asignación
                    </span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Solicitante</label>
                        <select name="solicitante_id" class="form-control">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($tk['solicitante_id']??0)==$u['id']?'selected':'' ?>>
                                <?= h($u['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Grupo de soporte</label>
                        <select name="grupo_id" class="form-control">
                            <option value="">— Sin grupo —</option>
                            <?php foreach ($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($tk['grupo_id']??0)==$g['id']?'selected':'' ?>>
                                <?= h($g['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Agente responsable</label>
                        <select name="agente_id" class="form-control">
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

            <!-- Origen -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span class="card-title">
                        <i class="fa-solid fa-tower-cell" style="color:#94A3B8;margin-right:8px;"></i>
                        Origen
                    </span>
                </div>
                <div class="card-body">
                    <select name="origen" class="form-control">
                        <?php foreach(['Portal','Email','Telefono','Monitoreo','Manual','API'] as $o): ?>
                        <option value="<?= $o ?>" <?= ($tk['origen']??'Portal')===$o?'selected':'' ?>><?= $o ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Botones -->
            <div style="display:flex;flex-direction:column;gap:8px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $editar ? 'Guardar cambios' : 'Crear ticket' ?>
                </button>
                <a href="<?= $editar ? 'ticket_detalle.php?id='.$id : 'tickets.php' ?>" class="btn btn-ghost" style="text-align:center;">
                    Cancelar
                </a>
            </div>
        </div>

    </div>
</form>

<style>
.radio-pill {
    display: block;
    padding: 6px 4px;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all .15s;
    background: white;
}
.radio-pill:hover { border-color: #4A90C4; color: #1565C0; }
.radio-pill-input:checked + .radio-pill {
    background: #1A3A5C;
    border-color: #1A3A5C;
    color: white;
    font-weight: 600;
}
</style>
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
    const el = document.getElementById('priorIndicador');
    el.style.background = bg;
    el.style.color = color;
    document.getElementById('priorValor').textContent = prior;
}
document.querySelectorAll('input[name="urgencia"], input[name="impacto"]')
    .forEach(el => el.addEventListener('change', actualizarPrioridad));
actualizarPrioridad();
</script>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
