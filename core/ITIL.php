<?php
// ============================================================
// ITSM ALEATICA — ITIL.php
// Lógica de negocio ITIL 4
// ============================================================
class ITIL {

    // ── Matriz de prioridad (Urgencia x Impacto) ──────────────
    private static array $matrizPrioridad = [
        'Alta'  => ['Alto' => 'Critica', 'Medio' => 'Alta',  'Bajo' => 'Media'],
        'Media' => ['Alto' => 'Alta',    'Medio' => 'Media', 'Bajo' => 'Media'],
        'Baja'  => ['Alto' => 'Media',   'Medio' => 'Baja',  'Bajo' => 'Baja'],
    ];

    public static function calcularPrioridad(string $urgencia, string $impacto): string {
        return self::$matrizPrioridad[$urgencia][$impacto] ?? 'Media';
    }

    // ── Color de prioridad ────────────────────────────────────
    public static function colorPrioridad(string $p): string {
        return match($p) {
            'Critica' => '#EF5350',
            'Alta'    => '#FF6B00',
            'Media'   => '#F9A825',
            default   => '#4CAF50',
        };
    }

    // ── SLA: calcular límites ─────────────────────────────────
    public static function calcularSLA(string $prioridad, ?int $categoriaId = null): array {
        $politica = DB::row(
            "SELECT * FROM itsm_sla_politicas
             WHERE prioridad = ?
               AND activa = 1
             ORDER BY aplica_categoria_id IS NOT NULL DESC,
                      aplica_categoria_id = ? DESC
             LIMIT 1",
            [$prioridad, $categoriaId ?? 0]
        );

        if (!$politica) {
            return ['respuesta' => null, 'resolucion' => null];
        }

        $ahora = time();
        return [
            'respuesta'  => date('Y-m-d H:i:s', $ahora + ($politica['tiempo_respuesta_min']  * 60)),
            'resolucion' => date('Y-m-d H:i:s', $ahora + ($politica['tiempo_resolucion_min'] * 60)),
        ];
    }

    // ── SLA: estado respecto a límite ────────────────────────
    public static function estadoSLA(?string $limite, ?int $cumplido = null): array {
        if ($cumplido === 1) return ['color' => '#4CAF50', 'label' => 'Cumplido', 'icon' => 'fa-circle-check'];
        if ($cumplido === 0) return ['color' => '#EF5350', 'label' => 'Incumplido', 'icon' => 'fa-circle-xmark'];
        if (!$limite)        return ['color' => '#94A3B8', 'label' => 'Sin SLA', 'icon' => 'fa-circle-minus'];

        $diff = strtotime($limite) - time();
        if ($diff < 0)          return ['color' => '#EF5350', 'label' => 'Vencido',      'icon' => 'fa-triangle-exclamation'];
        if ($diff < 3600)       return ['color' => '#FF6B00', 'label' => 'Critico',       'icon' => 'fa-clock'];
        if ($diff < 7200)       return ['color' => '#F9A825', 'label' => 'En riesgo',     'icon' => 'fa-clock'];
        return                         ['color' => '#4CAF50', 'label' => 'En tiempo',     'icon' => 'fa-clock'];
    }

    // ── Número correlativo ────────────────────────────────────
    public static function siguiente(string $prefijo, string $tabla, string $campo = 'numero'): string {
        $ultimo = DB::value(
            "SELECT MAX(CAST(SUBSTRING_INDEX({$campo}, '-', -1) AS UNSIGNED)) FROM {$tabla} WHERE {$campo} LIKE ?",
            ["{$prefijo}-" . date('Y') . "-%"]
        );
        $sig = ($ultimo ?? 0) + 1;
        return $prefijo . '-' . date('Y') . '-' . str_pad($sig, 5, '0', STR_PAD_LEFT);
    }

    // ── Crear notificación ────────────────────────────────────
    public static function notificar(int $usuarioId, string $tipo, string $titulo, string $msg, string $url = '', string $entidadTipo = '', ?int $entidadId = null): void {
        $iconos = [
            'ticket_nuevo'     => 'fa-ticket',
            'ticket_asignado'  => 'fa-user-check',
            'sla_riesgo'       => 'fa-clock',
            'garantia_vence'   => 'fa-shield',
            'contrato_vence'   => 'fa-file-contract',
            'cambio_aprobado'  => 'fa-circle-check',
        ];
        DB::insertRow('adm_notificaciones', [
            'usuario_id'   => $usuarioId,
            'tipo'         => $tipo,
            'titulo'       => $titulo,
            'mensaje'      => $msg,
            'icono'        => $iconos[$tipo] ?? 'fa-bell',
            'url_destino'  => $url,
            'entidad_tipo' => $entidadTipo,
            'entidad_id'   => $entidadId,
        ]);
    }

    // ── Porcentaje SLA global del período ─────────────────────
    public static function slaGlobal(string $periodo): array {
        $stats = DB::row(
            "SELECT
                COUNT(*) total,
                SUM(CASE WHEN sla_resolucion_cumplido = 1 THEN 1 ELSE 0 END) cumplidos,
                SUM(CASE WHEN sla_resolucion_cumplido = 0 THEN 1 ELSE 0 END) incumplidos
             FROM itsm_tickets
             WHERE DATE_FORMAT(fecha_apertura,'%Y-%m') = ?
               AND estado IN ('Resuelto','Cerrado')
               AND sla_resolucion_cumplido IS NOT NULL",
            [$periodo]
        );
        $pct = $stats['total'] > 0
            ? round($stats['cumplidos'] / $stats['total'] * 100, 1)
            : 100;
        return ['porcentaje' => $pct, ...$stats];
    }
}
