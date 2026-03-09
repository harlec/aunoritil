<?php
// ============================================================
// ITSM ALEATICA v2.0 — CONFIGURACIÓN CENTRAL
// Ajustar credenciales según entorno (MAMP / LAMP)
// ============================================================

// ── ENTORNO ──────────────────────────────────────────────────
define('ENTORNO', 'desarrollo'); // 'desarrollo' | 'produccion'
define('APP_VERSION', '2.0.0');
define('APP_NOMBRE', 'Aleatica ITSM');
define('APP_EMPRESA', 'Autopista del Norte S.A.C.');

// ── BASE DE DATOS PRINCIPAL ───────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');        // MAMP default: 8889
define('DB_NAME',    'itsm_aleatica');
define('DB_USER',    'itsm_user');   // Usuario creado en Plesk > BD
define('DB_PASS',    'ikm169uhn');             // Password del usuario de BD en Plesk
define('DB_CHARSET', 'utf8mb4');

// ── BASE DE DATOS MONITOREO (red interna) ─────────────────────
define('MONITOR_ACTIVO', false);  // Cambiar a true cuando esté disponible
define('MONITOR_HOST', '');       // IP servidor monitoreo ej: 192.168.1.100
define('MONITOR_PORT', '3306');
define('MONITOR_DB',   '');       // Nombre BD monitoreo
define('MONITOR_USER', '');       // Solo lectura
define('MONITOR_PASS', '');

// ── RUTAS ─────────────────────────────────────────────────────
define('ROOT_PATH',    __DIR__);
define('MODULES_PATH', __DIR__ . '/modules');
define('UPLOADS_PATH', __DIR__ . '/uploads');
define('CORE_PATH',    __DIR__ . '/core');

// URL base: ajustar según MAMP (http://localhost:8888/itsm-aleatica)
define('BASE_URL', 'https://aunoritil.harlec.com.pe'); // Ajustar según dominio

// ── SESIONES ──────────────────────────────────────────────────
define('SESSION_LIFETIME', 28800); // 8 horas en segundos
define('SESSION_NAME', 'itsm_session');

// ── SEGURIDAD ─────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', 'itsm_csrf');
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

// ── UPLOADS ───────────────────────────────────────────────────
define('UPLOAD_MAX_MB', 10);
define('UPLOAD_TIPOS_DOC', ['pdf','doc','docx','xls','xlsx','csv']);
define('UPLOAD_TIPOS_IMG', ['jpg','jpeg','png','gif','webp']);

// ── PAGINACIÓN ────────────────────────────────────────────────
define('PER_PAGE', 25);

// ── ZONA HORARIA ──────────────────────────────────────────────
date_default_timezone_set('America/Lima');

// ── MANEJO DE ERRORES ─────────────────────────────────────────
if (ENTORNO === 'desarrollo') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ── AUTOLOAD CORE ─────────────────────────────────────────────
spl_autoload_register(function ($class) {
    $file = CORE_PATH . '/' . $class . '.php';
    if (file_exists($file)) require_once $file;
});

require_once CORE_PATH . '/DB.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/ITIL.php';
require_once CORE_PATH . '/Audit.php';
require_once CORE_PATH . '/Helpers.php';

// ── INICIO DE SESIÓN ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false, // true en producción con HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
