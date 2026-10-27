<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class ApiController
{
    /**
     * Retorna o identificador devidamente quotado para o driver ativo.
     * MySQL: `col`   |   PostgreSQL: "col"
     */
    private function q(Introspector $db, string $ident): string
    {
        $driver = $db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            return '"' . str_replace('"', '""', $ident) . '"';
        }
        return '`' . str_replace('`', '``', $ident) . '`';
    }

    public function openapi(): void
    {
        $f = __DIR__ . '/../../../public/openapi.json';
        if (!file_exists($f)) {
            http_response_code(404);
            die("openapi.json não encontrado");
        }
        header('Content-Type: application/json; charset=utf-8');
        readfile($f);
        exit;
    }

    public function docs(): void
    {
        global $twig;
        echo $twig->render('openapi.twig', ['title' => 'API Docs']);
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $apiUser = null;

        // ------------------------------------------------------------
        // 1. Autenticação (JWT Bearer ou sessão)
        // ------------------------------------------------------------
        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = trim($m[1]);
            try {
                $decoded = \Firebase\JWT\JWT::decode(
                    $token,
                    new \Firebase\JWT\Key(Config::$jwtSecret, 'HS256')
                );
                $dbCheck = new Introspector();
                if (isset($decoded->jti) && $dbCheck->isTokenRevoked($decoded->jti)) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Token revogado']);
                    exit;
                }
                $stmtU = $dbCheck->pdo()->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
                $stmtU->execute([$decoded->sub]);
                $apiUser = $stmtU->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // Fallback: tenta validar como API token (prefix + hash)
                $prefix = substr($token, 0, 12);
                $db0 = new Introspector();
                $db0->ensureApiTokensTable();
                $stmt = $db0->pdo()->prepare("SELECT * FROM api_tokens WHERE token_prefix = ? AND revoked_at IS NULL");
                $stmt->execute([$prefix]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && password_verify($token, $row['token_hash'])) {
                    $stmtU = $db0->pdo()->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
                    $stmtU->execute([$row['user_id']]);
                    $apiUser = $stmtU->fetch(\PDO::FETCH_ASSOC);
                }
            }
        } elseif (isset($_SESSION['user'])) {
            $apiUser = $_SESSION['user'];
        }

        if (!$apiUser) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Token inválido ou ausente']);
            exit;
        }

        // ------------------------------------------------------------
        // 2. Validação de tabela e RBAC
        // ------------------------------------------------------------
        $userId = (int)$apiUser['id'];
        $table  = $_GET['table'] ?? '';
        $db     = new Introspector();

        try {
            $db->assertValidTable($table);
        } catch (\Throwable $e) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tabela inválida']);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $id     = $_GET['id'] ?? null;
        $requiredPermission = match ($method) {
            'GET'          => 'SELECT',
            'POST'         => 'INSERT',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE'       => 'DELETE',
            default        => null,
        };

        if ($requiredPermission && !$db->can($userId, $table, $requiredPermission)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permissão negada']);
            exit;
        }

        // ------------------------------------------------------------
        // 3. Metadados da tabela
        // ------------------------------------------------------------
        $schema = $db->tableSchema($table);
        $pk     = $db->primaryKey($schema) ?? 'id';

        $body = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])
            ? (json_decode(file_get_contents('php://input'), true) ?: [])
            : [];

        // ------------------------------------------------------------
        // 4. Helpers de sanitização e webhook
        // ------------------------------------------------------------
        $hashIfPassword = function ($col, $val) {
            $n = strtolower($col);
            return (str_contains($n, 'senha') || str_contains($n, 'password')) && !empty($val)
                ? password_hash((string)$val, PASSWORD_DEFAULT)
                : $val;
        };

        $sanitize = function ($input) use ($pk, $hashIfPassword) {
            $out = [];
            foreach ($input as $k => $v) {
                if ($k === $pk || $k === 'deleted_at' || $k === '_csrf') continue;
                $out[$k] = $hashIfPassword($k, $v);
            }
            return $out;
        };

        $fireWebhook = function ($actionName, $recordId) use ($table, $db) {
            $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
            if (!file_exists($cfgFile)) return;
            $url = json_decode(file_get_contents($cfgFile), true)['webhook'] ?? '';
            if (empty($url)) return;

            $payload   = json_encode(['action' => $actionName, 'table' => $table, 'id' => $recordId, 'time' => date('c')]);
            $signature = hash_hmac('sha256', $payload, Config::$webhookSecret);
            $db->ensureWebhookLogsTable();

            $attempt = 0; $success = false; $statusCode = 0; $response = '';
            while ($attempt < 3 && !$success) {
                $attempt++;
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Phpgen-Signature: ' . $signature],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                ]);
                $response   = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($statusCode >= 200 && $statusCode < 300) $success = true;
                else if ($attempt < 3) sleep(pow(2, $attempt - 1));
            }
            try {
                $db->pdo()->prepare("INSERT INTO webhook_logs (table_name, record_id, action, url, status_code, response_body, attempt) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$table, (int)$recordId, $actionName, $url, $statusCode, mb_substr((string)$response, 0, 1000), $attempt]);
            } catch (\Throwable $e) {}
        };

        // ------------------------------------------------------------
        // 5. CRUD
        // ------------------------------------------------------------
        try {
            switch ($method) {
                case 'GET':
                    if ($id !== null) {
                        $sql = "SELECT * FROM " . $this->q($db, $table)
                             . " WHERE " . $this->q($db, $pk) . " = ?"
                             . " AND " . $this->q($db, 'deleted_at') . " IS NULL";
                        $stmt = $db->pdo()->prepare($sql);
                        $stmt->execute([$id]);
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if (!$row) {
                            http_response_code(404);
                            echo json_encode(['status' => 'error', 'message' => 'Não encontrado']);
                            exit;
                        }
                        echo json_encode(['status' => 'success', 'data' => $row], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    } else {
                        $limit  = min(100, max(1, (int)($_GET['limit'] ?? 25)));
                        $page   = max(1, (int)($_GET['page'] ?? 1));
                        $offset = ($page - 1) * $limit;

                        $countSql = "SELECT COUNT(*) FROM " . $this->q($db, $table)
                                  . " WHERE " . $this->q($db, 'deleted_at') . " IS NULL";
                        $total = (int)$db->pdo()->query($countSql)->fetchColumn();

                        $selectSql = "SELECT * FROM " . $this->q($db, $table)
                                   . " WHERE " . $this->q($db, 'deleted_at') . " IS NULL"
                                   . " LIMIT $limit OFFSET $offset";
                        $stmt = $db->pdo()->prepare($selectSql);
                        $stmt->execute();
                        echo json_encode(
                            ['status' => 'success', 'total' => $total, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                        );
                    }
                    break;

                case 'POST':
                    $data = $sanitize($body);
                    if (empty($data)) {
                        http_response_code(400);
                        echo json_encode(['status' => 'error', 'message' => 'Payload vazio']);
                        exit;
                    }
                    $cols = array_keys($data);
                    $ph   = implode(', ', array_fill(0, count($cols), '?'));
                    $quotedCols = implode(', ', array_map(fn($c) => $this->q($db, $c), $cols));
                    $sql = "INSERT INTO " . $this->q($db, $table) . " ($quotedCols) VALUES ($ph)";
                    $db->pdo()->prepare($sql)->execute(array_values($data));
                    $newId = $db->pdo()->lastInsertId();
                    $db->log($userId, 'CREATE', $table, (int)$newId);
                    $fireWebhook('CREATE', (int)$newId);
                    http_response_code(201);
                    echo json_encode(['status' => 'success', 'id' => $newId]);
                    break;

                case 'PUT':
                case 'PATCH':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['status' => 'error', 'message' => 'Informe o ID']);
                        exit;
                    }
                    $data = $sanitize($body);
                    $sets = []; $params = [];
                    foreach ($data as $k => $v) {
                        $sets[]   = $this->q($db, $k) . " = ?";
                        $params[] = $v;
                    }
                    $params[] = $id;
                    $sql = "UPDATE " . $this->q($db, $table)
                         . " SET " . implode(', ', $sets)
                         . " WHERE " . $this->q($db, $pk) . " = ?";
                    $db->pdo()->prepare($sql)->execute($params);
                    $db->log($userId, 'UPDATE', $table, (int)$id);
                    $fireWebhook('UPDATE', (int)$id);
                    echo json_encode(['status' => 'success', 'id' => $id]);
                    break;

                case 'DELETE':
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['status' => 'error', 'message' => 'Informe o ID']);
                        exit;
                    }
                    $sql = "UPDATE " . $this->q($db, $table)
                         . " SET " . $this->q($db, 'deleted_at') . " = NOW()"
                         . " WHERE " . $this->q($db, $pk) . " = ?";
                    $db->pdo()->prepare($sql)->execute([$id]);
                    $db->log($userId, 'DELETE', $table, (int)$id);
                    $fireWebhook('DELETE', (int)$id);
                    echo json_encode(['status' => 'success', 'id' => $id]);
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['status' => 'error', 'message' => 'Método não suportado']);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Erro interno',
                'detail'  => $e->getMessage(),   // ajuda no diagnóstico; remover em produção
            ]);
            error_log('ApiController: ' . $e->getMessage());
        }
        exit;
    }
}