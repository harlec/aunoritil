<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
Auth::requireCan('cmdb.*');

$id     = (int)($_GET['id'] ?? 0);
$editar = $id > 0;
$ci     = [];

if ($editar) {
    $ci = DB::row("SELECT * FROM cmdb_cis WHERE id=?", [$id]);
    if (!$ci) { flash('error','CI no encontrado'); redirect(BASE_URL.'/modules/cmdb/cis.php'); }
}

$pageTitle  = $editar ? 'Editar CI — ' . $ci['nombre'] : 'Nuevo Elemento de Configuración';
$pageModule = 'cmdb';

// ── Datos auxiliares ──────────────────────────────────────────
$ubicaciones = DB::query("SELECT id, nombre, tipo FROM cmdb_ubicaciones WHERE activa=1 ORDER BY nombre");
$usuarios    = DB::query("SELECT id, nombre, email FROM adm_usuarios WHERE activo=1 ORDER BY nombre");
$proveedores = DB::query("SELECT id, nombre FROM sup_proveedores WHERE activo=1 ORDER BY nombre");
$contratos   = DB::query("SELECT id, numero, nombre FROM sup_contratos WHERE estado='Activo' ORDER BY nombre");
$vlans       = DB::query("SELECT id, vlan_id, nombre FROM net_vlans ORDER BY vlan_id") ?: [];

// ── Procesar formulario ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $accesorios = [
        'cargador' => isset($_POST['acc_cargador']),
        'mouse'    => isset($_POST['acc_mouse']),
        'teclado'  => isset($_POST['acc_teclado']),
        'docking'  => isset($_POST['acc_docking']),
        'mochila'  => isset($_POST['acc_mochila']),
    ];

    $datos = [
        'codigo_ci'          => postClean('codigo_ci'),
        'nombre'             => postClean('nombre'),
        'tipo_ci'            => postClean('tipo_ci'),
        'categoria'          => postClean('categoria'),
        'estado'             => postClean('estado'),
        'etapa_ciclo_vida'   => postClean('etapa_ciclo_vida'),
        'criticidad'         => postClean('criticidad'),
        'marca'              => postClean('marca'),
        'modelo'             => postClean('modelo'),
        'numero_serie'       => postClean('numero_serie'),
        'numero_parte'       => postClean('numero_parte'),
        'numero_activo'      => postClean('numero_activo'),
        'version_firmware'   => postClean('version_firmware'),
        'version_so'         => postClean('version_so'),
        'ip_address'         => postClean('ip_address'),
        'mac_address'        => postClean('mac_address'),
        'mac_wifi'           => postClean('mac_wifi'),
        'hostname'           => postClean('hostname'),
        'vlan_id'            => postInt('vlan_id') ?: null,
        'ubicacion_id'       => postInt('ubicacion_id') ?: null,
        'rack_posicion'      => postClean('rack_posicion'),
        'propietario_id'     => postInt('propietario_id') ?: null,
        'responsable_ti'     => postInt('responsable_ti') ?: null,
        'proveedor_id'       => postInt('proveedor_id') ?: null,
        'contrato_id'        => postInt('contrato_id') ?: null,
        'fecha_compra'       => post('fecha_compra') ?: null,
        'fecha_garantia_fin' => post('fecha_garantia_fin') ?: null,
        'costo_adquisicion'  => post('costo_adquisicion') ?: null,
        'moneda_adquisicion' => postClean('moneda_adquisicion'),
        'tipo_adquisicion'   => postClean('tipo_adquisicion'),
        'procesador'         => postClean('procesador'),
        'ram_gb'             => post('ram_gb') ?: null,
        'disco_gb'           => post('disco_gb') ?: null,
        'accesorios'         => json_encode($accesorios),
        'monitor_id'         => postClean('monitor_id') ?: null,
        'monitor_ip'         => postClean('monitor_ip') ?: null,
        'notas'              => postClean('notas'),
        'updated_at'         => date('Y-m-d H:i:s'),
    ];

    // Validar
    $errores = [];
    if (empty($datos['codigo_ci'])) $errores[] = 'El código CI es obligatorio.';
    if (empty($datos['nombre']))    $errores[] = 'El nombre es obligatorio.';
    if (empty($datos['tipo_ci']))   $errores[] = 'El tipo es obligatorio.';

    // Verificar código único
    if (!empty($datos['codigo_ci'])) {
        $existe = DB::value(
            "SELECT id FROM cmdb_cis WHERE codigo_ci=? AND id!=?",
            [$datos['codigo_ci'], $id]
        );
        if ($existe) $errores[] = "El código '{$datos['codigo_ci']}' ya existe.";
    }

    if (empty($errores)) {
        if ($editar) {
            $anterior = $ci;
            DB::updateRow('cmdb_cis', $datos, 'id=?', [$id]);
            // Registrar en historial si cambió el estado
            if ($anterior['estado'] !== $datos['estado']) {
                DB::insertRow('cmdb_historial_ci', [
                    'ci_id'          => $id,
                    'tipo_cambio'    => 'Estado',
                    'valor_anterior' => $anterior['estado'],
                    'valor_nuevo'    => $datos['estado'],
                    'descripcion'    => 'Cambio de estado desde formulario',
                    'usuario_id'     => Auth::userId(),
                ]);
            }
            Audit::log('cmdb_cis', $id, 'EDITAR', $anterior, $datos);
            flash('success', 'CI actualizado correctamente.');
        } else {
            $datos['created_by'] = Auth::userId();
            $newId = DB::insertRow('cmdb_cis', $datos);
            Audit::log('cmdb_cis', $newId, 'CREAR', null, $datos);
            flash('success', "CI {$datos['codigo_ci']} creado correctamente.");
            redirect(BASE_URL . '/modules/cmdb/ci_detalle.php?id=' . $newId);
        }
        redirect(BASE_URL . '/modules/cmdb/cis.php');
    } else {
        foreach ($errores as $e) flash('error', $e);
        $ci = array_merge($ci, $datos); // repoblar form
    }
}

