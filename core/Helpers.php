<?php
// ============================================================
// ITSM ALEATICA — Helpers.php
// Funciones de utilidad global
// ============================================================

// ── Sanitización ──────────────────────────────────────────────
function h(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function clean(mixed $val): string {
    return trim(strip_tags((string)($val ?? '')));
}

// ── Redirección ───────────────────────────────────────────────
function redirect(string $url, int $code = 302): never {
    http_response_code($code);
    header("Location: {$url}");
    exit;
}

function redirectBack(string $fallback = '/'): never {
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL . $fallback);
}

// ── Paginador HTML ────────────────────────────────────────────
function paginator(int $total, int $perPage, int $page, string $baseUrl): string {
    if ($total <= $perPage) return '';
    $pages = (int)ceil($total / $perPage);
    $sep   = str_contains($baseUrl, '?') ? '&' : '?';
    $html  = '<nav><ul class="pagination pagination-sm mb-0">';
    if ($page > 1)
        $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}{$sep}page=" . ($page-1) . "'><i class='fa-solid fa-chevron-left'></i></a></li>";
    for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++) {
        $active = $i === $page ? ' active' : '';
        $html  .= "<li class='page-item{$active}'><a class='page-link' href='{$baseUrl}{$sep}page={$i}'>{$i}</a></li>";
    }
    if ($page < $pages)
        $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}{$sep}page=" . ($page+1) . "'><i class='fa-solid fa-chevron-right'></i></a></li>";
    $html .= '</ul></nav>';
    return $html;
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $tipo, string $msg): void {
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $msg];
}

function renderFlash(): string {
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $config = match($f['tipo']) {
            'success' => ['bg'=>'#E8F5E9','border'=>'#A5D6A7','text'=>'#2E7D32','icon'=>'fa-circle-check'],
            'error'   => ['bg'=>'#FFEBEE','border'=>'#FFCDD2','text'=>'#C62828','icon'=>'fa-circle-xmark'],
            'warning' => ['bg'=>'#FFF3E0','border'=>'#FFCC80','text'=>'#E65100','icon'=>'fa-triangle-exclamation'],
            default   => ['bg'=>'#E3F2FD','border'=>'#90CAF9','text'=>'#1565C0','icon'=>'fa-circle-info'],
        };
        $html .= sprintf(
            '<div class="flash-msg" style="background:%s;border:1px solid %s;color:%s;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;display:flex;align-items:center;gap:10px;">
                <i class="fas %s"></i><span>%s</span>
                <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:18px;">x</button>
             </div>',
            $config['bg'], $config['border'], $config['text'], $config['icon'], h($f['msg'])
        );
    }
    unset($_SESSION['flash']);
    return $html;
}

