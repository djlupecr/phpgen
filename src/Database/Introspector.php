<?php

namespace PhpGen\Database;

use PDO;
use PhpGen\Config;
use PhpGen\Validation\BrValidator;

class Introspector
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo) {
            $this->pdo = $pdo;
            return;
        }
        $cfg = Config::$db;
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function assertValidTable(string $table): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Nome de tabela inválido: $table");
        }
        if (!in_array($table, $this->listTables(), true)) {
            throw new \InvalidArgumentException("Tabela não existe: $table");
        }
    }

    public function assertValidColumn(string $table, string $column): void
    {
        $schema = $this->tableSchema($table);
        $cols = array_column($schema, 'name');
        if (!in_array($column, $cols, true)) {
            throw new \InvalidArgumentException("Coluna inválida: $column em $table");
        }
    }

    public function listTables(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function tableSchema(string $table): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new \InvalidArgumentException("Nome inválido.");
        $stmt = $this->pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($cols as $c) {
            $out[] = [
                'name' => $c['Field'], 'type' => $this->normalizeType($c['Type']),
                'raw_type' => $c['Type'], 'nullable' => $c['Null'] === 'YES',
                'key' => $c['Key'], 'default' => $c['Default'], 'extra' => $c['Extra'],
            ];
        }
        return $out;
    }

    private function normalizeType(string $raw): string
    {
        $raw = strtolower($raw);
        return preg_match('/^([a-z]+)/', $raw, $m) ? $m[1] : $raw;
    }

    public function primaryKey(array $schema): ?string
    {
        foreach ($schema as $col) if ($col['key'] === 'PRI') return $col['name'];
        return null;
    }

    public function foreignKeys(string $table): array
    {
        $db = Config::$db['database'];
        $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND REFERENCED_TABLE_NAME IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':db' => $db, ':t' => $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['column' => $r['COLUMN_NAME'], 'ref_table' => $r['REFERENCED_TABLE_NAME'], 'ref_column' => $r['REFERENCED_COLUMN_NAME']];
        }
        return $out;
    }

    public function ensureApiTokensTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM api_tokens LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS api_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    token_hash VARCHAR(255) NOT NULL,
                    token_prefix VARCHAR(12) NOT NULL,
                    last_used_at DATETIME NULL,
                    expires_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_prefix (token_prefix),
                    INDEX idx_revoked (revoked_at)
                ) DEFAULT CHARSET=utf8mb4;
            ");
        } catch (\Throwable $e) { error_log('api_tokens migration: ' . $e->getMessage()); }
    }

    public function ensureNotificationsTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM notifications LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(150) NOT NULL,
                    message TEXT NOT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id)
                ) DEFAULT CHARSET=utf8mb4;
            ");
        } catch (\Throwable $e) {}
    }

    public function ensureAuditLogsTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM audit_logs LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(20) NOT NULL,
                table_name VARCHAR(100) NOT NULL,
                record_id BIGINT NULL,
                details TEXT NULL,
                details_json JSON NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_table (table_name),
                INDEX idx_created (created_at)
            ) DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function revokeOrphanTokens(): int
    {
        $this->ensureApiTokensTable();
        $stmt = $this->pdo->prepare("UPDATE api_tokens t LEFT JOIN usuarios u ON u.id = t.user_id SET t.revoked_at = NOW() WHERE u.id IS NULL AND t.revoked_at IS NULL");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function ensureRbacTables(): void
    {
        try { $this->pdo->query("SELECT 1 FROM roles LIMIT 1"); } catch (\Throwable $e) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS roles (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, description TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4;");
            } catch (\Throwable $e2) {}
        }
        try { $this->pdo->query("SELECT 1 FROM user_roles LIMIT 1"); } catch (\Throwable $e) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (user_id INT NOT NULL, role_id INT NOT NULL, PRIMARY KEY (user_id, role_id), INDEX idx_user (user_id), INDEX idx_role (role_id)) DEFAULT CHARSET=utf8mb4;");
            } catch (\Throwable $e2) {}
        }
        try { $this->pdo->query("SELECT 1 FROM role_permissions LIMIT 1"); } catch (\Throwable $e) {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, table_name VARCHAR(100) NULL, can_select TINYINT(1) NOT NULL DEFAULT 0, can_insert TINYINT(1) NOT NULL DEFAULT 0, can_update TINYINT(1) NOT NULL DEFAULT 0, can_delete TINYINT(1) NOT NULL DEFAULT 0, UNIQUE KEY uk_role_table (role_id, table_name), INDEX idx_role (role_id)) DEFAULT CHARSET=utf8mb4;");
            } catch (\Throwable $e2) {}
        }
        $this->seedRoles();
    }

    public function seedRoles(): void
    {
        $roles = [['admin', 'Administrador', 'Acesso total a todas as tabelas'], ['editor', 'Editor', 'Pode criar e editar, mas não excluir'], ['viewer', 'Visualizador', 'Apenas leitura']];
        $stmt = $this->pdo->prepare("INSERT INTO roles (slug, name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)");
        foreach ($roles as $r) $stmt->execute($r);

        $roleIds = [];
        foreach ($this->pdo->query("SELECT id, slug FROM roles") as $r) { $roleIds[$r['slug']] = (int)$r['id']; }

        $perms = [['admin', 1, 1, 1, 1], ['editor', 1, 1, 1, 0], ['viewer', 1, 0, 0, 0]];
        $stmtPerm = $this->pdo->prepare("INSERT INTO role_permissions (role_id, table_name, can_select, can_insert, can_update, can_delete) VALUES (?, NULL, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE can_select = VALUES(can_select), can_insert = VALUES(can_insert), can_update = VALUES(can_update), can_delete = VALUES(can_delete)");
        foreach ($perms as [$slug, $s, $i, $u, $d]) {
            if (!isset($roleIds[$slug])) continue;
            $stmtPerm->execute([$roleIds[$slug], $s, $i, $u, $d]);
        }

        $viewerId = $roleIds['viewer'] ?? null;
        if ($viewerId) {
            $this->pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) SELECT u.id, $viewerId FROM usuarios u LEFT JOIN user_roles ur ON ur.user_id = u.id WHERE ur.user_id IS NULL");
        }
    }

    public function getUserRoles(int $userId): array
    {
        $this->ensureRbacTables();
        $stmt = $this->pdo->prepare("SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getUserPermissions(int $userId, string $table): array
    {
        $this->ensureRbacTables();
        $roles = $this->getUserRoles($userId);
        if (empty($roles)) return ['SELECT' => false, 'INSERT' => false, 'UPDATE' => false, 'DELETE' => false];

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->pdo->prepare("SELECT table_name, can_select, can_insert, can_update, can_delete FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.slug IN ($placeholders) AND (rp.table_name = ? OR rp.table_name IS NULL)");
        $params = array_merge($roles, [$table]);
        $stmt->execute($params);

        $agg = ['SELECT' => false, 'INSERT' => false, 'UPDATE' => false, 'DELETE' => false];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ((int)$r['can_select']) $agg['SELECT'] = true;
            if ((int)$r['can_insert']) $agg['INSERT'] = true;
            if ((int)$r['can_update']) $agg['UPDATE'] = true;
            if ((int)$r['can_delete']) $agg['DELETE'] = true;
        }
        return $agg;
    }

    public function can(int $userId, string $table, string $action): bool
    {
        $perms = $this->getUserPermissions($userId, $table);
        return !empty($perms[strtoupper($action)]);
    }

    public function ensureWebhookLogsTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM webhook_logs LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS webhook_logs (id BIGINT AUTO_INCREMENT PRIMARY KEY, table_name VARCHAR(100) NOT NULL, record_id BIGINT NULL, action VARCHAR(20) NOT NULL, url VARCHAR(255) NOT NULL, status_code INT NULL, response_body TEXT NULL, attempt INT DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_table (table_name), INDEX idx_created (created_at)) DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function ensure2faTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM user_2fa LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_2fa (user_id INT PRIMARY KEY, secret VARCHAR(255) NOT NULL, enabled TINYINT(1) DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function logLogin(string $email, bool $success, string $reason = ''): void
    {
        $this->ensureAuditLogsTable();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAIL';
        $details = "Email: $email" . ($reason ? " | Motivo: $reason" : '');
        try {
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (NULL, ?, 'usuarios', NULL, ?, ?)");
            $stmt->execute([$action, $details, $ip]);
        } catch (\Throwable $e) {}
    }

    public function isDuplicate(string $table, array $data, string $pk, $ignoreId = null): ?string
    {
        $schema = $this->tableSchema($table);
        $candidateCols = [];
        foreach ($schema as $c) {
            if ($c['key'] === 'PRI') continue;
            if ($c['key'] === 'UNI' || $c['key'] === 'MUL') {
                $candidateCols[] = $c['name'];
                continue;
            }
            if (in_array(strtolower($c['name']), ['email', 'cpf', 'cnpj', 'username', 'login', 'slug', 'codigo', 'code'])) {
                $candidateCols[] = $c['name'];
            }
        }
        $candidateCols = array_unique($candidateCols);
        if (empty($candidateCols)) return null;

        foreach ($candidateCols as $col) {
            if (empty($data[$col])) continue;
            $sql = "SELECT `$pk` FROM `$table` WHERE `$col` = ? AND `deleted_at` IS NULL";
            $params = [$data[$col]];
            if ($ignoreId !== null) {
                $sql .= " AND `$pk` != ?";
                $params[] = $ignoreId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                return "Já existe um registro com o mesmo valor no campo '$col'.";
            }
        }
        return null;
    }

    public function getDashboardStats(string $period = 'all'): array
    {
        $tables = $this->listTables();
        $ignore = ['audit_logs', 'api_tokens', 'roles', 'user_roles', 'role_permissions', 'webhook_logs', 'notifications', 'user_2fa', 'revoked_tokens', 'rate_limit', 'webhook_queue', 'tenants'];

        $stats = [];
        $topTables = [];

        foreach ($tables as $t) {
            if (in_array($t, $ignore)) continue;
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$t` WHERE `deleted_at` IS NULL");
                $count = (int)$stmt->fetchColumn();

                $schema = $this->tableSchema($t);
                $temAtivo = false;
                foreach ($schema as $c) {
                    if (strtolower($c['name']) === 'ativo') { $temAtivo = true; break; }
                }

                $chartData = [];
                if ($temAtivo) {
                    $stmtA = $this->pdo->query("SELECT ativo, COUNT(*) as total FROM `$t` WHERE `deleted_at` IS NULL GROUP BY ativo");
                    $chartData = $stmtA->fetchAll(PDO::FETCH_ASSOC);
                }

                $stats[] = ['name' => $t, 'count' => $count, 'chart' => $chartData];
                $topTables[$t] = $count;
            } catch (\Throwable $e) {
                $stats[] = ['name' => $t, 'count' => 0, 'chart' => []];
            }
        }

        arsort($topTables);
        $top5 = array_slice($topTables, 0, 5, true);
        $mainTable = array_key_first($top5);
        $timelineData = ['labels' => [], 'data' => []];

        if ($mainTable) {
            $schema = $this->tableSchema($mainTable);
            $dateCol = null;
            foreach ($schema as $c) {
                $n = strtolower($c['name']);
                if (in_array($n, ['created_at', 'criado_em', 'data_criacao'])) { $dateCol = $c['name']; break; }
            }

            if ($dateCol) {
                $stmt = $this->pdo->query("
                    SELECT DATE(`$dateCol`) as dia, COUNT(*) as total
                    FROM `$mainTable`
                    WHERE `deleted_at` IS NULL
                      AND `$dateCol` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(`$dateCol`)
                    ORDER BY dia ASC
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $timelineData['labels'][] = date('d/m', strtotime($r['dia']));
                    $timelineData['data'][] = (int)$r['total'];
                }
            }
        }

        return [
            'stats' => $stats,
            'top5' => $top5,
            'timeline' => $timelineData,
            'mainTable' => $mainTable
        ];
    }

    public function ensureRevokedTokensTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM revoked_tokens LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS revoked_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                jti VARCHAR(64) NOT NULL UNIQUE,
                user_id INT NOT NULL,
                revoked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                reason VARCHAR(255) NULL,
                INDEX idx_jti (jti),
                INDEX idx_expires (expires_at)
            ) DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function isTokenRevoked(string $jti): bool
    {
        $this->ensureRevokedTokensTable();
        $stmt = $this->pdo->prepare("SELECT 1 FROM revoked_tokens WHERE jti = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$jti]);
        return (bool)$stmt->fetchColumn();
    }

    public function revokeToken(string $jti, int $userId, int $expiresIn, string $reason = ''): void
    {
        $this->ensureRevokedTokensTable();
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        $this->pdo->prepare("INSERT IGNORE INTO revoked_tokens (jti, user_id, expires_at, reason) VALUES (?, ?, ?, ?)")
            ->execute([$jti, $userId, $expiresAt, $reason]);
    }

    public function cleanupExpiredTokens(): int
    {
        $this->ensureRevokedTokensTable();
        $stmt = $this->pdo->prepare("DELETE FROM revoked_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function ensureRateLimitTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM rate_limit LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS rate_limit (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 0,
                window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
                blocked_until DATETIME NULL,
                INDEX idx_ip_action (ip, action),
                INDEX idx_window (window_start)
            ) DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function checkRateLimit(string $ip, string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        $this->ensureRateLimitTable();
        $this->pdo->exec("DELETE FROM rate_limit WHERE window_start < DATE_SUB(NOW(), INTERVAL {$windowSeconds} SECOND) AND blocked_until IS NULL");

        $stmt = $this->pdo->prepare("SELECT attempts, blocked_until FROM rate_limit WHERE ip = ? AND action = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$ip, $action]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
            return false;
        }

        if ($row && $row['attempts'] >= $maxAttempts) {
            $this->pdo->prepare("UPDATE rate_limit SET blocked_until = DATE_ADD(NOW(), INTERVAL {$windowSeconds} SECOND) WHERE ip = ? AND action = ?")
                ->execute([$ip, $action]);
            return false;
        }

        return true;
    }

    public function incrementRateLimit(string $ip, string $action): void
    {
        $this->ensureRateLimitTable();
        $stmt = $this->pdo->prepare("SELECT id FROM rate_limit WHERE ip = ? AND action = ? AND window_start > DATE_SUB(NOW(), INTERVAL 300 SECOND) ORDER BY id DESC LIMIT 1");
        $stmt->execute([$ip, $action]);
        $id = $stmt->fetchColumn();

        if ($id) {
            $this->pdo->prepare("UPDATE rate_limit SET attempts = attempts + 1 WHERE id = ?")->execute([$id]);
        } else {
            $this->pdo->prepare("INSERT INTO rate_limit (ip, action, attempts) VALUES (?, ?, 1)")->execute([$ip, $action]);
        }
    }

    public function resetRateLimit(string $ip, string $action): void
    {
        $this->ensureRateLimitTable();
        $this->pdo->prepare("DELETE FROM rate_limit WHERE ip = ? AND action = ?")->execute([$ip, $action]);
    }

    // ============================================================
    // v2.6.0 — Onda 2: Webhook Queue
    // ============================================================
    public function ensureWebhookQueueTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM webhook_queue LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS webhook_queue (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                url VARCHAR(500) NOT NULL,
                payload TEXT NOT NULL,
                signature VARCHAR(255) NOT NULL,
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                status ENUM('pending','processing','success','failed','dead_letter') DEFAULT 'pending',
                last_attempt DATETIME NULL,
                next_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_response TEXT NULL,
                last_status_code INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_next (next_attempt)
            ) DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}
    }

    public function enqueueWebhook(string $url, string $payload, string $signature): int
    {
        $this->ensureWebhookQueueTable();
        $stmt = $this->pdo->prepare("INSERT INTO webhook_queue (url, payload, signature, next_attempt) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$url, $payload, $signature]);
        return (int)$this->pdo->lastInsertId();
    }

    public function fetchPendingWebhooks(int $limit = 20): array
    {
        $this->ensureWebhookQueueTable();
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare("
            SELECT * FROM webhook_queue
            WHERE status = 'pending' AND next_attempt <= NOW()
            ORDER BY id ASC LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markWebhookProcessing(int $id): void
    {
        $this->pdo->prepare("UPDATE webhook_queue SET status = 'processing', last_attempt = NOW() WHERE id = ?")
            ->execute([$id]);
    }

    public function markWebhookSuccess(int $id, int $statusCode, string $response = ''): void
    {
        $this->pdo->prepare("UPDATE webhook_queue SET status = 'success', last_status_code = ?, last_response = ? WHERE id = ?")
            ->execute([$statusCode, mb_substr($response, 0, 2000), $id]);
    }

    public function markWebhookRetry(int $id, int $attempts, int $statusCode, string $response = ''): void
    {
        $nextAttempt = date('Y-m-d H:i:s', time() + pow(2, $attempts) * 5);
        $this->pdo->prepare("
            UPDATE webhook_queue
            SET status = 'pending', attempts = ?, last_attempt = NOW(),
                next_attempt = ?, last_status_code = ?, last_response = ?
            WHERE id = ?
        ")->execute([$attempts, $nextAttempt, $statusCode, mb_substr($response, 0, 2000), $id]);
    }

    public function markWebhookDeadLetter(int $id, int $statusCode, string $response = ''): void
    {
        $this->pdo->prepare("
            UPDATE webhook_queue
            SET status = 'dead_letter', last_attempt = NOW(), last_status_code = ?, last_response = ?
            WHERE id = ?
        ")->execute([$statusCode, mb_substr($response, 0, 2000), $id]);
    }

    public function recoverStuckWebhooks(int $timeoutMinutes = 5): int
    {
        $this->ensureWebhookQueueTable();
        $stmt = $this->pdo->prepare("
            UPDATE webhook_queue
            SET status = 'pending', next_attempt = NOW()
            WHERE status = 'processing' AND last_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$timeoutMinutes]);
        return $stmt->rowCount();
    }

    // ============================================================
    // v2.6.0 — Onda 2: Multi-Tenant
    // ============================================================
    public function ensureTenantTable(): void
    {
        try { $this->pdo->query("SELECT 1 FROM tenants LIMIT 1"); return; } catch (\Throwable $e) {}
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) NOT NULL UNIQUE,
                domain VARCHAR(100) NULL,
                active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1, 'Default', 'default')");
        } catch (\Throwable $e) {}
    }

    public function getTenantId(): int
    {
        if (!isset($_SESSION['tenant_id'])) {
            $_SESSION['tenant_id'] = 1;
        }
        return (int)$_SESSION['tenant_id'];
    }

    public function applyTenantFilter(string $table): string
    {
        $schema = $this->tableSchema($table);
        $cols = array_column($schema, 'name');
        if (in_array('tenant_id', $cols)) {
            return " AND tenant_id = " . $this->getTenantId();
        }
        return "";
    }

    // ============================================================
    // v2.6.0 — Onda 2: Auditoria com Diff
    // ============================================================
    public function logChange(int $userId, string $action, string $table, int $recordId, array $before, array $after, string $ip = ''): void
    {
        $this->ensureAuditLogsTable();

        $diff = [];
        foreach ($after as $key => $newVal) {
            $oldVal = $before[$key] ?? null;
            if ($oldVal != $newVal) {
                $diff[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, details_json, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $action, $table, $recordId,
                json_encode($diff, JSON_UNESCAPED_UNICODE),
                $ip ?: ($_SERVER['REMOTE_ADDR'] ?? ''),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        } catch (\Throwable $e) {
            // Fallback se details_json não existir
            try {
                $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $action, $table, $recordId, json_encode($diff), $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '')]);
            } catch (\Throwable $e2) {}
        }
    }

    // ============================================================
    // v2.6.0 — Onda 2: Processamento de Webhooks
    // ============================================================
    public function processWebhookQueue(int $limit = 20): array
    {
        $this->ensureWebhookQueueTable();
        $this->recoverStuckWebhooks(5);

        $jobs = $this->fetchPendingWebhooks($limit);
        $result = ['success' => 0, 'retry' => 0, 'dead_letter' => 0, 'errors' => 0];

        foreach ($jobs as $job) {
            $this->markWebhookProcessing((int)$job['id']);

            try {
                $ch = curl_init($job['url']);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $job['payload'],
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'X-Phpgen-Signature: ' . $job['signature'],
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                ]);
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $attempts = (int)$job['attempts'] + 1;

                if ($statusCode >= 200 && $statusCode < 300) {
                    $this->markWebhookSuccess((int)$job['id'], $statusCode, $response);
                    $result['success']++;
                } elseif ($attempts >= (int)$job['max_attempts']) {
                    $this->markWebhookDeadLetter((int)$job['id'], $statusCode, $response);
                    $result['dead_letter']++;
                } else {
                    $this->markWebhookRetry((int)$job['id'], $attempts, $statusCode, $response);
                    $result['retry']++;
                }
            } catch (\Throwable $e) {
                $this->markWebhookRetry((int)$job['id'], (int)$job['attempts'] + 1, 0, $e->getMessage());
                $result['errors']++;
            }
        }

        return $result;
    }
	
	public function testProcessWebhook(): void
	{
		// Testa o fluxo sem HTTP real (mock)
		$id = $this->db->enqueueWebhook(
			'http://localhost:9999/nunca-responde',
			'{"test":true}',
			'abc123'
		);
		
		$this->assertGreaterThan(0, $id);
		
		// Como o destino é inválido, o webhook vai para retry (não success)
		// Mas o PROCESSAMENTO em si funciona
		$processor = new WebhookProcessor($this->db);
		$result = $processor->process(10);
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('retry', $result);
		$this->assertGreaterThanOrEqual(1, $result['retry']); // ← retry, não success
	}

    public function log(int $userId, string $action, string $table, int $recordId, string $details = ''): void
    {
        $this->ensureAuditLogsTable();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $details, $ip]);
    }
}