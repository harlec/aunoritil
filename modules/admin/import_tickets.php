<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin('admin');

$pageTitle  = 'Importar Tickets desde CSV';
$pageModule = 'admin';

// ── Mapeo de SEDE (código CSV → nombre en cmdb_ubicaciones) ──
$MAPA_SEDES = [
    'LIM' => 'Lima',
    'NCH' => 'Nuevo Chimbote',
    'FOR' => 'Fortaleza',
    'HUA' => 'Huarmey',
    'VIR' => 'Virú',
    '402' => 'KM 402',
    'SAN' => 'Santa',
];

// ── Mapeo de TIPO INCIDENCIA → categoria_id ──
$MAPA_CATEGORIAS = [
    'Soporte Usuario MS365'        => 'Aplicacion Corporativa',
    'Soporte Usuario Notebook'     => 'Laptop / Desktop',
    'Soporte Usuario Aplicaciones' => 'Aplicacion Corporativa',
    'Soporte General'              => 'Otros',
    'Soporte Usuario Peaje'        => 'Aplicacion Corporativa',
    'Sistema de Peajes'            => 'Aplicacion Corporativa',
    'Soporte Usuario Móvil'        => 'Laptop / Desktop',
    'Soporte Usuario Movil'        => 'Laptop / Desktop',
    'Redes MPLS'                   => 'Sin conectividad',
    'Redes Internet'               => 'Sin conectividad',
    'Redes Starlink'               => 'Sin conectividad',
    'Poste SOS'                    => 'Poste SOS sin senial',
    'Telefonía Fija'               => 'Otros',
    'Telefonia Fija'               => 'Otros',
    'Telefonía Móvil'              => 'Otros',
    'Telefonia Movil'              => 'Otros',
    'Facturación Electrónica'      => 'Aplicacion Corporativa',
    'Facturacion Electronica'      => 'Aplicacion Corporativa',
    'PMV'                          => 'Otros',
    'Izipay'                       => 'Aplicacion Corporativa',
    'Página Web'                   => 'Aplicacion Corporativa',
    'Pagina Web'                   => 'Aplicacion Corporativa',
];

// ── Estado CSV → estado BD ──
function mapearEstado(string $e): string {
    $e = strtolower(trim($e));
    if (str_contains($e, 'solucionado') || str_contains($e, 'cerrado')) return 'Cerrado';
    if (str_contains($e, 'proceso'))    return 'En_proceso';
    if (str_contains($e, 'espera'))     return 'En_espera';
    if ($e === '')                       return 'Cerrado';
    return 'Cerrado';
}

// ── Cachés para lookups ──
$cacheSedes  = [];
$cacheCats   = [];
$cacheAgentes= [];

function buscarSede(string $codigo, array $mapa): ?int {
    global $cacheSedes;
    $codigo = trim($codigo);
    if (!$codigo) return null;
    if (isset($cacheSedes[$codigo])) return $cacheSedes[$codigo];
    $nombre = $mapa[$codigo] ?? $codigo;
    $r = DB::row("SELECT id FROM cmdb_ubicaciones WHERE nombre LIKE ? LIMIT 1", ["%{$nombre}%"]);
    $cacheSedes[$codigo] = $r ? (int)$r['id'] : null;
    return $cacheSedes[$codigo];
}

function buscarCategoria(string $tipo, array $mapa): ?int {
    global $cacheCats;
    $tipo = trim($tipo);
    if (!$tipo) return null;
    if (isset($cacheCats[$tipo])) return $cacheCats[$tipo];
    $nombreCat = $mapa[$tipo] ?? null;
    if (!$nombreCat) { $cacheCats[$tipo] = null; return null; }
    $r = DB::row("SELECT id FROM itsm_categorias WHERE nombre = ? LIMIT 1", [$nombreCat]);
    $cacheCats[$tipo] = $r ? (int)$r['id'] : null;
    return $cacheCats[$tipo];
}

function buscarAgente(string $nombre): ?int {
    global $cacheAgentes;
    $nombre = trim($nombre);
    if (!$nombre) return null;
    if (isset($cacheAgentes[$nombre])) return $cacheAgentes[$nombre];
    // Busca por apellido o nombre parcial
    $partes = explode(' ', $nombre);
    $apellido = $partes[0] ?? '';
    $r = DB::row("SELECT id FROM adm_usuarios WHERE nombre LIKE ? LIMIT 1", ["%{$apellido}%"]);
    $cacheAgentes[$nombre] = $r ? (int)$r['id'] : null;
    return $cacheAgentes[$nombre];
}