// Sugerir próximo código
if (!$editar) {
    $ultimo = DB::value("SELECT MAX(CAST(SUBSTRING(codigo_ci, 4) AS UNSIGNED)) FROM cmdb_cis WHERE codigo_ci LIKE 'CI-%'");
    $ci['codigo_ci'] = 'CI-' . str_pad(($ultimo ?? 0) + 1, 4, '0', STR_PAD_LEFT);
}

$accesorios = json_decode($ci['accesorios'] ?? '{}', true) ?: [];

require_once __DIR__ . '/../../includes/layout.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <i class="fas fa-<?= $editar ? 'pen' : 'plus-circle' ?>" style="color:#00695C;margin-right:10px;"></i>
      <?= $editar ? h($ci['nombre']) : 'Nuevo CI' ?>
    </h1>
    <p class="page-subtitle"><?= $editar ? 'Editar elemento de configuración' : 'Registrar nuevo elemento en la CMDB' ?></p>
  </div>
  <div style="display:flex;gap:10px;">
    <?php if($editar): ?>
    <a href="<?= BASE_URL ?>/modules/cmdb/ci_detalle.php?id=<?= $id ?>" class="btn btn-ghost">
      <i class="fas fa-eye"></i> Ver detalle
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/modules/cmdb/cis.php" class="btn btn-ghost">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
  </div>
</div>

