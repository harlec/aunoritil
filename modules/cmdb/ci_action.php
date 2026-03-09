<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
Auth::requireCan('cmdb.*');
Auth::verifyCsrf();

$accion = clean($_GET['accion'] ?? $_POST['accion'] ?? '');
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

switch ($accion) {

    case 'eliminar':
        $ci = DB::row("SELECT nombre FROM cmdb_cis WHERE id=?", [$id]);
        if ($ci) {
            DB::exec("DELETE FROM cmdb_cis WHERE id=?", [$id]);
            Audit::log('cmdb_cis', $id, 'ELIMINAR', $ci);
            flash('success', "CI '{$ci['nombre']}' eliminado.");
        }
        redirect(BASE_URL . '/modules/cmdb/cis.php');

    case 'baja':
        $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
        foreach ($ids as $cid) {
            DB::exec("UPDATE cmdb_cis SET estado='Dado_de_baja', updated_at=NOW() WHERE id=?", [$cid]);
            DB::insertRow('cmdb_historial_ci', ['ci_id'=>$cid,'tipo_cambio'=>'Estado','valor_anterior'=>'Activo','valor_nuevo'=>'Dado_de_baja','usuario_id'=>Auth::userId(),'descripcion'=>'Baja masiva']);
        }
        flash('success', count($ids) . ' CIs dados de baja.');
        redirect(BASE_URL . '/modules/cmdb/cis.php');

    case 'almacen':
        $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
        foreach ($ids as $cid) {
            DB::exec("UPDATE cmdb_cis SET estado='En_almacen', updated_at=NOW() WHERE id=?", [$cid]);
            DB::insertRow('cmdb_historial_ci', ['ci_id'=>$cid,'tipo_cambio'=>'Estado','valor_anterior'=>'Activo','valor_nuevo'=>'En_almacen','usuario_id'=>Auth::userId(),'descripcion'=>'Mover a almacén masivo']);
        }
        flash('success', count($ids) . ' CIs movidos a almacén.');
        redirect(BASE_URL . '/modules/cmdb/cis.php');

    case 'agregar_relacion':
        $origen  = postInt('ci_origen_id');
        $destino = postInt('ci_destino_id');
        $tipo    = postClean('tipo_relacion');
        $desc    = postClean('descripcion');
        $redir   = (int)($_POST['redirect'] ?? $origen);
        if ($origen && $destino && $tipo) {
            try {
                DB::insertRow('cmdb_relaciones', [
                    'ci_origen_id'  => $origen,
                    'ci_destino_id' => $destino,
                    'tipo_relacion' => $tipo,
                    'descripcion'   => $desc,
                    'created_by'    => Auth::userId(),
                ]);
                flash('success', 'Relación agregada.');
            } catch (PDOException $e) {
                flash('error', 'Esta relación ya existe.');
            }
        }
        redirect(BASE_URL . "/modules/cmdb/ci_detalle.php?id={$redir}&tab=relaciones");

    case 'del_relacion':
        $ciRedir = (int)($_GET['ci'] ?? 0);
        DB::exec("DELETE FROM cmdb_relaciones WHERE id=?", [$id]);
        flash('success', 'Relación eliminada.');
        redirect(BASE_URL . "/modules/cmdb/ci_detalle.php?id={$ciRedir}&tab=relaciones");

    default:
        redirect(BASE_URL . '/modules/cmdb/cis.php');
}