function parsearFecha(string $f): ?string {
    $f = trim($f);
    if (!$f || $f === '' || str_starts_with($f, '#')) return null;
    // Formatos: d/m/Y, d/m/y, d/m/Y H:i
    $limpio = preg_replace('/[^\d\/]/', '', $f);
    $partes  = explode('/', $limpio);
    if (count($partes) < 3) return null;
    [$d, $m, $y] = $partes;
    if (strlen((string)$y) === 2) $y = '20' . $y;
    if ((int)$y < 2000 || (int)$y > 2030) return null;
    if (!checkdate((int)$m, (int)$d, (int)$y)) return null;
    return sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d);
}

function limpiarTexto(string $s): string {
    // Convertir de Windows-1252 a UTF-8
    return mb_convert_encoding(trim($s), 'UTF-8', 'Windows-1252');
}

// ── PROCESAMIENTO DEL CSV ─────────────────────────────────────
$preview  = [];
$errores  = [];
$importados = 0;
$omitidos   = 0;
$accion   = $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['preview','importar'])) {
    Auth::verifyCsrf();

    $archivo    = $_FILES['csv']['tmp_name'] ?? '';
    $fileError  = $_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE;
    // Si es importar con datos en POST (segunda pasada)
    $usarPost = ($accion === 'importar' && empty($archivo) && !empty($_POST['csv_data']));

    if ($usarPost) {
        $lineas = explode("\n", $_POST['csv_data']);
    } elseif ($archivo && is_uploaded_file($archivo)) {
        $contenido = file_get_contents($archivo);
        $lineas = explode("\n", $contenido);
    } else {
        $mensajesError = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize en php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera MAX_FILE_SIZE del formulario.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir en disco.',
        ];
        $msg = $mensajesError[$fileError] ?? "Error de subida (código {$fileError}).";
        flash('error', $msg);
        redirect(BASE_URL . '/modules/admin/import_tickets.php');
    }

    // Guardar raw para el form oculto
    $csvRaw = implode("\n", $lineas);

    $encontroHeader = false;
    $filaNum = 0;

    foreach ($lineas as $linea) {
        $linea = rtrim($linea, "\r");
        $cols  = explode(';', $linea);

        // Detectar fila de cabecera
        if (!$encontroHeader) {
            if (isset($cols[0]) && trim($cols[0]) === 'ID' && isset($cols[8])) {
                $encontroHeader = true;
            }
            continue;
        }

        // Ignorar filas vacías o con #N/D
        $clave = trim($cols[8] ?? '');
        if ($clave === '' || str_starts_with($clave, '#')) { $omitidos++; continue; }
        if (count($cols) < 9) { $omitidos++; continue; }

        $filaNum++;
        $fecha       = parsearFecha(limpiarTexto($cols[1] ?? ''));
        $sede        = limpiarTexto($cols[2] ?? '');
        $usuario     = limpiarTexto($cols[3] ?? '');
        $tipoInc     = limpiarTexto($cols[4] ?? '');
        $sistema     = limpiarTexto($cols[5] ?? '');
        $proveedor   = limpiarTexto($cols[6] ?? '');
        $descripcion = limpiarTexto($cols[8] ?? '');
        $coordinador = limpiarTexto($cols[9] ?? '');
        $estadoCSV   = limpiarTexto($cols[10] ?? '');
        $fechaSol    = parsearFecha(limpiarTexto($cols[11] ?? ''));
        $tiempoMin   = (int)(preg_replace('/[^\d]/', '', $cols[12] ?? ''));
        $comentario  = limpiarTexto($cols[13] ?? '');

        if (!$fecha || !$descripcion) { $omitidos++; continue; }

        $titulo = $descripcion;
        if ($usuario) $titulo = $usuario . ' — ' . $descripcion;

        $fila = [
            '_fila'         => $filaNum,
            'fecha'         => $fecha,
            'sede'          => $sede,
            'usuario'       => $usuario,
            'tipo_inc'      => $tipoInc,
            'sistema'       => $sistema,
            'proveedor'     => $proveedor,
            'descripcion'   => $descripcion,
            'titulo'        => truncate($titulo, 490),
            'coordinador'   => $coordinador,
            'estado_csv'    => $estadoCSV,
            'fecha_sol'     => $fechaSol,
            'tiempo_min'    => $tiempoMin,
            'comentario'    => $comentario,
            // Resueltos
            'sede_id'       => buscarSede($sede, $MAPA_SEDES),
            'categoria_id'  => buscarCategoria($tipoInc, $MAPA_CATEGORIAS),
            'agente_id'     => buscarAgente($coordinador),
            'estado'        => mapearEstado($estadoCSV),
        ];
        $preview[] = $fila;
    }

    // ── IMPORTAR ──────────────────────────────────────────────
    if ($accion === 'importar' && !empty($preview)) {
        $contadorNum = (int)DB::value("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)),0) FROM itsm_tickets WHERE numero LIKE 'IMP-%'");

        foreach ($preview as $f) {
            $contadorNum++;
            $numero = 'IMP-' . date('Y', strtotime($f['fecha'])) . '-' . str_pad($contadorNum, 5, '0', STR_PAD_LEFT);

            $notasExtra = trim(implode(' | ', array_filter([
                $f['sistema']   ? "Sistema: {$f['sistema']}"     : '',
                $f['proveedor'] ? "Proveedor: {$f['proveedor']}" : '',
            ])));

            $ticketId = DB::insertRow('itsm_tickets', [
                'numero'                => $numero,
                'tipo'                  => str_contains(strtolower($f['tipo_inc']), 'inciden') ? 'Incidente' : 'Solicitud',
                'titulo'                => $f['titulo'],
                'descripcion'           => $f['descripcion'] . ($notasExtra ? "\n\n[{$notasExtra}]" : ''),
                'estado'                => $f['estado'],
                'prioridad'             => 'Media',
                'urgencia'              => 'Media',
                'impacto'               => 'Medio',
                'categoria_id'          => $f['categoria_id'],
                'agente_id'             => $f['agente_id'],
                'sede_id'               => $f['sede_id'],
                'fecha_apertura'        => $f['fecha'],
                'fecha_resolucion'      => $f['fecha_sol'],
                'fecha_cierre'          => $f['fecha_sol'],
                'tiempo_resolucion_min' => $f['tiempo_min'] ?: null,
                'origen'                => 'Manual',
                'created_by'            => Auth::userId(),
            ]);

            if ($f['comentario']) {
                DB::insertRow('itsm_comentarios', [
                    'ticket_id'  => $ticketId,
                    'usuario_id' => Auth::userId(),
                    'tipo'       => 'Interno',
                    'contenido'  => $f['comentario'],
                ]);
            }

            $importados++;
        }

        Audit::log('itsm_tickets', 0, 'IMPORTAR', null, null, "Importados {$importados} tickets desde CSV");
        flash('success', "{$importados} tickets importados correctamente.");
        redirect(BASE_URL . '/modules/itsm/tickets.php');
    }
}