<form method="POST" id="formCI">
  <?= Auth::csrfInput() ?>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    <!-- Columna principal -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Identificación -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-id-card" style="color:#1A3A5C;margin-right:8px;"></i>Identificación</span>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Código CI <span class="req">*</span></label>
              <input type="text" name="codigo_ci" class="form-control mono"
                     value="<?= h($ci['codigo_ci'] ?? '') ?>"
                     placeholder="CI-0001" required>
            </div>
            <div class="form-group" style="grid-column:span 2;">
              <label class="form-label">Nombre descriptivo <span class="req">*</span></label>
              <input type="text" name="nombre" class="form-control"
                     value="<?= h($ci['nombre'] ?? '') ?>"
                     placeholder="Ej: Laptop Dell Latitude 5540 - Juan Pérez" required>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Categoría <span class="req">*</span></label>
              <select name="categoria" class="form-control" id="selectCat" onchange="ajustarTipos()">
                <?php foreach(['Hardware','Software','Red','Telefonia','ITS','Servicio','Otro'] as $cat): ?>
                <option value="<?= $cat ?>" <?= ($ci['categoria']??'')===$cat?'selected':'' ?>><?= $cat ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Tipo de CI <span class="req">*</span></label>
              <select name="tipo_ci" class="form-control" id="selectTipo">
                <?php
                $tiposMap = [
                  'Hardware'  => ['Servidor','Laptop','Desktop','Tablet','UPS','PDU','Impresora','Escaner','Otro'],
                  'Software'  => ['Licencia_SW','Servicio_SW','Aplicacion','Otro'],
                  'Red'       => ['Switch','Router','AP','Firewall','Modem','Enlace_ISP','Circuito','Otro'],
                  'Telefonia' => ['Telefono_IP','Central_Telefonica','Otro'],
                  'ITS'       => ['Poste_ITS','Camara_IP','Modulo_SOS','Router','Otro'],
                  'Servicio'  => ['Servicio_SW','Aplicacion','Otro'],
                  'Otro'      => ['Otro'],
                ];
                $allTipos = ['Servidor','Laptop','Desktop','Tablet','Switch','Router','AP','Firewall','Modem','Poste_ITS','Camara_IP','Modulo_SOS','UPS','PDU','Telefono_IP','Central_Telefonica','Impresora','Escaner','Licencia_SW','Servicio_SW','Aplicacion','Enlace_ISP','Circuito','Otro'];
                foreach($allTipos as $t):
                ?>
                <option value="<?= $t ?>" <?= ($ci['tipo_ci']??'')===$t?'selected':'' ?>><?= str_replace('_',' ',$t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-control">
                <?php foreach(['Activo','Inactivo','En_reparacion','En_almacen','Dado_de_baja','En_transito'] as $e): ?>
                <option value="<?= $e ?>" <?= ($ci['estado']??'Activo')===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Criticidad</label>
              <select name="criticidad" class="form-control">
                <?php foreach(['Critico','Alto','Medio','Bajo'] as $c): ?>
                <option value="<?= $c ?>" <?= ($ci['criticidad']??'Medio')===$c?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Etapa ciclo de vida</label>
              <select name="etapa_ciclo_vida" class="form-control">
                <?php foreach(['Planificado','En_desarrollo','Operativo','Obsoleto','Retirado'] as $e): ?>
                <option value="<?= $e ?>" <?= ($ci['etapa_ciclo_vida']??'Operativo')===$e?'selected':'' ?>><?= str_replace('_',' ',$e) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Hardware / Datos técnicos -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-microchip" style="color:#4A90C4;margin-right:8px;"></i>Datos Técnicos</span>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Marca</label>
              <input type="text" name="marca" class="form-control" value="<?= h($ci['marca']??'') ?>" placeholder="Dell, HP, Cisco...">
            </div>
            <div class="form-group">
              <label class="form-label">Modelo</label>
              <input type="text" name="modelo" class="form-control" value="<?= h($ci['modelo']??'') ?>" placeholder="Latitude 5540">
            </div>
            <div class="form-group">
              <label class="form-label">Número de Serie</label>
              <input type="text" name="numero_serie" class="form-control mono" value="<?= h($ci['numero_serie']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Número de Parte</label>
              <input type="text" name="numero_parte" class="form-control mono" value="<?= h($ci['numero_parte']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Nro. Activo Fijo</label>
              <input type="text" name="numero_activo" class="form-control" value="<?= h($ci['numero_activo']??'') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Versión Firmware / SO</label>
              <input type="text" name="version_so" class="form-control mono" value="<?= h($ci['version_so']??'') ?>" placeholder="Windows 11 Pro 23H2">
            </div>
          </div>

          <!-- Specs (laptops/servidores) -->
          <div id="secSpecs" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:4px;">
            <div class="form-group">
              <label class="form-label">Procesador</label>
              <input type="text" name="procesador" class="form-control" value="<?= h($ci['procesador']??'') ?>" placeholder="Intel Core i7-1355U">
            </div>
            <div class="form-group">
              <label class="form-label">RAM (GB)</label>
              <input type="number" name="ram_gb" class="form-control" value="<?= h($ci['ram_gb']??'') ?>" min="0" step="0.5" placeholder="16">
            </div>
            <div class="form-group">
              <label class="form-label">Disco (GB)</label>
              <input type="number" name="disco_gb" class="form-control" value="<?= h($ci['disco_gb']??'') ?>" min="0" placeholder="512">
            </div>
          </div>

          <!-- Firmware (equipos red) -->
          <div id="secFirmware" style="display:none;">
            <div class="form-group">
              <label class="form-label">Versión Firmware</label>
              <input type="text" name="version_firmware" class="form-control mono" value="<?= h($ci['version_firmware']??'') ?>" placeholder="IOS 15.2">
            </div>
          </div>

          <!-- Accesorios (laptops) -->
          <div id="secAccesorios" style="display:none;">
            <label class="form-label">Accesorios incluidos</label>
            <div style="display:flex;gap:16px;flex-wrap:wrap;background:#F8FAFC;padding:12px;border-radius:10px;border:1px solid #EEF2F7;">
              <?php $accItems = ['cargador'=>'fa-plug','mouse'=>'fa-computer-mouse','teclado'=>'fa-keyboard','docking'=>'fa-dockview','mochila'=>'fa-bag-shopping']; ?>
              <?php foreach($accItems as $key=>$icon): ?>
              <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:500;color:#475569;">
                <input type="checkbox" name="acc_<?= $key ?>" <?= !empty($accesorios[$key])?'checked':'' ?> style="width:16px;height:16px;accent-color:#1A3A5C;">
                <i class="fas <?= $icon ?>" style="color:#94A3B8;"></i>
                <?= ucfirst($key) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Red -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-network-wired" style="color:#00695C;margin-right:8px;"></i>Red y Conectividad</span>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Dirección IP</label>
              <input type="text" name="ip_address" class="form-control mono" value="<?= h($ci['ip_address']??'') ?>" placeholder="192.168.10.100">
            </div>
            <div class="form-group">
              <label class="form-label">Hostname</label>
              <input type="text" name="hostname" class="form-control mono" value="<?= h($ci['hostname']??'') ?>" placeholder="srv-contabilidad-01">
            </div>
            <div class="form-group">
              <label class="form-label">MAC Address (LAN)</label>
              <input type="text" name="mac_address" class="form-control mono" value="<?= h($ci['mac_address']??'') ?>" placeholder="AA:BB:CC:DD:EE:FF">
            </div>
            <div class="form-group">
              <label class="form-label">MAC Address (WiFi)</label>
              <input type="text" name="mac_wifi" class="form-control mono" value="<?= h($ci['mac_wifi']??'') ?>" placeholder="AA:BB:CC:DD:EE:FF">
            </div>
            <?php if(!empty($vlans)): ?>
            <div class="form-group" style="grid-column:span 2;">
              <label class="form-label">VLAN</label>
              <select name="vlan_id" class="form-control">
                <option value="">Sin VLAN asignada</option>
                <?php foreach($vlans as $v): ?>
                <option value="<?= $v['id'] ?>" <?= ($ci['vlan_id']??0)==$v['id']?'selected':'' ?>>
                  VLAN <?= $v['vlan_id'] ?> — <?= h($v['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Monitoreo -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-chart-line" style="color:#E65100;margin-right:8px;"></i>Integración Monitoreo</span>
          <span style="font-size:11px;color:#94A3B8;">Opcional — vincula con el sistema de monitoreo</span>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">ID en sistema de monitoreo</label>
              <input type="text" name="monitor_id" class="form-control mono"
                     value="<?= h($ci['monitor_id']??'') ?>"
                     placeholder="ID o nombre en la BD de monitoreo">
            </div>
            <div class="form-group">
              <label class="form-label">IP monitoreada</label>
              <input type="text" name="monitor_ip" class="form-control mono"
                     value="<?= h($ci['monitor_ip']??'') ?>"
                     placeholder="IP que pingea el sistema">
            </div>
          </div>
        </div>
      </div>

      <!-- Notas -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-note-sticky" style="color:#94A3B8;margin-right:8px;"></i>Notas</span>
        </div>
        <div class="card-body">
          <textarea name="notas" class="form-control" rows="3"
                    placeholder="Observaciones, historial de intervenciones, información adicional..."><?= h($ci['notas']??'') ?></textarea>
        </div>
      </div>

    </div>

    <!-- Sidebar derecho -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:80px;">

      <!-- Acciones -->
      <div class="card">
        <div class="card-body" style="padding:16px;">
          <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:10px;">
            <i class="fas fa-<?= $editar?'floppy-disk':'plus' ?>"></i>
            <?= $editar ? 'Guardar cambios' : 'Crear CI' ?>
          </button>
          <a href="<?= BASE_URL ?>/modules/cmdb/cis.php" class="btn btn-ghost" style="width:100%;">
            Cancelar
          </a>
        </div>
      </div>

      <!-- Ubicación y asignación -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-map-pin" style="color:#E65100;margin-right:6px;"></i>Ubicación</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Sede / Ubicación</label>
            <select name="ubicacion_id" class="form-control">
              <option value="">Sin asignar</option>
              <?php foreach($ubicaciones as $u): ?>
              <option value="<?= $u['id'] ?>" <?= ($ci['ubicacion_id']??0)==$u['id']?'selected':'' ?>>
                <?= h($u['nombre']) ?> <span style="color:#94A3B8;">(<?= $u['tipo'] ?>)</span>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Posición en Rack</label>
            <input type="text" name="rack_posicion" class="form-control" value="<?= h($ci['rack_posicion']??'') ?>" placeholder="Rack A1, U3-U5">
          </div>
        </div>
      </div>

      <!-- Asignación -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-users" style="color:#6A1B9A;margin-right:6px;"></i>Asignación</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Asignado a (usuario)</label>
            <select name="propietario_id" class="form-control">
              <option value="">Sin asignar</option>
              <?php foreach($usuarios as $u): ?>
              <option value="<?= $u['id'] ?>" <?= ($ci['propietario_id']??0)==$u['id']?'selected':'' ?>>
                <?= h($u['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Responsable TI</label>
            <select name="responsable_ti" class="form-control">
              <option value="">Sin asignar</option>
              <?php foreach($usuarios as $u): ?>
              <option value="<?= $u['id'] ?>" <?= ($ci['responsable_ti']??0)==$u['id']?'selected':'' ?>>
                <?= h($u['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Proveedor y contrato -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-handshake" style="color:#E65100;margin-right:6px;"></i>Proveedor</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Proveedor</label>
            <select name="proveedor_id" class="form-control">
              <option value="">Sin proveedor</option>
              <?php foreach($proveedores as $p): ?>
              <option value="<?= $p['id'] ?>" <?= ($ci['proveedor_id']??0)==$p['id']?'selected':'' ?>>
                <?= h($p['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Contrato de soporte</label>
            <select name="contrato_id" class="form-control">
              <option value="">Sin contrato</option>
              <?php foreach($contratos as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($ci['contrato_id']??0)==$c['id']?'selected':'' ?>>
                <?= h($c['numero']) ?> — <?= h(substr($c['nombre'],0,30)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Adquisición -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-receipt" style="color:#558B2F;margin-right:6px;"></i>Adquisición</span>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Fecha de compra</label>
            <input type="date" name="fecha_compra" class="form-control" value="<?= h($ci['fecha_compra']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Venc. garantía</label>
            <input type="date" name="fecha_garantia_fin" class="form-control" value="<?= h($ci['fecha_garantia_fin']??'') ?>">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="form-group">
              <label class="form-label">Costo</label>
              <input type="number" name="costo_adquisicion" class="form-control" value="<?= h($ci['costo_adquisicion']??'') ?>" min="0" step="0.01">
            </div>
            <div class="form-group">
              <label class="form-label">Moneda</label>
              <select name="moneda_adquisicion" class="form-control">
                <?php foreach(['PEN','USD','MXN'] as $m): ?>
                <option value="<?= $m ?>" <?= ($ci['moneda_adquisicion']??'PEN')===$m?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tipo adquisición</label>
            <select name="tipo_adquisicion" class="form-control">
              <?php foreach(['CAPEX','OPEX','Leasing','Donacion'] as $t): ?>
              <option value="<?= $t ?>" <?= ($ci['tipo_adquisicion']??'CAPEX')===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<script>
const tiposMap = <?= json_encode([
  'Hardware'  => ['Servidor','Laptop','Desktop','Tablet','UPS','PDU','Impresora','Escaner','Otro'],
  'Software'  => ['Licencia_SW','Servicio_SW','Aplicacion','Otro'],
  'Red'       => ['Switch','Router','AP','Firewall','Modem','Enlace_ISP','Circuito','Otro'],
  'Telefonia' => ['Telefono_IP','Central_Telefonica','Otro'],
  'ITS'       => ['Poste_ITS','Camara_IP','Modulo_SOS','Router','Otro'],
  'Servicio'  => ['Servicio_SW','Aplicacion','Otro'],
  'Otro'      => ['Otro'],
]) ?>;

function ajustarTipos() {
    const cat    = document.getElementById('selectCat').value;
    const sel    = document.getElementById('selectTipo');
    const actual = sel.value;
    const tipos  = tiposMap[cat] || tiposMap['Otro'];
    sel.innerHTML = tipos.map(t =>
        `<option value="${t}" ${t===actual?'selected':''}>${t.replace(/_/g,' ')}</option>`
    ).join('');
    ajustarSecciones();
}

function ajustarSecciones() {
    const cat  = document.getElementById('selectCat').value;
    const tipo = document.getElementById('selectTipo').value;
    const specs    = document.getElementById('secSpecs');
    const firmware = document.getElementById('secFirmware');
    const accs     = document.getElementById('secAccesorios');

    const esPC      = ['Laptop','Desktop','Tablet','Servidor'].includes(tipo);
    const esRed     = ['Switch','Router','AP','Firewall','Modem'].includes(tipo);
    const esLaptop  = ['Laptop','Tablet'].includes(tipo);

    specs.style.display    = esPC    ? 'grid' : 'none';
    firmware.style.display = esRed   ? 'block' : 'none';
    accs.style.display     = esLaptop ? 'block' : 'none';
}

document.getElementById('selectTipo').addEventListener('change', ajustarSecciones);
ajustarSecciones(); // Ejecutar al cargar
</script>

<?php endLayout(); ?>
