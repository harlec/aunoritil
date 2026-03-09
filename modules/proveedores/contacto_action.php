<?php
// contacto_action.php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
Auth::verifyCsrf();

$accion = clean($_GET['accion'] ?? $_POST['accion'] ?? '');
$provId = (int)($_GET['proveedor'] ?? $_POST['proveedor_id'] ?? 0);

switch ($accion) {
    case 'crear':
        $datos = [
            'proveedor_id' => postInt('proveedor_id'),
            'nombre'       => postClean('nombre'),
            'cargo'        => postClean('cargo'),
            'email'        => postClean('email'),
            'telefono'     => postClean('telefono'),
            'tipo'         => postClean('tipo'),
            'principal'    => isset($_POST['principal']) ? 1 : 0,
        ];
        if (!empty($datos['nombre'])) {
            if ($datos['principal']) {
                DB::exec("UPDATE sup_contactos SET principal=0 WHERE proveedor_id=?", [$datos['proveedor_id']]);
            }
            DB::insertRow('sup_contactos', $datos);
            flash('success', 'Contacto agregado.');
        }
        redirect(BASE_URL . "/modules/proveedores/proveedor_form.php?id={$datos['proveedor_id']}");

    case 'eliminar':
        $id = (int)($_GET['id'] ?? 0);
        DB::exec("DELETE FROM sup_contactos WHERE id=?", [$id]);
        flash('success', 'Contacto eliminado.');
        redirect(BASE_URL . "/modules/proveedores/proveedor_form.php?id={$provId}");

    default:
        redirect(BASE_URL . '/modules/proveedores/proveedores.php');
}