ob_start();
?>
<!-- Encabezado -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fa-solid fa-file-csv" style="color:#2E7D32;margin-right:8px;"></i>
            Importar Tickets desde CSV
        </h1>
        <p class="page-subtitle">Importa el registro histórico de incidentes desde el Excel exportado como CSV.</p>
    </div>
    <a href="usuarios.php" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (empty($preview)): ?>
<!-- Formulario de carga -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="fa-solid fa-upload" style="color:#1565C0;margin-right:8px;"></i>
                Cargar archivo CSV
            </span>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <?= Auth::csrfInput() ?>
                <input type="hidden" name="accion" value="preview">
                <div class="form-group">
                    <label class="form-label">Archivo CSV <span class="req">*</span></label>
                    <input type="file" name="csv" accept=".csv,.txt" class="form-control" required>
                    <div style="font-size:11px;color:#94A3B8;margin-top:4px;">
                        Exporta el Excel como CSV (delimitado por punto y coma)
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fa-solid fa-eye"></i> Vista previa
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="fa-solid fa-map" style="color:#F9A825;margin-right:8px;"></i>
                Mapeo de columnas
            </span>
        </div>
        <div class="card-body" style="padding:16px 20px;">
            <?php
            $mapeo = [
                'FECHA'              => 'fecha_apertura',
                'SEDE'               => 'sede_id (lookup)',
                'USUARIO/VIA/SERVICIO'=> 'parte del título',
                'TIPO INCIDENCIA'    => 'categoria_id (lookup)',
                'SISTEMA'            => 'descripción (extra)',
                'PROVEEDOR'          => 'descripción (extra)',
                'DESCRIPCIÓN INCIDENTE'=> 'titulo + descripcion',
                'COORDINADOR AUNOR'  => 'agente_id (lookup)',
                'ESTADO'             => 'estado',
                'FECHA SOLUCIÓN'     => 'fecha_resolucion',
                'TIEMPO (Minutos)'   => 'tiempo_resolucion_min',
                'COMENTARIOS'        => 'comentario interno',
            ];
            foreach ($mapeo as $csv => $bd): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid #F1F5F9;font-size:12px;">
                <span style="font-weight:600;color:#1E293B;"><?= $csv ?></span>
                <span style="color:#64748B;">→ <?= $bd ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Vista previa -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <span class="card-title">
            <i class="fa-solid fa-table" style="color:#1565C0;margin-right:8px;"></i>
            Vista previa — <?= count($preview) ?> registros encontrados
            <?php if ($omitidos > 0): ?>
            <span class="badge" style="background:#FFF3E0;color:#E65100;border:1px solid #FFCC80;margin-left:8px;">
                <?= $omitidos ?> omitidos (vacíos / #N/D)
            </span>
            <?php endif; ?>
        </span>
        <div style="display:flex;gap:8px;">
            <a href="import_tickets.php" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </a>
            <form method="post" id="formImportar" style="display:inline;">
                <?= Auth::csrfInput() ?>
                <input type="hidden" name="accion" value="importar">
                <input type="hidden" name="csv_data" value="<?= h($csvRaw ?? '') ?>">
                <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm('¿Importar <?= count($preview) ?> tickets? Esta acción no se puede deshacer.')">
                    <i class="fa-solid fa-file-import"></i> Confirmar importación
                </button>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Sede</th>
                    <th>Usuario / Descripción</th>
                    <th>Tipo incidencia</th>
                    <th>Coordinador</th>
                    <th>Estado</th>
                    <th style="width:60px">Min</th>
                    <th style="width:60px">Sed.</th>
                    <th style="width:60px">Cat.</th>
                    <th style="width:60px">Age.</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($preview as $f): ?>
            <tr>
                <td style="font-size:11px;color:#94A3B8;"><?= $f['_fila'] ?></td>
                <td style="font-size:12px;white-space:nowrap;"><?= $f['fecha'] ? date('d/m/Y', strtotime($f['fecha'])) : '—' ?></td>
                <td>
                    <span class="badge" style="background:#E8EAF6;color:#1565C0;border:1px solid #c5cae9;font-size:10px;">
                        <?= h($f['sede']) ?>
                    </span>
                </td>
                <td style="max-width:300px;">
                    <div style="font-size:12px;font-weight:500;"><?= h(truncate($f['usuario'], 30)) ?></div>
                    <div style="font-size:11px;color:#64748B;"><?= h(truncate($f['descripcion'], 60)) ?></div>
                </td>
                <td style="font-size:12px;"><?= h(truncate($f['tipo_inc'], 30)) ?></td>
                <td style="font-size:12px;"><?= h(truncate($f['coordinador'], 20)) ?></td>
                <td>
                    <?php $ec = $f['estado'] === 'Cerrado' ? ['#E8F5E9','#2E7D32'] : ['#FFF8E1','#F9A825']; ?>
                    <span class="badge" style="background:<?= $ec[0] ?>;color:<?= $ec[1] ?>;border:1px solid <?= $ec[1] ?>44;font-size:10px;">
                        <?= $f['estado'] ?>
                    </span>
                </td>
                <td style="font-size:12px;text-align:right;"><?= $f['tiempo_min'] ?: '—' ?></td>
                <td style="text-align:center;">
                    <?php if ($f['sede_id']): ?>
                    <i class="fa-solid fa-check" style="color:#2E7D32;font-size:11px;"></i>
                    <?php else: ?>
                    <i class="fa-solid fa-xmark" style="color:#EF5350;font-size:11px;" title="No encontrado: <?= h($f['sede']) ?>"></i>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($f['categoria_id']): ?>
                    <i class="fa-solid fa-check" style="color:#2E7D32;font-size:11px;"></i>
                    <?php else: ?>
                    <i class="fa-solid fa-minus" style="color:#94A3B8;font-size:11px;" title="Sin mapeo: <?= h($f['tipo_inc']) ?>"></i>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($f['agente_id']): ?>
                    <i class="fa-solid fa-check" style="color:#2E7D32;font-size:11px;"></i>
                    <?php else: ?>
                    <i class="fa-solid fa-minus" style="color:#94A3B8;font-size:11px;" title="No encontrado: <?= h($f['coordinador']) ?>"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="background:#FFF8E1;border:1px solid #FFCC80;border-radius:10px;padding:14px 18px;font-size:13px;color:#E65100;">
    <i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i>
    <strong>Atención:</strong> Los tickets se importarán con número <code>IMP-YYYY-NNNNN</code> para distinguirlos de los tickets nuevos.
    Las columnas marcadas con <i class="fa-solid fa-xmark" style="color:#EF5350;"></i> no pudieron resolver el lookup — se importarán sin ese campo.
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require_once ROOT_PATH . '/includes/layout.php';
