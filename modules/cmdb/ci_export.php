<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$buscar    = clean($_GET['q']          ?? '');
$filTipo   = clean($_GET['tipo']       ?? '');
$filCat    = clean($_GET['categoria']  ?? '');
$filEstado = clean($_GET['estado']     ?? '');
$filSede   = (int)($_GET['sede']       ?? 0);

$where  = ['1=1'];
$params = [];
if ($buscar) { $where[] = '(c.nombre LIKE ? OR c.codigo_ci LIKE ? OR c.ip_address LIKE ?)'; $like = "%{$buscar}%"; $params = array_merge($params, [$like,$like,$like]); }
if ($filTipo)   { $where[] = 'c.tipo_ci = ?';     $params[] = $filTipo; }
if ($filCat)    { $where[] = 'c.categoria = ?';   $params[] = $filCat; }
if ($filEstado) { $where[] = 'c.estado = ?';       $params[] = $filEstado; }
if ($filSede)   { $where[] = 'c.ubicacion_id = ?'; $params[] = $filSede; }

$cis = DB::query(
    "SELECT c.codigo_ci, c.nombre, c.tipo_ci, c.categoria, c.estado, c.criticidad,
     c.marca, c.modelo, c.numero_serie, c.ip_address, c.mac_address, c.hostname,
     c.version_so, c.procesador, c.ram_gb, c.disco_gb,
     u.nombre AS sede, us.nombre AS asignado, c.fecha_compra, c.fecha_garantia_fin,
     c.costo_adquisicion, c.moneda_adquisicion, c.notas
     FROM cmdb_cis c
     LEFT JOIN cmdb_ubicaciones u ON u.id = c.ubicacion_id
     LEFT JOIN adm_usuarios us    ON us.id = c.propietario_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY c.codigo_ci",
    $params
);

$filename = 'cmdb_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
// BOM para Excel
fwrite($out, "\xEF\xBB\xBF");

// Cabecera
fputcsv($out, [
    'Código CI','Nombre','Tipo','Categoría','Estado','Criticidad',
    'Marca','Modelo','Serie','IP','MAC','Hostname','SO',
    'CPU','RAM (GB)','Disco (GB)','Sede','Asignado a',
    'Fecha Compra','Garantía hasta','Costo','Moneda','Notas'
], ';');

foreach ($cis as $c) {
    fputcsv($out, array_values($c), ';');
}
fclose($out);
exit;
