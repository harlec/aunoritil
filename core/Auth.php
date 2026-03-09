<?php
// ============================================================
// ITSM ALEATICA — Auth.php
// ============================================================

class Auth {

    // ── Login ─────────────────────────────────────────────────
    public static function login(string $email, string $password): bool {
        $user = DB::row(
            "SELECT u.*, r.nombre as rol_nombre, r.permisos as rol_permisos
             FROM adm_usuarios u
             JOIN adm_roles r ON r.id = u.rol_id
             WHERE u.email = ? AND u.activo = 1",
            [trim($email)]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Audit::log('adm_usuarios', null, 'LOGIN', null, null,
                "Intento fallido para: {$email}");
            return false;
        }

        // Rehash si es necesario
        if (password_needs_rehash($user['password_hash'], HASH_ALGO, ['cost' => HASH_COST])) {
            $newHash = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
            DB::exec("UPDATE adm_usuarios SET password_hash=? WHERE id=?", [$newHash, $user['id']]);
        }

        // Guardar sesión
        $_SESSION['usuario_id']     = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_email']  = $user['email'];
        $_SESSION['usuario_rol']    = $user['rol_nombre'];
        $_SESSION['usuario_tipo']   = $user['tipo'];
        $_SESSION['usuario_permisos'] = json_decode($user['rol_permisos'] ?? '[]', true);
        $_SESSION['usuario_grupo']  = $user['grupo_id'];
        $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
        $_SESSION['login_time']     = time();

        DB::exec("UPDATE adm_usuarios SET ultimo_login=NOW() WHERE id=?", [$user['id']]);
        Audit::log('adm_usuarios', $user['id'], 'LOGIN');

        return true;
    }

    // ── Logout ────────────────────────────────────────────────
    public static function logout(): void {
        Audit::log('adm_usuarios', self::userId(), 'LOGOUT');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    // ── Verificar autenticación ───────────────────────────────
    public static function check(): bool {
        return isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] > 0;
    }

    // ── Requerir login (redirige si no autenticado) ───────────
    public static function requireLogin(string $tipo = 'admin'): void {
        if (!self::check()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header("Location: " . BASE_URL . "/login.php?redirect={$redirect}");
            exit;
        }
        if ($tipo === 'admin' && $_SESSION['usuario_tipo'] === 'Usuario_Final') {
            header("Location: " . BASE_URL . "/portal/");
            exit;
        }
    }

    // ── Verificar permiso ─────────────────────────────────────
    public static function can(string $permiso): bool {
        $permisos = $_SESSION['usuario_permisos'] ?? [];
        if (in_array('*', $permisos)) return true; // Admin total
        if (in_array($permiso, $permisos)) return true;
        // Verificar wildcard: tickets.* cubre tickets.ver, tickets.crear...
        [$modulo] = explode('.', $permiso);
        return in_array("{$modulo}.*", $permisos);
    }

    // ── Requerir permiso específico ───────────────────────────
    public static function requireCan(string $permiso): void {
        if (!self::can($permiso)) {
            http_response_code(403);
            include ROOT_PATH . '/includes/403.php';
            exit;
        }
    }

    // ── Es admin ──────────────────────────────────────────────
    public static function isAdmin(): bool {
        return in_array('*', $_SESSION['usuario_permisos'] ?? []);
    }

    // ── Es agente (no usuario final) ──────────────────────────
    public static function isAgent(): bool {
        return in_array($_SESSION['usuario_tipo'] ?? '', ['Agente', 'Admin']);
    }

    // ── Getters ───────────────────────────────────────────────
    public static function userId(): ?int {
        return $_SESSION['usuario_id'] ?? null;
    }
    public static function userName(): string {
        return $_SESSION['usuario_nombre'] ?? 'Desconocido';
    }
    public static function userEmail(): string {
        return $_SESSION['usuario_email'] ?? '';
    }
    public static function userRol(): string {
        return $_SESSION['usuario_rol'] ?? '';
    }

    // ── CSRF Token ────────────────────────────────────────────
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die(json_encode(['error' => 'Token CSRF inválido']));
        }
    }

    // ── Input hidden con token CSRF ───────────────────────────
    public static function csrfInput(): string {
        return '<input type="hidden" name="_csrf" value="' . self::csrfToken() . '">';
    }
}
