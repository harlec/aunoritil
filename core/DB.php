<?php
// ============================================================
// ITSM ALEATICA — DB.php
// Wrapper PDO con soporte conexión dual (principal + monitoreo)
// ============================================================

class DB {
    private static ?PDO $instance = null;
    private static ?PDO $monitor  = null;

    // ── Conexión principal ────────────────────────────────────
    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                self::error('BD principal no disponible', $e);
            }
        }
        return self::$instance;
    }

    // ── Conexión monitoreo (opcional, solo lectura) ───────────
    public static function monitor(): ?PDO {
        if (!MONITOR_ACTIVO || !MONITOR_HOST) return null;
        if (self::$monitor === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                MONITOR_HOST, MONITOR_PORT, MONITOR_DB);
            try {
                self::$monitor = new PDO($dsn, MONITOR_USER, MONITOR_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('ITSM Monitor BD error: ' . $e->getMessage());
                return null;
            }
        }
        return self::$monitor;
    }

    // ── SELECT que retorna múltiples filas ────────────────────
    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── SELECT que retorna una fila ───────────────────────────
    public static function row(string $sql, array $params = []): ?array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── SELECT que retorna un valor escalar ───────────────────
    public static function value(string $sql, array $params = []): mixed {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    // ── INSERT / UPDATE / DELETE ──────────────────────────────
    public static function exec(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // ── INSERT y retorna el ID insertado ──────────────────────
    public static function insert(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return (int) self::get()->lastInsertId();
    }

    // ── INSERT con array asociativo ───────────────────────────
    public static function insertRow(string $tabla, array $datos): int {
        $cols = implode(', ', array_keys($datos));
        $ph   = implode(', ', array_fill(0, count($datos), '?'));
        $sql  = "INSERT INTO {$tabla} ({$cols}) VALUES ({$ph})";
        return self::insert($sql, array_values($datos));
    }

    // ── UPDATE con array asociativo ───────────────────────────
    public static function updateRow(string $tabla, array $datos, string $where, array $whereParams = []): int {
        $set = implode(' = ?, ', array_keys($datos)) . ' = ?';
        $sql = "UPDATE {$tabla} SET {$set} WHERE {$where}";
        return self::exec($sql, [...array_values($datos), ...$whereParams]);
    }

    // ── Paginación ────────────────────────────────────────────
    public static function paginate(string $sql, array $params, int $page, int $perPage = PER_PAGE): array {
        // Total
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _count";
        $total    = (int) self::value($countSql, $params);
        // Datos
        $offset = ($page - 1) * $perPage;
        $rows   = self::query("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);
        return [
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    // ── Manejo de errores ─────────────────────────────────────
    private static function error(string $msg, PDOException $e): void {
        if (ENTORNO === 'desarrollo') {
            die("<pre style='color:red;padding:20px;'><b>DB Error:</b> {$msg}\n" . $e->getMessage() . "</pre>");
        }
        error_log("ITSM DB Error: {$msg} — " . $e->getMessage());
        die('Error de base de datos. Contacte al administrador.');
    }
}

// Alias global para compatibilidad
function db(): PDO { return DB::get(); }
