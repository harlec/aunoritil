<?php
// ============================================================
// ITSM ALEATICA — ticket_action.php
// Manejador de acciones POST para tickets
// ============================================================
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/itsm/tickets.php');
}

Auth::verifyCsrf();

$action = postClean('action');
$id     = postInt('id');

if (!$id && $action !== 'bulk_accion') {
    flash('error', 'ID de ticket invalido.');
    redirect(BASE_URL . '/modules/itsm/tickets.php');
}

$tk = $id ? DB::row("SELECT * FROM itsm_tickets WHERE id=?", [$id]) : null;

switch ($action) {

    // ── Cerrar ───────────────────────────────────────────────
    case 'cerrar':
        if (!$tk) { flash('error','Ticket no encontrado'); break; }
        if (in_array($tk['estado'], ['Cerrado','Cancelado'])) {
            flash('warning', 'El ticket ya esta cerrado o cancelado.'); break;
        }
        $cumpleSla = ($tk['sla_resolucion_limite'] && strtotime($tk['sla_resolucion_limite']) >= time()) ? 1 : 0;
        DB::updateRow('itsm_tickets', [
            'estado'                  => 'Cerrado',
            'fecha_resolucion'        => $tk['fecha_resolucion'] ?? date('Y-m-d H:i:s'),
            'fecha_cierre'            => date('Y-m-d H:i:s'),
            'sla_resolucion_cumplido' => $cumpleSla,
        ], 'id = ?', [$id]);
        DB::insertRow('itsm_comentarios', [
            'ticket_id'  => $id,
            'usuario_id' => Auth::userId(),
            'tipo'       => 'Sistema',
            'contenido'  => 'Ticket cerrado por ' . Auth::userName(),
        ]);
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} cerrado");
        flash('success', "Ticket {$tk['numero']} cerrado correctamente.");
        redirect(BASE_URL . "/modules/itsm/tickets.php");
        break;

    // ── Cancelar ─────────────────────────────────────────────
    case 'cancelar':
        if (!$tk) { flash('error','Ticket no encontrado'); break; }
        $motivo = postClean('motivo') ?: 'Sin motivo especificado';
        DB::updateRow('itsm_tickets', [
            'estado'      => 'Cancelado',
            'fecha_cierre' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        DB::insertRow('itsm_comentarios', [
            'ticket_id'  => $id,
            'usuario_id' => Auth::userId(),
            'tipo'       => 'Sistema',
            'contenido'  => "Ticket cancelado por " . Auth::userName() . ". Motivo: {$motivo}",
        ]);
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} cancelado");
        flash('success', "Ticket {$tk['numero']} cancelado.");
        redirect(BASE_URL . "/modules/itsm/tickets.php");
        break;

    // ── Reabrir ──────────────────────────────────────────────
    case 'reabrir':
        if (!$tk) { flash('error','Ticket no encontrado'); break; }
        DB::updateRow('itsm_tickets', [
            'estado'                  => 'Nuevo',
            'fecha_resolucion'        => null,
            'fecha_cierre'            => null,
            'sla_resolucion_cumplido' => null,
        ], 'id = ?', [$id]);
        DB::insertRow('itsm_comentarios', [
            'ticket_id'  => $id,
            'usuario_id' => Auth::userId(),
            'tipo'       => 'Sistema',
            'contenido'  => 'Ticket reabierto por ' . Auth::userName(),
        ]);
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} reabierto");
        flash('success', "Ticket {$tk['numero']} reabierto.");
        redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}");
        break;

    // ── Asignar agente ────────────────────────────────────────
    case 'asignar':
        if (!$tk) { flash('error','Ticket no encontrado'); break; }
        $agenteId = postInt('agente_id') ?: null;
        $cambios  = ['agente_id' => $agenteId];
        if ($agenteId) {
            if ($tk['estado'] === 'Nuevo') $cambios['estado'] = 'Asignado';
            if (!$tk['fecha_asignacion'])  $cambios['fecha_asignacion'] = date('Y-m-d H:i:s');
            $agente = DB::row("SELECT nombre FROM adm_usuarios WHERE id=?", [$agenteId]);
            DB::insertRow('itsm_comentarios', [
                'ticket_id'  => $id,
                'usuario_id' => Auth::userId(),
                'tipo'       => 'Sistema',
                'contenido'  => "Ticket asignado a {$agente['nombre']} por " . Auth::userName(),
            ]);
            ITIL::notificar($agenteId, 'ticket_asignado',
                "Ticket asignado: {$tk['numero']}", $tk['titulo'],
                BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}", 'ticket', $id
            );
        } else {
            DB::insertRow('itsm_comentarios', [
                'ticket_id'  => $id,
                'usuario_id' => Auth::userId(),
                'tipo'       => 'Sistema',
                'contenido'  => 'Agente removido del ticket por ' . Auth::userName(),
            ]);
        }
        DB::updateRow('itsm_tickets', $cambios, 'id = ?', [$id]);
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} asignado");
        flash('success', 'Asignacion actualizada.');
        redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}");
        break;

    // ── Resolver ─────────────────────────────────────────────
    case 'resolver':
        if (!$tk) { flash('error','Ticket no encontrado'); break; }
        $solucion  = postClean('solucion');
        $cumpleSla = ($tk['sla_resolucion_limite'] && strtotime($tk['sla_resolucion_limite']) >= time()) ? 1 : 0;
        DB::updateRow('itsm_tickets', [
            'estado'                  => 'Resuelto',
            'solucion'                => $solucion,
            'fecha_resolucion'        => date('Y-m-d H:i:s'),
            'sla_resolucion_cumplido' => $cumpleSla,
        ], 'id = ?', [$id]);
        if ($solucion) {
            DB::insertRow('itsm_comentarios', [
                'ticket_id'  => $id,
                'usuario_id' => Auth::userId(),
                'tipo'       => 'Publico',
                'contenido'  => "Resuelto por " . Auth::userName() . ".\n\nSolucion: {$solucion}",
            ]);
        }
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} resuelto");
        flash('success', "Ticket {$tk['numero']} marcado como resuelto.");
        redirect(BASE_URL . "/modules/itsm/ticket_detalle.php?id={$id}");
        break;

    // ── Calificar ─────────────────────────────────────────────
    case 'calificar':
        if (!$tk) { flash('error','Ticket no encontrado'); break; }
        $calificacion     = min(5, max(1, postInt('calificacion')));
        $comentarioCierre = postClean('comentario_cierre');
        DB::updateRow('itsm_tickets', [
            'calificacion'      => $calificacion,
            'comentario_cierre' => $comentarioCierre,
            'estado'            => 'Cerrado',
            'fecha_cierre'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        Audit::log('itsm_tickets', $id, 'EDITAR', null, null, "Ticket {$tk['numero']} calificado {$calificacion}/5");
        flash('success', 'Gracias por tu calificacion. Ticket cerrado.');
        redirect(BASE_URL . "/modules/itsm/tickets.php");
        break;

    // ── Acciones masivas ─────────────────────────────────────
    case 'bulk_accion':
        $ids        = array_filter(array_map('intval', $_POST['ids'] ?? []));
        $bulkAccion = postClean('bulk_accion');
        if (empty($ids)) {
            flash('warning', 'No seleccionaste ningun ticket.');
            redirect(BASE_URL . '/modules/itsm/tickets.php');
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        switch ($bulkAccion) {
            case 'cerrar':
                DB::exec("UPDATE itsm_tickets SET estado='Cerrado', fecha_cierre=NOW() WHERE id IN ({$ph}) AND estado NOT IN ('Cerrado','Cancelado')", $ids);
                flash('success', count($ids) . ' ticket(s) cerrados.');
                break;
            case 'asignar_me':
                DB::exec("UPDATE itsm_tickets SET agente_id=?, estado='Asignado' WHERE id IN ({$ph}) AND estado NOT IN ('Cerrado','Cancelado')",
                    array_merge([Auth::userId()], $ids));
                flash('success', count($ids) . ' ticket(s) asignados a ti.');
                break;
            default:
                flash('warning', 'Accion masiva no reconocida.');
        }
        redirect(BASE_URL . '/modules/itsm/tickets.php');
        break;

    default:
        flash('error', 'Accion no reconocida.');
        redirect(BASE_URL . '/modules/itsm/tickets.php');
}

redirect(BASE_URL . '/modules/itsm/tickets.php');
