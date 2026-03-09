<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
Auth::requireCan('admin.*');

$accion = clean($_GET['accion'] ?? $_POST['accion'] ?? '');

switch ($accion) {

    // ── Activar / Desactivar usuario ──────────────────────────
    case 'toggle_activo':
        $id = (int)($_GET['id'] ?? 0);
        if ($id === Auth::userId()) {
            flash('error', 'No puedes desactivarte a ti mismo.');
            redirect(BASE_URL . '/modules/admin/usuarios.php');
        }
        $u = DB::row("SELECT nombre, activo FROM adm_usuarios WHERE id=?", [$id]);
        if ($u) {
            $nuevo = $u['activo'] ? 0 : 1;
            DB::exec("UPDATE adm_usuarios SET activo=?, updated_at=NOW() WHERE id=?", [$nuevo, $id]);
            // Cerrar sesiones activas si se desactiva
            if (!$nuevo) {
                DB::exec("DELETE FROM adm_sesiones WHERE usuario_id=?", [$id]);
            }
            Audit::log('adm_usuarios', $id, $nuevo ? 'ACTIVAR' : 'DESACTIVAR');
            flash('success', "Usuario '{$u['nombre']}' " . ($nuevo ? 'activado' : 'desactivado') . '.');
        }
        redirect(BASE_URL . '/modules/admin/usuarios.php');

    // ── Asignar equipo a usuario ──────────────────────────────
    case 'asignar_equipo':
        Auth::verifyCsrf();
        $usuarioId = postInt('usuario_id');
        $ciId      = postInt('ci_id');

        if (!$usuarioId || !$ciId) {
            flash('error', 'Datos incompletos.');
            redirect(BASE_URL . '/modules/admin/usuarios.php');
        }

        $ci = DB::row("SELECT nombre, propietario_id FROM cmdb_cis WHERE id=?", [$ciId]);
        $u  = DB::row("SELECT nombre FROM adm_usuarios WHERE id=?", [$usuarioId]);

        if ($ci && $u) {
            // Guardar propietario anterior en historial
            if ($ci['propietario_id'] && $ci['propietario_id'] != $usuarioId) {
                $anterior = DB::value("SELECT nombre FROM adm_usuarios WHERE id=?", [$ci['propietario_id']]);
                DB::insertRow('cmdb_historial_ci', [
                    'ci_id'          => $ciId,
                    'tipo_cambio'    => 'Reasignacion',
                    'valor_anterior' => $anterior ?? 'Sin propietario',
                    'valor_nuevo'    => $u['nombre'],
                    'descripcion'    => 'Equipo reasignado desde gestión de usuarios',
                    'usuario_id'     => Auth::userId(),
                ]);
            } else {
                DB::insertRow('cmdb_historial_ci', [
                    'ci_id'          => $ciId,
                    'tipo_cambio'    => 'Asignacion',
                    'valor_anterior' => 'Sin propietario',
                    'valor_nuevo'    => $u['nombre'],
                    'descripcion'    => 'Equipo asignado desde gestión de usuarios',
                    'usuario_id'     => Auth::userId(),
                ]);
            }

            DB::exec(
                "UPDATE cmdb_cis SET propietario_id=?, updated_at=NOW() WHERE id=?",
                [$usuarioId, $ciId]
            );
            Audit::log('cmdb_cis', $ciId, 'ASIGNAR', null, ['propietario_id' => $usuarioId]);
            flash('success', "Equipo '{$ci['nombre']}' asignado a {$u['nombre']}.");
        } else {
            flash('error', 'CI o usuario no encontrado.');
        }
        redirect(BASE_URL . "/modules/admin/usuario_detalle.php?id={$usuarioId}&tab=equipos");

    // ── Desasignar equipo ─────────────────────────────────────
    case 'desasignar_equipo':
        $ciId      = (int)($_GET['ci_id']      ?? 0);
        $usuarioId = (int)($_GET['usuario_id'] ?? 0);

        $ci = DB::row("SELECT nombre FROM cmdb_cis WHERE id=? AND propietario_id=?", [$ciId, $usuarioId]);
        if ($ci) {
            DB::exec(
                "UPDATE cmdb_cis SET propietario_id=NULL, updated_at=NOW() WHERE id=?",
                [$ciId]
            );
            DB::insertRow('cmdb_historial_ci', [
                'ci_id'       => $ciId,
                'tipo_cambio' => 'Desasignacion',
                'valor_anterior' => DB::value("SELECT nombre FROM adm_usuarios WHERE id=?", [$usuarioId]),
                'valor_nuevo' => 'Sin propietario',
                'descripcion' => 'Equipo desasignado desde gestión de usuarios',
                'usuario_id'  => Auth::userId(),
            ]);
            Audit::log('cmdb_cis', $ciId, 'DESASIGNAR');
            flash('success', "Equipo '{$ci['nombre']}' desasignado.");
        } else {
            flash('error', 'No se encontró el equipo o no pertenece a este usuario.');
        }
        redirect(BASE_URL . "/modules/admin/usuario_detalle.php?id={$usuarioId}&tab=equipos");

    // ── Reset password (genera temporal) ─────────────────────
    case 'reset_password':
        $id = (int)($_GET['id'] ?? 0);
        $u  = DB::row("SELECT nombre, email FROM adm_usuarios WHERE id=?", [$id]);
        if ($u) {
            $temporal = 'Aleatica' . rand(1000,9999) . '!';
            $hash     = password_hash($temporal, PASSWORD_BCRYPT, ['cost'=>12]);
            DB::exec("UPDATE adm_usuarios SET password_hash=?, updated_at=NOW() WHERE id=?", [$hash, $id]);
            Audit::log('adm_usuarios', $id, 'RESET_PASSWORD');
            flash('success', "Contraseña temporal para {$u['nombre']}: <strong>{$temporal}</strong> — Comunícasela de forma segura.");
        }
        redirect(BASE_URL . "/modules/admin/usuario_detalle.php?id={$id}");

    // ── Eliminar usuario ──────────────────────────────────────
    case 'eliminar':
        $id = (int)($_GET['id'] ?? 0);
        if ($id === Auth::userId()) {
            flash('error', 'No puedes eliminarte a ti mismo.');
            redirect(BASE_URL . '/modules/admin/usuarios.php');
        }
        $u = DB::row("SELECT nombre FROM adm_usuarios WHERE id=?", [$id]);
        if ($u) {
            // Desasignar equipos antes de eliminar
            DB::exec("UPDATE cmdb_cis SET propietario_id=NULL WHERE propietario_id=?", [$id]);
            DB::exec("DELETE FROM adm_sesiones WHERE usuario_id=?", [$id]);
            DB::exec("DELETE FROM adm_usuarios WHERE id=?", [$id]);
            Audit::log('adm_usuarios', $id, 'ELIMINAR', $u);
            flash('success', "Usuario '{$u['nombre']}' eliminado.");
        }
        redirect(BASE_URL . '/modules/admin/usuarios.php');

    default:
        redirect(BASE_URL . '/modules/admin/usuarios.php');
}
