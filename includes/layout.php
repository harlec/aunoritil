<?php
// ============================================================
// ITSM ALEATICA — Layout principal (estilo InvGate)
// Uso: $pageTitle, $pageModule ya deben estar definidos
// ============================================================

$pageTitle  = $pageTitle  ?? 'ITSM';
$pageModule = $pageModule ?? '';
$notifCount = contarNotificaciones();
$userInicial = strtoupper(substr(Auth::userName(), 0, 2));

// ── Configuración del menú ────────────────────────────────────
$nav = [
    [
        'id'      => 'dashboard',
        'icon'    => 'fa-gauge-high',
        'label'   => 'Dashboard',
        'url'     => BASE_URL . '/dashboard.php',
        'color'   => '#4A90C4',
        'bg'      => '#E3F2FD',
    ],
    [
        'id'      => 'tickets',
        'icon'    => 'fa-ticket',
        'label'   => 'Mesa de Ayuda',
        'url'     => BASE_URL . '/modules/itsm/tickets.php',
        'color'   => '#1565C0',
        'bg'      => '#E8EAF6',
        'badge'   => fn() => DB::value("SELECT COUNT(*) FROM itsm_tickets WHERE estado IN ('Nuevo','Asignado') AND (agente_id=? OR agente_id IS NULL)", [Auth::userId()]),
    ],
    [
        'id'      => 'cmdb',
        'icon'    => 'fa-server',
        'label'   => 'CMDB',
        'url'     => BASE_URL . '/modules/cmdb/cis.php',
        'color'   => '#00695C',
        'bg'      => '#E0F2F1',
    ],
    [
        'id'      => 'problemas',
        'icon'    => 'fa-bug',
        'label'   => 'Problemas',
        'url'     => BASE_URL . '/modules/itsm/problemas.php',
        'color'   => '#6A1B9A',
        'bg'      => '#F3E5F5',
    ],
    [
        'id'      => 'cambios',
        'icon'    => 'fa-arrows-rotate',
        'label'   => 'Cambios (RFC)',
        'url'     => BASE_URL . '/modules/itsm/cambios.php',
        'color'   => '#0277BD',
        'bg'      => '#E1F5FE',
    ],
    [
        'id'      => 'catalogo',
        'icon'    => 'fa-layer-group',
        'label'   => 'Catálogo',
        'url'     => BASE_URL . '/modules/itsm/catalogo.php',
        'color'   => '#2E7D32',
        'bg'      => '#E8F5E9',
    ],
    [
        'id'      => 'kb',
        'icon'    => 'fa-book-open',
        'label'   => 'Base de Conocimiento',
        'url'     => BASE_URL . '/modules/itsm/kb.php',
        'color'   => '#00838F',
        'bg'      => '#E0F7FA',
    ],
    [
        'id'      => 'redes',
        'icon'    => 'fa-network-wired',
        'label'   => 'Redes / Telecom',
        'url'     => BASE_URL . '/modules/redes/',
        'color'   => '#37474F',
        'bg'      => '#ECEFF1',
    ],
    [
        'id'      => 'proveedores',
        'icon'    => 'fa-handshake',
        'label'   => 'Proveedores',
        'url'     => BASE_URL . '/modules/proveedores/',
        'color'   => '#E65100',
        'bg'      => '#FBE9E7',
    ],
    [
        'id'      => 'finanzas',
        'icon'    => 'fa-file-invoice-dollar',
        'label'   => 'Finanzas TI',
        'url'     => BASE_URL . '/modules/finanzas/',
        'color'   => '#558B2F',
        'bg'      => '#F1F8E9',
    ],
    [
        'id'      => 'disponibilidad',
        'icon'    => 'fa-chart-line',
        'label'   => 'Disponibilidad',
        'url'     => BASE_URL . '/modules/disponibilidad/',
        'color'   => '#1A3A5C',
        'bg'      => '#E8EAF6',
    ],
    [
        'id'      => 'reportes',
        'icon'    => 'fa-chart-pie',
        'label'   => 'Reportes',
        'url'     => BASE_URL . '/modules/reportes/',
        'color'   => '#AD1457',
        'bg'      => '#FCE4EC',
    ],
    [
        'id'      => 'admin',
        'icon'    => 'fa-gear',
        'label'   => 'Administración',
        'url'     => BASE_URL . '/modules/admin/usuarios.php',
        'color'   => '#616161',
        'bg'      => '#F5F5F5',
        'adminOnly' => true,
        'submenu' => [
            ['label'=>'Usuarios',        'url'=> BASE_URL.'/modules/admin/usuarios.php',     'icon'=>'fa-users'],
            ['label'=>'Roles y Permisos','url'=> BASE_URL.'/modules/admin/roles.php',         'icon'=>'fa-shield-halved'],
            ['label'=>'Grupos Soporte',  'url'=> BASE_URL.'/modules/admin/grupos.php',        'icon'=>'fa-people-group'],
            ['label'=>'Configuración',   'url'=> BASE_URL.'/modules/admin/configuracion.php', 'icon'=>'fa-sliders'],
            ['label'=>'Auditoría',       'url'=> BASE_URL.'/modules/admin/auditoria.php',     'icon'=>'fa-clock-rotate-left'],
            ['label'=>'Importar CSV',    'url'=> BASE_URL.'/modules/admin/import_tickets.php', 'icon'=>'fa-file-csv'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> — Aleatica ITSM</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Google Fonts: DM Sans + JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <style>
    /* ── Reset y base ────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:    #1A3A5C;
      --green:   #4CAF50;
      --orange:  #FF6B00;
      --sidebar-w: 220px;
      --sidebar-w-open: 220px;
      --topbar-h: 56px;
      --font: 'DM Sans', system-ui, sans-serif;
      --mono: 'JetBrains Mono', monospace;
      --radius: 12px;
      --shadow: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.05);
      --shadow-md: 0 4px 16px rgba(0,0,0,.10);
    }

    html, body { height: 100%; }
    body {
      font-family: var(--font);
      background: #F0F4F8;
      color: #1E293B;
      font-size: 14px;
      line-height: 1.5;
      overflow-x: hidden;
    }

    /* ── Sidebar ─────────────────────────────────────────── */
    #sidebar {
      position: fixed; left: 0; top: 0; bottom: 0;
      width: 220px;
      background: #FFFFFF;
      border-right: 1px solid #EEF2F7;
      display: flex; flex-direction: column; align-items: stretch;
      padding: 0 0 16px;
      z-index: 100;
      overflow-y: auto; overflow-x: hidden;
      box-shadow: 2px 0 12px rgba(26,58,92,.06);
    }
    #sidebar.open { width: var(--sidebar-w-open); align-items: flex-start; }

    /* Logo */
    .sb-logo {
      width: 100%; height: var(--topbar-h);
      display: flex; align-items: center; justify-content: center;
      border-bottom: 1px solid #EEF2F7;
      flex-shrink: 0;
      padding: 0 14px;
      gap: 10px;
      overflow: hidden;
    }
    .sb-logo-icon {
      width: 36px; height: 36px; flex-shrink: 0;
      background: linear-gradient(135deg, var(--navy), #2D5F8A);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
    }
    .sb-logo-icon svg { width: 20px; height: 20px; fill: white; }
    .sb-logo-text {
      font-size: 15px; font-weight: 700; color: var(--navy);
      white-space: nowrap; opacity: 0; transition: opacity .15s;
      letter-spacing: -.3px;
    }
    

    /* Nav items */
    .sb-nav {
      flex: 1; width: 100%; overflow-y: auto; overflow-x: hidden;
      padding: 10px 0;
      scrollbar-width: none;
    }
    .sb-nav::-webkit-scrollbar { display: none; }

    .sb-item {
      position: relative;
      display: flex; align-items: center;
      width: calc(100% - 16px);
      margin: 1px 8px;
      padding: 0 10px;
      height: 44px;
      border-radius: 10px;
      text-decoration: none;
      color: #64748B;
      font-weight: 500;
      font-size: 13.5px;
      transition: all .15s;
      cursor: pointer;
      gap: 12px;
      overflow: hidden;
      white-space: nowrap;
    }
    .sb-item:hover {
      background: var(--item-bg, #F1F5F9);
      color: var(--item-color, #1E293B);
    }
    .sb-item.active {
      background: var(--item-bg);
      color: var(--item-color);
      font-weight: 600;
    }
    .sb-item.active::before {
      content: '';
      position: absolute; left: 0; top: 8px; bottom: 8px;
      width: 3px; border-radius: 0 3px 3px 0;
      background: var(--item-color);
    }

    .sb-icon {
      width: 36px; height: 36px; flex-shrink: 0;
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      background: var(--item-bg);
      color: var(--item-color);
      font-size: 15px;
      transition: all .15s;
    }
    .sb-item:hover .sb-icon, .sb-item.active .sb-icon {
      background: var(--item-color);
      color: white;
    }

    .sb-label { opacity: 1; transition: opacity .12s; font-size: 13px; }

    .sb-badge {
      margin-left: auto; flex-shrink: 0;
      background: #EF5350; color: white;
      font-size: 10px; font-weight: 700;
      min-width: 18px; height: 18px;
      border-radius: 9px; padding: 0 5px;
      display: flex; align-items: center; justify-content: center;
      opacity: 0; transition: opacity .12s;
    }
    #sidebar.open .sb-badge { opacity: 1; }

    /* Tooltip en modo colapsado */
    .sb-item::after {
      content: attr(data-tooltip);
      position: absolute; left: calc(var(--sidebar-w) - 4px);
      background: #1E293B; color: white;
      font-size: 12px; font-weight: 500;
      padding: 5px 10px; border-radius: 7px;
      white-space: nowrap; pointer-events: none;
      opacity: 0; transition: opacity .15s;
      z-index: 999;
    }
    /* tooltips desactivados - sidebar siempre visible */

    /* Separador */
    .sb-sep {
      width: calc(100% - 24px); margin: 6px 12px;
      height: 1px; background: #EEF2F7; flex-shrink: 0;
    }


    /* ── Submenú expandible ──────────────────────────────── */
    .sb-has-sub { cursor: pointer; user-select: none; }

    .sb-chevron {
      margin-left: auto; font-size: 10px; color: #94A3B8;
      transition: transform .25s ease; flex-shrink: 0;
    }
    .sb-chevron.rotated { transform: rotate(-180deg); }

    .sb-submenu {
      max-height: 0;
      overflow: hidden;
      transition: max-height .28s ease, opacity .2s ease;
      opacity: 0;
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: 0 8px;
    }
    .sb-submenu.open {
      max-height: 400px;
      opacity: 1;
      padding: 2px 8px 6px 8px;
    }

    .sb-subitem {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 10px 8px 14px; border-radius: 8px;
      font-size: 12.5px; font-weight: 500; color: #64748B;
      text-decoration: none; transition: all .15s;
      border-left: 2px solid transparent;
    }
    .sb-subitem i { font-size: 12px; width: 16px; text-align: center; color: #94A3B8; flex-shrink: 0; }
    .sb-subitem:hover { background: #F1F5F9; color: #1E293B; border-left-color: #CBD5E1; }
    .sb-subitem:hover i { color: #475569; }
    .sb-subitem.active { background: #EDE7F6; color: #4A148C; font-weight: 600; border-left-color: #6A1B9A; }
    .sb-subitem.active i { color: #6A1B9A; }

    /* Toggle btn */
    .sb-toggle { display: none; }
    .sb-toggle:hover { background: #F8FAFC; color: #475569; }

    /* ── Topbar ──────────────────────────────────────────── */
    #topbar {
      position: fixed; left: 220px; right: 0; top: 0;
      height: var(--topbar-h);
      background: #FFFFFF;
      border-bottom: 1px solid #EEF2F7;
      display: flex; align-items: center;
      padding: 0 24px;
      gap: 12px;
      z-index: 99;
      transition: left .22s cubic-bezier(.4,0,.2,1);
      box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }
    

    /* Breadcrumb */
    .topbar-bread {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; color: #94A3B8;
    }
    .topbar-bread .current {
      color: #1E293B; font-weight: 600; font-size: 14px;
    }
    .topbar-bread i { font-size: 10px; }

    /* Buscador */
    .topbar-search {
      flex: 1; max-width: 360px; margin-left: auto;
      position: relative;
    }
    .topbar-search input {
      width: 100%; height: 36px;
      background: #F8FAFC; border: 1px solid #E2E8F0;
      border-radius: 10px; padding: 0 12px 0 36px;
      font-family: var(--font); font-size: 13px; color: #1E293B;
      outline: none; transition: all .15s;
    }
    .topbar-search input:focus {
      border-color: #4A90C4; background: white;
      box-shadow: 0 0 0 3px rgba(74,144,196,.12);
    }
    .topbar-search input::placeholder { color: #94A3B8; }
    .topbar-search .search-icon {
      position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
      color: #94A3B8; font-size: 13px; pointer-events: none;
    }

    /* Acciones topbar */
    .topbar-actions { display: flex; align-items: center; gap: 6px; }
    .topbar-btn {
      width: 36px; height: 36px; border-radius: 10px;
      background: none; border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #64748B; font-size: 16px; transition: all .15s;
      position: relative;
    }
    .topbar-btn:hover { background: #F1F5F9; color: #1E293B; }
    .topbar-btn .notif-dot {
      position: absolute; top: 6px; right: 6px;
      width: 8px; height: 8px; border-radius: 50%;
      background: #EF5350; border: 2px solid white;
    }
    .topbar-btn .notif-count {
      position: absolute; top: 4px; right: 3px;
      background: #EF5350; color: white;
      font-size: 9px; font-weight: 700;
      min-width: 16px; height: 16px; border-radius: 8px;
      padding: 0 3px;
      display: flex; align-items: center; justify-content: center;
      border: 2px solid white;
    }

    /* Avatar usuario */
    .topbar-avatar {
      width: 36px; height: 36px; border-radius: 10px;
      background: linear-gradient(135deg, var(--navy), #2D5F8A);
      color: white; font-size: 12px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; letter-spacing: .5px;
    }

    /* Dropdown usuario */
    .user-dropdown {
      position: relative;
    }
    .user-menu {
      position: absolute; right: 0; top: calc(100% + 8px);
      background: white; border: 1px solid #E2E8F0;
      border-radius: var(--radius); padding: 6px;
      min-width: 200px; box-shadow: var(--shadow-md);
      display: none; z-index: 999;
    }
    .user-menu.show { display: block; }
    .user-menu-header {
      padding: 10px 12px 8px;
      border-bottom: 1px solid #F1F5F9;
      margin-bottom: 4px;
    }
    .user-menu-name { font-weight: 600; color: #1E293B; font-size: 13px; }
    .user-menu-rol  { font-size: 11px; color: #94A3B8; margin-top: 2px; }
    .user-menu a {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 12px; border-radius: 8px;
      font-size: 13px; color: #475569; text-decoration: none;
      transition: all .1s;
    }
    .user-menu a:hover { background: #F8FAFC; color: #1E293B; }
    .user-menu a.danger { color: #EF5350; }
    .user-menu a.danger:hover { background: #FFEBEE; }
    .user-menu a i { width: 16px; text-align: center; }

    /* ── Main content ────────────────────────────────────── */
    #main {
      margin-left: 220px;
      margin-top: var(--topbar-h);
      padding: 28px 28px 40px;
      min-height: calc(100vh - var(--topbar-h));

    }
    

    /* ── Page header ─────────────────────────────────────── */
    .page-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
    }
    .page-title { font-size: 22px; font-weight: 700; color: #0F172A; letter-spacing: -.4px; }
    .page-subtitle { font-size: 13px; color: #64748B; margin-top: 3px; }

    /* ── Botones ─────────────────────────────────────────── */
    .btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 0 16px; height: 38px;
      border-radius: 10px; border: none;
      font-family: var(--font); font-size: 13px; font-weight: 600;
      cursor: pointer; text-decoration: none;
      transition: all .15s; white-space: nowrap;
    }
    .btn-primary { background: var(--navy); color: white; }
    .btn-primary:hover { background: #152E4D; box-shadow: 0 4px 12px rgba(26,58,92,.25); }
    .btn-success { background: #4CAF50; color: white; }
    .btn-success:hover { background: #388E3C; }
    .btn-danger  { background: #EF5350; color: white; }
    .btn-danger:hover  { background: #C62828; }
    .btn-ghost {
      background: white; color: #475569;
      border: 1px solid #E2E8F0;
    }
    .btn-ghost:hover { background: #F8FAFC; border-color: #CBD5E1; color: #1E293B; }
    .btn-sm { height: 32px; padding: 0 12px; font-size: 12px; border-radius: 8px; }
    .btn-icon {
      width: 34px; padding: 0;
      justify-content: center;
    }

    /* ── Cards ───────────────────────────────────────────── */
    .card {
      background: white; border-radius: var(--radius);
      border: 1px solid #EEF2F7;
      box-shadow: var(--shadow);
    }
    .card-header {
      padding: 16px 20px;
      border-bottom: 1px solid #F1F5F9;
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-title { font-size: 14px; font-weight: 600; color: #0F172A; }
    .card-body  { padding: 20px; }

    /* ── KPI cards ───────────────────────────────────────── */
    .kpi-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px; margin-bottom: 24px;
    }
    .kpi-card {
      background: white; border-radius: var(--radius);
      border: 1px solid #EEF2F7;
      padding: 20px;
      display: flex; flex-direction: column; gap: 8px;
      box-shadow: var(--shadow);
      transition: box-shadow .15s;
    }
    .kpi-card:hover { box-shadow: var(--shadow-md); }
    .kpi-label { font-size: 11px; font-weight: 600; color: #94A3B8; text-transform: uppercase; letter-spacing: .06em; }
    .kpi-value { font-size: 32px; font-weight: 800; color: #0F172A; letter-spacing: -1px; line-height: 1; }
    .kpi-sub   { font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 4px; }
    .kpi-icon  {
      width: 40px; height: 40px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; margin-bottom: 4px;
      align-self: flex-start;
    }

    /* ── Tabla ───────────────────────────────────────────── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th {
      text-align: left; padding: 10px 16px;
      font-size: 11px; font-weight: 700; color: #94A3B8;
      text-transform: uppercase; letter-spacing: .06em;
      background: #F8FAFC; border-bottom: 1px solid #EEF2F7;
      white-space: nowrap;
    }
    td {
      padding: 12px 16px; border-bottom: 1px solid #F8FAFC;
      font-size: 13px; color: #334155; vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #FAFBFF; }

    /* ── Badge ───────────────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center;
      padding: 3px 10px; border-radius: 20px;
      font-size: 11.5px; font-weight: 600;
      white-space: nowrap;
    }

    /* ── Formularios ─────────────────────────────────────── */
    .form-group { margin-bottom: 16px; }
    .form-label {
      display: block; font-size: 12px; font-weight: 600; color: #475569;
      margin-bottom: 6px; letter-spacing: .02em;
    }
    .form-label .req { color: #EF5350; }
    .form-control {
      width: 100%; height: 40px;
      border: 1.5px solid #E2E8F0; border-radius: 10px;
      padding: 0 12px;
      font-family: var(--font); font-size: 13.5px; color: #1E293B;
      background: white; outline: none; transition: all .15s;
    }
    .form-control:focus {
      border-color: #4A90C4;
      box-shadow: 0 0 0 3px rgba(74,144,196,.12);
    }
    textarea.form-control { height: auto; padding: 10px 12px; resize: vertical; }
    select.form-control { cursor: pointer; }
    .form-control.mono { font-family: var(--mono); }

    /* ── Modal ───────────────────────────────────────────── */
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(15,23,42,.45);
      backdrop-filter: blur(4px);
      display: none; align-items: center; justify-content: center;
      z-index: 1000; padding: 24px;
    }
    .modal-overlay.show { display: flex; }
    .modal {
      background: white; border-radius: 16px;
      width: 100%; max-width: 680px; max-height: 90vh;
      overflow: hidden; display: flex; flex-direction: column;
      box-shadow: 0 24px 64px rgba(0,0,0,.18);
      animation: modalIn .2s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes modalIn {
      from { opacity: 0; transform: scale(.95) translateY(8px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid #F1F5F9;
      display: flex; align-items: center; justify-content: space-between;
      flex-shrink: 0;
    }
    .modal-title { font-size: 16px; font-weight: 700; color: #0F172A; }
    .modal-close {
      width: 32px; height: 32px; border-radius: 8px;
      background: none; border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #94A3B8; font-size: 18px; transition: all .1s;
    }
    .modal-close:hover { background: #F1F5F9; color: #475569; }
    .modal-body { padding: 24px; overflow-y: auto; }
    .modal-footer {
      padding: 16px 24px;
      border-top: 1px solid #F1F5F9;
      display: flex; align-items: center; justify-content: flex-end;
      gap: 10px; flex-shrink: 0;
      background: #FAFBFF;
    }

    /* ── Empty state ─────────────────────────────────────── */
    .empty-state {
      text-align: center; padding: 60px 20px; color: #94A3B8;
    }
    .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; }
    .empty-state p { font-size: 14px; }

    /* ── SLA indicator ───────────────────────────────────── */
    .sla-bar {
      height: 4px; background: #F1F5F9; border-radius: 2px;
      overflow: hidden; margin-top: 4px; width: 80px;
    }
    .sla-fill { height: 4px; border-radius: 2px; }

    /* ── Paginador ───────────────────────────────────────── */
    .paginator { display: flex; align-items: center; gap: 4px; justify-content: flex-end; padding: 16px 20px; }
    .pag-btn {
      min-width: 32px; height: 32px; border-radius: 8px;
      background: none; border: 1px solid #E2E8F0;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; color: #475569; text-decoration: none;
      transition: all .1s; padding: 0 8px;
    }
    .pag-btn:hover { background: #F1F5F9; border-color: #CBD5E1; }
    .pag-btn.active { background: var(--navy); color: white; border-color: var(--navy); font-weight: 600; }

    /* ── Scrollbar global ────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

    /* ── Toast ───────────────────────────────────────────── */
    #toast-container {
      position: fixed; bottom: 24px; right: 24px;
      z-index: 9999; display: flex; flex-direction: column; gap: 8px;
    }
    .toast {
      background: #1E293B; color: white;
      border-radius: 12px; padding: 12px 18px;
      font-size: 13px; font-weight: 500;
      box-shadow: 0 8px 24px rgba(0,0,0,.2);
      display: flex; align-items: center; gap: 10px;
      animation: toastIn .3s cubic-bezier(.34,1.56,.64,1);
      min-width: 280px; max-width: 360px;
    }
    @keyframes toastIn {
      from { opacity: 0; transform: translateX(20px); }
      to   { opacity: 1; transform: translateX(0); }
    }
    .toast.success { border-left: 4px solid #4CAF50; }
    .toast.error   { border-left: 4px solid #EF5350; }
    .toast.warning { border-left: 4px solid #FF6B00; }

    /* ── Mono ────────────────────────────────────────────── */
    .mono { font-family: var(--mono); }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 768px) {
      #main { padding: 16px; }
      .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<aside id="sidebar">

  <!-- Logo -->
  <div class="sb-logo">
    <div class="sb-logo-icon">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L3 7v10l9 5 9-5V7L12 2zm0 2.3l6.9 3.8-6.9 3.8L5.1 8.1 12 4.3zm-8 4.9l7 3.9v7.6l-7-3.9V9.2zm9 11.5v-7.6l7-3.9v7.6l-7 3.9z"/>
      </svg>
    </div>
    <span class="sb-logo-text">Aleatica ITSM</span>
  </div>

  <!-- Nav -->
  <nav class="sb-nav">
    <?php foreach ($nav as $item):
      if (!empty($item['adminOnly']) && !Auth::isAdmin()) continue;
      $active = $pageModule === $item['id'];
      $badgeVal = '';
      if (!empty($item['badge'])) {
        $cnt = ($item['badge'])();
        if ($cnt > 0) $badgeVal = $cnt > 99 ? '99+' : (string)$cnt;
      }
    ?>
    <?php if (!empty($item['submenu'])): ?>
    <div class="sb-item-group <?= $active ? 'active' : '' ?>"
         style="--item-color:<?= $item['color'] ?>;--item-bg:<?= $item['bg'] ?>;">
      <div class="sb-item sb-has-sub <?= $active ? 'active' : '' ?>"
           data-tooltip="<?= h($item['label']) ?>"
           onclick="toggleSubmenu('sub-<?= $item['id'] ?>')"
           style="--item-color:<?= $item['color'] ?>;--item-bg:<?= $item['bg'] ?>;">
        <div class="sb-icon"><i class="fas <?= h($item['icon']) ?>"></i></div>
        <span class="sb-label"><?= h($item['label']) ?></span>
        <i class="fas fa-chevron-down sb-chevron" id="chev-<?= $item['id'] ?>"></i>
      </div>
      <div class="sb-submenu" id="sub-<?= $item['id'] ?>">
        <?php foreach ($item['submenu'] as $sub): ?>
        <a href="<?= h($sub['url']) ?>"
           class="sb-subitem <?= str_contains($_SERVER['REQUEST_URI'] ?? '', basename($sub['url'])) ? 'active' : '' ?>">
          <i class="fas <?= h($sub['icon']) ?>"></i>
          <span><?= h($sub['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= h($item['url']) ?>"
       class="sb-item <?= $active ? 'active' : '' ?>"
       data-tooltip="<?= h($item['label']) ?>"
       style="--item-color:<?= $item['color'] ?>;--item-bg:<?= $item['bg'] ?>;">
      <div class="sb-icon"><i class="fas <?= h($item['icon']) ?>"></i></div>
      <span class="sb-label"><?= h($item['label']) ?></span>
      <?php if ($badgeVal): ?>
        <span class="sb-badge"><?= $badgeVal ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sb-sep"></div>

  <!-- Toggle -->
  <button class="sb-toggle" id="sbToggle" title="Expandir menú">
    <i class="fas fa-sidebar" id="sbToggleIcon"></i>
  </button>

</aside>

<!-- ═══════════════════════════════════════════
     TOPBAR
═══════════════════════════════════════════ -->
<header id="topbar">

  <!-- Breadcrumb -->
  <div class="topbar-bread">
    <span>Aleatica</span>
    <i class="fas fa-chevron-right"></i>
    <span class="current"><?= h($pageTitle) ?></span>
  </div>

  <!-- Búsqueda global -->
  <div class="topbar-search">
    <i class="fas fa-magnifying-glass search-icon"></i>
    <input type="text" placeholder="Buscar tickets, CIs, usuarios..." id="globalSearch" autocomplete="off">
  </div>

  <!-- Acciones -->
  <div class="topbar-actions">

    <!-- Nuevo ticket rápido -->
    <button class="btn btn-primary btn-sm" onclick="abrirModalTicketRapido()">
      <i class="fas fa-plus"></i>
      <span>Nuevo</span>
    </button>

    <!-- Notificaciones -->
    <button class="topbar-btn" id="btnNotif" title="Notificaciones">
      <i class="fas fa-bell"></i>
      <?php if ($notifCount > 0): ?>
        <span class="notif-count"><?= min($notifCount, 99) ?></span>
      <?php endif; ?>
    </button>

    <!-- Tema -->
    <button class="topbar-btn" id="btnTheme" title="Tema" onclick="toggleTheme()">
      <i class="fas fa-sun"></i>
    </button>

    <!-- Usuario -->
    <div class="user-dropdown">
      <div class="topbar-avatar" id="btnUser"><?= $userInicial ?></div>
      <div class="user-menu" id="userMenu">
        <div class="user-menu-header">
          <div class="user-menu-name"><?= h(Auth::userName()) ?></div>
          <div class="user-menu-rol"><?= h(Auth::userRol()) ?></div>
        </div>
        <a href="<?= BASE_URL ?>/modules/admin/mi_perfil.php">
          <i class="fas fa-user"></i> Mi perfil
        </a>
        <a href="<?= BASE_URL ?>/modules/admin/configuracion.php">
          <i class="fas fa-gear"></i> Configuración
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="danger">
          <i class="fas fa-arrow-right-from-bracket"></i> Cerrar sesión
        </a>
      </div>
    </div>

  </div>
</header>

<!-- ═══════════════════════════════════════════
     CONTENIDO PRINCIPAL
═══════════════════════════════════════════ -->
<main id="main">

<!-- Flash messages -->
<?= renderFlash() ?>

<!-- Aquí va el contenido de cada página -->
<?= $pageContent ?? '' ?>

<?php
function endLayout(): void {
    global $pageTitle;
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
</main><!-- /#main -->

<div id="toast-container"></div>

<script>
// ── Sidebar toggle ─────────────────────────────────────────
const sidebar    = document.getElementById('sidebar');
const toggleBtn  = document.getElementById('sbToggle');
const toggleIcon = document.getElementById('sbToggleIcon');

// Sidebar siempre abierto por defecto
let sbOpen = localStorage.getItem('sb_open') !== '0'; // default: abierto
applySidebar();

function applySidebar() {
    if (sbOpen) {
        sidebar.classList.add('open');
        document.body.classList.add('sb-open');
        if (toggleIcon) toggleIcon.className = 'fas fa-sidebar-flip';
    } else {
        sidebar.classList.remove('open');
        document.body.classList.remove('sb-open');
        if (toggleIcon) toggleIcon.className = 'fas fa-sidebar';
    }
}
toggleBtn.onclick = () => {
    sbOpen = !sbOpen;
    localStorage.setItem('sb_open', sbOpen ? '1' : '0');
    applySidebar();
};

// ── Dropdown usuario ───────────────────────────────────────
document.getElementById('btnUser')?.addEventListener('click', (e) => {
    e.stopPropagation();
    document.getElementById('userMenu')?.classList.toggle('show');
});
document.addEventListener('click', () => {
    document.getElementById('userMenu')?.classList.remove('show');
});

// ── Modal genérico ─────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('show'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});

// ── Toast ──────────────────────────────────────────────────
function toast(msg, tipo = 'info', ms = 3500) {
    const icons = { success: 'fa-check-circle', error: 'fa-xmark-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const t = document.createElement('div');
    t.className = `toast ${tipo}`;
    t.innerHTML = `<i class="fas ${icons[tipo]||icons.info}"></i><span>${msg}</span>`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.style.opacity = '0', ms);
    setTimeout(() => t.remove(), ms + 400);
}

// ── Confirmar ──────────────────────────────────────────────
function confirmar(msg, url) {
    if (confirm(msg || '¿Estás seguro?')) window.location.href = url;
}

// ── Búsqueda global ────────────────────────────────────────
const gsearch = document.getElementById('globalSearch');
gsearch?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && gsearch.value.trim().length > 1) {
        window.location.href = '<?= $baseUrl ?>/busqueda.php?q=' + encodeURIComponent(gsearch.value.trim());
    }
});

// ── Ticket rápido ──────────────────────────────────────────
function abrirModalTicketRapido() { openModal('modalTicketRapido'); }


// ── Submenú Admin ──────────────────────────────────────────
function toggleSubmenu(id) {
    const sub  = document.getElementById(id);
    const modId = id.replace('sub-','');
    const chev = document.getElementById('chev-' + modId);
    if (!sub) return;
    const isOpen = sub.classList.contains('open');
    // Cerrar todos
    document.querySelectorAll('.sb-submenu.open').forEach(s => s.classList.remove('open'));
    document.querySelectorAll('.sb-chevron.rotated').forEach(c => c.classList.remove('rotated'));
    // Abrir el clickeado si estaba cerrado
    if (!isOpen) {
        sub.classList.add('open');
        if (chev) chev.classList.add('rotated');
    }
}
// Abrir submenú activo al cargar
document.querySelectorAll('.sb-item-group.active .sb-submenu').forEach(s => {
    s.classList.add('open');
    const modId = s.id.replace('sub-','');
    const chev = document.getElementById('chev-' + modId);
    if (chev) chev.classList.add('rotated');
});

// ── Atajos de teclado ──────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === '/' && !['INPUT','TEXTAREA'].includes(e.target.tagName)) {
        e.preventDefault(); gsearch?.focus();
    }
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.user-menu.show').forEach(m => m.classList.remove('show'));
    }
});
</script>

<!-- Modal ticket rápido -->
<div class="modal-overlay" id="modalTicketRapido">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-ticket" style="color:#1565C0;margin-right:8px;"></i>Nuevo Ticket Rápido</span>
      <button class="modal-close" onclick="closeModal('modalTicketRapido')"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <form action="<?= $baseUrl ?>/modules/itsm/tickets.php" method="POST" id="formTicketRapido">
        <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Tipo</label>
          <select name="tipo" class="form-control">
            <option value="Solicitud">📋 Solicitud de Servicio</option>
            <option value="Incidente">🔴 Incidente</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Asunto <span class="req">*</span></label>
          <input type="text" name="titulo" class="form-control" placeholder="Describe brevemente el problema" required>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="3" placeholder="Más detalles..."></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" class="form-control">
              <option value="Media" selected>🟡 Media</option>
              <option value="Alta">🟠 Alta</option>
              <option value="Critica">🔴 Crítica</option>
              <option value="Baja">🟢 Baja</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Impacto</label>
            <select name="impacto" class="form-control">
              <option value="Medio">Medio</option>
              <option value="Alto">Alto</option>
              <option value="Bajo">Bajo</option>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modalTicketRapido')">Cancelar</button>
      <button class="btn btn-primary" onclick="document.getElementById('formTicketRapido').submit()">
        <i class="fas fa-paper-plane"></i> Crear ticket
      </button>
    </div>
  </div>
</div>

</body>
</html>
<?php
} // fin endLayout()
?>
