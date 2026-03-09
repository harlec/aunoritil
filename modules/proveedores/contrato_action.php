<?php
// contrato_action.php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

$accion = clean($_GET['accion'] ?? '');
$id     = (int)($_GET['id'] ?? 0);

switch ($accion) {
    case 'eliminar':
        $c = DB::row("SELECT nombre FROM sup_contratos WHERE id=?", [$id]);
        if ($c) {
            DB::exec("DELETE FROM sup_contratos WHERE id=?", [$id]);
            flash('success', "Contrato '{$c['nombre']}' eliminado.");
        }
        redirect(BASE_URL . '/modules/proveedores/contratos.php');

    default:
        redirect(BASE_URL . '/modules/proveedores/contratos.php');
}