// ── Fechas ────────────────────────────────────────────────────
function fechaHumana(?string $fecha, bool $hora = false): string {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    return $hora ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

function tiempoRelativo(?string $fecha): string {
    if (!$fecha) return '—';
    $diff = time() - strtotime($fecha);
    if ($diff < 60)     return 'hace un momento';
    if ($diff < 3600)   return 'hace ' . floor($diff/60) . ' min';
    if ($diff < 86400)  return 'hace ' . floor($diff/3600) . ' hrs';
    if ($diff < 604800) return 'hace ' . floor($diff/86400) . ' dias';
    return fechaHumana($fecha);
}

function diffMinutos(?string $desde, ?string $hasta = null): int {
    if (!$desde) return 0;
    $hasta = $hasta ?? date('Y-m-d H:i:s');
    return (int)(abs(strtotime($hasta) - strtotime($desde)) / 60);
}

// ── Formateo numerico ─────────────────────────────────────────
function moneda(float $val, string $mon = 'PEN'): string {
    $sym = match($mon) { 'USD' => '$', 'MXN' => '$MX', default => 'S/' };
    return $sym . ' ' . number_format($val, 2, '.', ',');
}

function numero(int|float $val): string {
    return number_format($val, 0, '.', ',');
}

// ── Texto ─────────────────────────────────────────────────────
function truncate(string $str, int $len): string {
    return mb_strlen($str) > $len ? mb_substr($str, 0, $len) . '...' : $str;
}

function formatBytes(int $bytes): string {
    if ($bytes <= 0)       return '0 B';
    if ($bytes < 1024)     return $bytes . ' B';
    if ($bytes < 1048576)  return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ── Badges HTML ───────────────────────────────────────────────
function badgePrioridad(string $p): string {
    $cfg = match($p) {
        'Critica' => ['#FFF0F0','#EF5350'],
        'Alta'    => ['#FFF3E0','#FF6B00'],
        'Media'   => ['#FFF9C4','#F9A825'],
        default   => ['#E8F5E9','#4CAF50'],
    };
    return "<span class='badge' style='background:{$cfg[0]};color:{$cfg[1]};border:1px solid {$cfg[1]}40;'>{$p}</span>";
}

function badgeEstado(string $e): string {
    $cfg = match($e) {
        'Nuevo'      => ['#EDE7F6','#6A1B9A'],
        'Asignado'   => ['#E3F2FD','#1565C0'],
        'En_proceso' => ['#E8F4FD','#0277BD'],
        'En_espera'  => ['#FFF3E0','#E65100'],
        'Resuelto'   => ['#E8F5E9','#2E7D32'],
        'Cerrado'    => ['#F5F5F5','#616161'],
        'Cancelado'  => ['#FFEBEE','#C62828'],
        'Activo'     => ['#E8F5E9','#2E7D32'],
        'Inactivo'   => ['#F5F5F5','#616161'],
        'Vencido'    => ['#FFEBEE','#C62828'],
        'Pagado'     => ['#E8F5E9','#2E7D32'],
        'Pendiente'  => ['#FFF3E0','#E65100'],
        default      => ['#F5F5F5','#475569'],
    };
    $label = str_replace('_', ' ', $e);
    return "<span class='badge' style='background:{$cfg[0]};color:{$cfg[1]};border:1px solid {$cfg[1]}30;'>{$label}</span>";
}

function badgeTipo(string $t): string {
    $cfg = match($t) {
        'Solicitud' => ['#E3F2FD','#1565C0'],
        'Incidente' => ['#FFEBEE','#C62828'],
        'Evento'    => ['#FFF3E0','#E65100'],
        'Alerta'    => ['#FFF9C4','#F57F17'],
        default     => ['#F5F5F5','#475569'],
    };
    return "<span class='badge' style='background:{$cfg[0]};color:{$cfg[1]};border:1px solid {$cfg[1]}30;'>{$t}</span>";
}

// ── Numero de ticket ──────────────────────────────────────────
function generarNumeroTicket(): string {
    $siguiente = (int) DB::value("SELECT valor FROM adm_configuracion WHERE clave='ticket_siguiente_num'");
    $prefijo   = DB::value("SELECT valor FROM adm_configuracion WHERE clave='ticket_prefijo'") ?: 'TKT';
    $numero    = $prefijo . '-' . date('Y') . '-' . str_pad($siguiente, 5, '0', STR_PAD_LEFT);
    DB::exec("UPDATE adm_configuracion SET valor=? WHERE clave='ticket_siguiente_num'", [$siguiente + 1]);
    return $numero;
}

// ── Calcular limite SLA ───────────────────────────────────────
function calcularLimiteSLA(string $prioridad, string $tipo = 'respuesta'): ?string {
    $politica = DB::row(
        "SELECT * FROM itsm_sla_politicas WHERE prioridad=? AND activa=1 LIMIT 1",
        [$prioridad]
    );
    if (!$politica) return null;
    $minutos = $tipo === 'respuesta'
        ? $politica['tiempo_respuesta_min']
        : $politica['tiempo_resolucion_min'];
    return date('Y-m-d H:i:s', strtotime("+{$minutos} minutes"));
}

// ── POST helper ───────────────────────────────────────────────
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}
function postInt(string $key, int $default = 0): int {
    return (int)($_POST[$key] ?? $default);
}
function postClean(string $key, string $default = ''): string {
    return clean($_POST[$key] ?? $default);
}

// ── Notificaciones no leidas ──────────────────────────────────
function contarNotificaciones(): int {
    $uid = Auth::userId();
    if (!$uid) return 0;
    return (int) DB::value(
        "SELECT COUNT(*) FROM adm_notificaciones WHERE usuario_id=? AND leida=0", [$uid]
    );
}
