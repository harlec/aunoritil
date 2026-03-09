<?php
// ============================================================
// ITSM ALEATICA — Audit.php
// ============================================================
class Audit {
    public static function log(
        string  $tabla,
        ?int    $registroId,
        string  $accion,
        ?array  $anterior  = null,
        ?array  $nuevo     = null,
        ?string $descripcion = null
    ): void {
        try {
            DB::insertRow('adm_auditoria', [
                'tabla'            => $tabla,
                'registro_id'      => $registroId,
                'accion'           => $accion,
                'datos_anteriores' => $anterior  ? json_encode($anterior,  JSON_UNESCAPED_UNICODE) : null,
                'datos_nuevos'     => $nuevo      ? json_encode($nuevo,     JSON_UNESCAPED_UNICODE) : null,
                'usuario_id'       => Auth::userId(),
                'ip'               => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'descripcion'      => $descripcion,
            ]);
        } catch (Throwable) {
            // Silencioso: auditoría no debe romper el flujo
        }
    }
}
