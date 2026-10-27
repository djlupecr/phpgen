<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Database\Introspector;
use PhpGen\Generator\Generator;
use PhpGen\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, ['cache' => false]);

$action = $_GET['action'] ?? 'dashboard';

// ============================================================
// HELPERS GLOBAIS
// ============================================================
function jsonSafe($data): string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrfCheck(): void {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403); die("CSRF token inválido.");
    }
}

// [FIX] Redireciona para o instalador se o sistema não estiver configurado
$configFile = __DIR__ . '/../src/Config.php';
if (file_exists($configFile) && filesize($configFile) < 500) {
    header('Location: install.php');
    exit;
}

// ============================================================
// MULTILANGUAGE (i18n)
// ============================================================
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'pt_BR';
if (!in_array($lang, ['pt_BR', 'en', 'es'])) { $lang = 'pt_BR'; }
$_SESSION['lang'] = $lang;
setcookie('lang', $lang, time() + (86400 * 30), '/');

$translationFile = __DIR__ . '/../locales/' . $lang . '.php';
$translations = file_exists($translationFile) ? require $translationFile : [];

$twig->addFilter(new \Twig\TwigFilter('trans', function ($key) use ($translations) {
    $parts = explode('.', $key);
    $val = $translations;
    foreach ($parts as $p) {
        if (isset($val[$p])) { $val = $val[$p]; } else { return $key; }
    }
    return is_string($val) ? $val : $key;
}));

$twig->addGlobal('current_lang', $lang);
$twig->addGlobal('available_langs', ['pt_BR' => '🇧🇷 PT', 'en' => '🇺🇸 EN', 'es' => '🇪🇸 ES']);
$twig->addGlobal('csrf_token', csrfToken());

// ============================================================
// Fallback para servidores que filtram Authorization
// ============================================================
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (!empty($_GET['api_token'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['api_token'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
}

// ============================================================
// HEALTH-CHECK — PÚBLICO
// ============================================================
if ($action === 'health') {
    header('Content-Type: application/json; charset=utf-8');
    $checks = [];

    try {
        $db = new Introspector();
        $db->pdo()->query("SELECT 1");
        $checks['db'] = ['ok' => true, 'detail' => 'conexão PDO'];
    } catch (\Throwable $e) {
        $checks['db'] = ['ok' => false, 'detail' => $e->getMessage()];
    }

    $checks['libs'] = [
        'ok' => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)
             && class_exists(\Dompdf\Dompdf::class)
             && class_exists(\Firebase\JWT\JWT::class)
             && class_exists(\PragmaRX\Google2FA\Google2FA::class),
        'detail' => [
            'phpspreadsheet' => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class),
            'dompdf'         => class_exists(\Dompdf\Dompdf::class),
            'firebase_jwt'   => class_exists(\Firebase\JWT\JWT::class),
            'google2fa'      => class_exists(\PragmaRX\Google2FA\Google2FA::class),
        ],
    ];

    $checks['config'] = [
        'ok' => Config::$generatedNamespace === 'PhpGen\\Generated'
             && strlen(Config::$jwtSecret) >= 32
             && !empty(Config::$webhookSecret),
        'detail' => [
            'generatedNamespace' => Config::$generatedNamespace,
            'jwtSecret_len'      => strlen(Config::$jwtSecret),
            'webhookSecret_set'  => !empty(Config::$webhookSecret),
        ],
    ];

    $tabelasGeradas = [];
    if (is_dir(Config::$outputDir)) {
        foreach (scandir(Config::$outputDir) as $d) {
            if ($d === '.' || $d === '..' || !is_dir(Config::$outputDir . '/' . $d)) continue;
            $tabelasGeradas[] = $d;
        }
    }
    $checks['generated'] = ['ok' => !empty($tabelasGeradas), 'detail' => $tabelasGeradas];

    $uploadDir = __DIR__ . '/uploads';
    $checks['uploads_writable'] = ['ok' => is_dir($uploadDir) && is_writable($uploadDir), 'detail' => $uploadDir];

    $allOk = true;
    foreach ($checks as $c) { if (!$c['ok']) { $allOk = false; break; } }

    http_response_code($allOk ? 200 : 503);
    echo json_encode([
        'status' => $allOk ? 'ok' : 'degraded',
        'timestamp' => date('c'),
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// LOGOUT API (JWT Revogável)
// ============================================================
if ($action === 'logout_api' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        try {
            $decoded = \Firebase\JWT\JWT::decode(trim($m[1]), new \Firebase\JWT\Key(Config::$jwtSecret, 'HS256'));
            if (isset($decoded->jti, $decoded->sub)) {
                $db = new Introspector();
                $db->revokeToken($decoded->jti, (int)$decoded->sub, (int)($decoded->exp - time()), 'logout');
                echo json_encode(['status' => 'success', 'message' => 'Token revogado']);
                exit;
            }
        } catch (\Throwable $e) {}
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token inválido ou ausente']);
    exit;
}

// ============================================================
// LOGIN
// ============================================================
if ($action === 'login') {
    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
              || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
              || ($_GET['format'] ?? '') === 'json';

    $erro = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $attempts = (int)($_SESSION['login_attempts'] ?? 0);

        // [FIX] Rate Limit por IP (Aplica para Web e API)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $dbRate = new Introspector();
        if (!$dbRate->checkRateLimit($ip, 'login', 5, 300)) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(429); // Too Many Requests
                echo json_encode(['status' => 'error', 'message' => 'Muitas tentativas. Aguarde 5 minutos.']);
                exit;
            }
            $erro = "Muitas tentativas. Aguarde 5 minutos.";
        }

        if (!$erro && !$wantsJson && !empty(Config::$turnstileSecretKey)) {
            $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
            $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['secret' => Config::$turnstileSecretKey, 'response' => $turnstileResponse, 'remoteip' => $ip]),
                CURLOPT_RETURNTRANSFER => true
            ]);
            $result = json_decode(curl_exec($ch), true);
            curl_close($ch);
            if (empty($result['success'])) {
                $erro = "Falha na verificação de segurança (Captcha). Tente novamente.";
            }
        }

        if (!$erro && $attempts >= 5) {
            sleep(2);
            $erro = "Muitas tentativas. Aguarde alguns minutos.";
        }

        if (!$erro) {
            $payload = $_POST;
            if (empty($payload)) {
                $raw = file_get_contents('php://input');
                if ($raw) $payload = json_decode($raw, true) ?: [];
            }
            $email = trim($payload['email'] ?? '');
            $senha = $payload['senha'] ?? '';

            $db   = new Introspector();
            $stmt = $db->pdo()->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha, $user['senha'])) {
                $db->ensure2faTable();
                $stmt2fa = $db->pdo()->prepare("SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1");
                $stmt2fa->execute([$user['id']]);

                if ($stmt2fa->fetchColumn()) {
                    if ($wantsJson) {
                        header('Content-Type: application/json; charset=utf-8');
                        http_response_code(401);
                        echo json_encode(['status' => 'error', 'message' => '2FA necessário', 'requires_2fa' => true, 'user_id' => $user['id']]);
                        exit;
                    }
                    $_SESSION['pre_2fa_user'] = $user;
                    header('Location: ?action=2fa_verify'); exit;
                }

                $_SESSION['user'] = $user;
                $_SESSION['login_attempts'] = 0;
                $db->logLogin($email, true);
                $dbRate->resetRateLimit($ip, 'login');
                try { $db->revokeOrphanTokens(); } catch (\Throwable $e) {}

                $jwtToken = null;
                if ($wantsJson || isset($_GET['jwt'])) {
                    $jti = bin2hex(random_bytes(16));
                    $payload = [
                        'iss' => 'phpgen',
                        'iat' => time(),
                        'exp' => time() + 3600,
                        'jti' => $jti,
                        'sub' => $user['id'],
                        'email' => $user['email'],
                        'roles' => $db->getUserRoles($user['id'])
                    ];
                    $jwtToken = \Firebase\JWT\JWT::encode($payload, Config::$jwtSecret, 'HS256');
                }

                if ($wantsJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'status' => 'success',
                        'user' => ['id' => $user['id'], 'nome' => $user['nome'] ?? '', 'email' => $user['email'] ?? ''],
                        'csrf' => csrfToken(),
                        'jwt' => $jwtToken
                    ]);
                    exit;
                }
                header('Location: ?action=dashboard'); exit;
            } else {
                $db->logLogin($email, false, 'Credenciais inválidas');
                $dbRate->incrementRateLimit($ip, 'login');
                $_SESSION['login_attempts'] = $attempts + 1;
                $erro = "E-mail ou senha inválidos.";
            }
        }
    }

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($erro ? 401 : 400);
        echo json_encode(['status' => 'error', 'message' => $erro ?? 'Envie POST com email e senha']);
        exit;
    }
    echo $twig->render('login.twig', [
        'erro' => $erro,
        'install_success' => $_SESSION['install_success'] ?? null,
        'turnstile_site_key' => Config::$turnstileSiteKey
    ]);
    unset($_SESSION['install_success']);
    exit;
}

if ($action === 'logout') { session_destroy(); header('Location: ?action=login'); exit; }

// ============================================================
// SESSÃO OBRIGATÓRIA + Recarga
// ============================================================
$rotasPublicas = ['api', 'login', 'logout', '2fa_verify', 'health', 'openapi', 'api_docs', 'logout_api'];
if (!in_array($action, $rotasPublicas, true)) {
    if (!isset($_SESSION['user'])) { header('Location: ?action=login'); exit; }
    $dbRefresh = new Introspector();
    $stmtRefresh = $dbRefresh->pdo()->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmtRefresh->execute([(int)$_SESSION['user']['id']]);
    $freshUser = $stmtRefresh->fetch(PDO::FETCH_ASSOC);
    if (!$freshUser) { session_destroy(); header('Location: ?action=login'); exit; }
    $_SESSION['user'] = $freshUser;
    $twig->addGlobal('session_user', $freshUser);
    $userId = (int)$freshUser['id'];
} elseif ($action === '2fa_verify' && isset($_SESSION['pre_2fa_user'])) {
    $userId = (int)$_SESSION['pre_2fa_user']['id'];
}

// ============================================================
// 2FA_SETUP
// ============================================================
if ($action === '2fa_setup') {
    $db = new Introspector(); $db->ensure2faTable();
    $google2fa = new \PragmaRX\Google2FA\Google2FA();
    $stmt = $db->pdo()->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $erro = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfCheck();
        $code = $_POST['code'] ?? '';
        $secret = $_POST['secret'] ?? '';
        if ($google2fa->verifyKey($secret, $code)) {
            $db->pdo()->prepare("INSERT INTO user_2fa (user_id, secret, enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE secret = ?, enabled = 1")->execute([$userId, $secret, $secret]);
            $_SESSION['flash_success'] = '2FA ativado!';
            header('Location: ?action=dashboard'); exit;
        } else { $erro = 'Código inválido.'; }
    }

    $secret = null; $qrCodeUrl = null;
    if (!$existing || !$existing['enabled']) {
        $secret = $google2fa->generateSecretKey();
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($google2fa->getQRCodeUrl(Config::$appName ?? 'phpgen', $_SESSION['user']['email'], $secret)) . '&size=200x200';
    }
    echo $twig->render('2fa_setup.twig', ['secret' => $secret, 'qrCodeUrl' => $qrCodeUrl, 'enabled' => $existing['enabled'] ?? false, 'erro' => $erro, 'csrf' => csrfToken()]);
    exit;
}

// ============================================================
// 2FA_VERIFY
// ============================================================
if ($action === '2fa_verify') {
    if (!isset($_SESSION['pre_2fa_user'])) { header('Location: ?action=login'); exit; }
    $google2fa = new \PragmaRX\Google2FA\Google2FA();
    $db = new Introspector(); $user = $_SESSION['pre_2fa_user'];
    $erro = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfCheck(); $code = $_POST['code'] ?? '';
        $stmt = $db->pdo()->prepare("SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1"); $stmt->execute([$user['id']]); $secret = $stmt->fetchColumn();
        if ($google2fa->verifyKey($secret, $code)) {
            $_SESSION['user'] = $user; unset($_SESSION['pre_2fa_user']); $_SESSION['login_attempts'] = 0;
            $db->logLogin($user['email'], true); header('Location: ?action=dashboard'); exit;
        } else { $erro = 'Código inválido.'; }
    }
    echo $twig->render('2fa_verify.twig', ['erro' => $erro, 'csrf' => csrfToken()]); exit;
}

// ============================================================
// RBAC global
// ============================================================
if (isset($userId)) {
    try {
        $dbRbac = new Introspector(); $dbRbac->ensureRbacTables();
        if ($action !== 'api') { $twig->addGlobal('is_admin', in_array('admin', $dbRbac->getUserRoles($userId), true)); }
    } catch (\Throwable $e) {}
}

// ============================================================
// ROTAS DE PAPÉIS
// ============================================================
if (in_array($action, ['roles', 'role_assign', 'role_remove'])) {
    $db = new Introspector(); $db->ensureRbacTables();
    if (!in_array('admin', $db->getUserRoles($userId), true)) { http_response_code(403); die("Acesso negado."); }

    if ($action === 'role_assign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfCheck(); $targetUser = (int)($_POST['user_id'] ?? 0); $roleSlug = trim($_POST['role'] ?? '');
        if ($targetUser && $roleSlug) {
            $stmtR = $db->pdo()->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1"); $stmtR->execute([$roleSlug]);
            if ($roleId = (int)$stmtR->fetchColumn()) { $db->pdo()->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$targetUser, $roleId]); }
        }
        header('Location: ?action=roles'); exit;
    }
    if ($action === 'role_remove' && isset($_GET['user_id'], $_GET['role'])) {
        $stmtR = $db->pdo()->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1"); $stmtR->execute([$_GET['role']]);
        if ($roleId = (int)$stmtR->fetchColumn()) { $db->pdo()->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?")->execute([(int)$_GET['user_id'], $roleId]); }
        header('Location: ?action=roles'); exit;
    }
    $usuarios = $db->pdo()->query("SELECT id, nome, email FROM usuarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    $roles = $db->pdo()->query("SELECT id, slug, name FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $papeisPorUsuario = [];
    foreach ($db->pdo()->query("SELECT ur.user_id, r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id") as $row) { $papeisPorUsuario[(int)$row['user_id']][] = $row['slug']; }
    echo $twig->render('roles.twig', ['usuarios' => $usuarios, 'roles' => $roles, 'papeisPorUsuario' => $papeisPorUsuario, 'csrf' => csrfToken()]); exit;
}

// ============================================================
// API TOKENS
// ============================================================
if (in_array($action, ['tokens', 'token_create', 'token_revoke'])) {
    $db = new Introspector(); $db->ensureApiTokensTable();
    if ($action === 'token_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfCheck(); $name = trim($_POST['name'] ?? '');
        if ($name) {
            $plain = bin2hex(random_bytes(32)); $prefix = substr($plain, 0, 12); $hash = password_hash($plain, PASSWORD_DEFAULT);
            $db->pdo()->prepare("INSERT INTO api_tokens (user_id, name, token_hash, token_prefix) VALUES (?, ?, ?, ?)")->execute([$userId, $name, $hash, $prefix]);
            $_SESSION['flash_token'] = $plain; $_SESSION['flash_success'] = "Token criado!";
        }
        header('Location: ?action=tokens'); exit;
    }
    if ($action === 'token_revoke' && isset($_GET['id'])) { $db->pdo()->prepare("UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND user_id = ?")->execute([(int)$_GET['id'], $userId]); header('Location: ?action=tokens'); exit; }
    $tokens = $db->pdo()->query("SELECT id, name, token_prefix, last_used_at, revoked_at, created_at FROM api_tokens WHERE user_id = $userId ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo $twig->render('tokens.twig', ['tokens' => $tokens, 'flash_token' => $_SESSION['flash_token'] ?? null, 'csrf' => csrfToken()]);
    unset($_SESSION['flash_token'], $_SESSION['flash_success']); exit;
}

// ============================================================
// WEBHOOK LOGS
// ============================================================
if ($action === 'webhook_logs') {
    $db = new Introspector(); $db->ensureWebhookLogsTable();
    $page = max(1, (int)($_GET['page'] ?? 1)); $offset = ($page - 1) * 25;
    $total = (int)$db->pdo()->query("SELECT COUNT(*) FROM webhook_logs")->fetchColumn();
    $logs = $db->pdo()->query("SELECT * FROM webhook_logs ORDER BY id DESC LIMIT 25 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
    echo $twig->render('webhook_logs.twig', ['logs' => $logs, 'total' => $total, 'page' => $page, 'totalPages' => ceil($total / 25)]); exit;
}

// ============================================================
// BACKUP
// ============================================================
if (in_array($action, ['backup', 'backup_download', 'backup_restore'])) {
    $db = new Introspector();
    if (!in_array('admin', $db->getUserRoles($userId), true)) { http_response_code(403); die("Acesso negado."); }
    if ($action === 'backup_download') {
        $manager = new \PhpGen\Validation\BackupManager($db);
        $zipPath = $manager->createBackup();
        header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.zip"'); readfile($zipPath); unlink($zipPath); exit;
    }
    if ($action === 'backup_restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfCheck();
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $manager = new \PhpGen\Validation\BackupManager($db);
            $result = $manager->restoreBackup($_FILES['backup_file']['tmp_name']);
            $_SESSION['flash_success'] = "Restaurado! {$result['files']} arquivos.";
        }
        header('Location: ?action=backup'); exit;
    }
    echo $twig->render('backup.twig', ['flash_success' => $_SESSION['flash_success'] ?? null, 'csrf' => csrfToken()]); unset($_SESSION['flash_success']); exit;
}

// ============================================================
// API REST
// ============================================================
if ($action === 'api') {
    header('Content-Type: application/json; charset=utf-8');
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $apiUser = null;

    if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $token = trim($m[1]);
        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Config::$jwtSecret, 'HS256'));
            $dbCheck = new Introspector();
            if (isset($decoded->jti) && $dbCheck->isTokenRevoked($decoded->jti)) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Token revogado']);
                exit;
            }
            $stmtU = $dbCheck->pdo()->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1"); $stmtU->execute([$decoded->sub]); $apiUser = $stmtU->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $prefix = substr($token, 0, 12);
            $db0 = new Introspector(); $db0->ensureApiTokensTable();
            $stmt = $db0->pdo()->prepare("SELECT * FROM api_tokens WHERE token_prefix = ? AND revoked_at IS NULL"); $stmt->execute([$prefix]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($token, $row['token_hash'])) {
                $stmtU = $db0->pdo()->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1"); $stmtU->execute([$row['user_id']]); $apiUser = $stmtU->fetch(PDO::FETCH_ASSOC);
            }
        }
    } elseif (isset($_SESSION['user'])) { $apiUser = $_SESSION['user']; }

    if (!$apiUser) { http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Token inválido ou ausente']); exit; }
    $userId = (int)$apiUser['id'];

    $table = $_GET['table'] ?? '';
    $db = new Introspector();
    try { $db->assertValidTable($table); } catch (\Throwable $e) { http_response_code(404); echo json_encode(['status' => 'error', 'message' => 'Tabela inválida']); exit; }

    $method = $_SERVER['REQUEST_METHOD'];
    $id = $_GET['id'] ?? null;
    $requiredPermission = match ($method) { 'GET' => 'SELECT', 'POST' => 'INSERT', 'PUT', 'PATCH' => 'UPDATE', 'DELETE' => 'DELETE', default => null };
    if ($requiredPermission && !$db->can($userId, $table, $requiredPermission)) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Permissão negada']); exit; }

    $schema = $db->tableSchema($table); $pk = $db->primaryKey($schema) ?? 'id';
    $body = (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];

    $hashIfPassword = function ($col, $val) { $n = strtolower($col); return (str_contains($n, 'senha') || str_contains($n, 'password')) && !empty($val) ? password_hash((string)$val, PASSWORD_DEFAULT) : $val; };
    $sanitize = function ($input) use ($schema, $pk, $hashIfPassword) { $out = []; foreach ($input as $k => $v) { if ($k === $pk || $k === 'deleted_at' || $k === '_csrf') continue; $out[$k] = $hashIfPassword($k, $v); } return $out; };

    $fireWebhook = function ($actionName, $recordId) use ($table, $db) {
        $cfgFile = __DIR__ . "/../generated/$table/config.json";
        if (!file_exists($cfgFile)) return;
        $url = json_decode(file_get_contents($cfgFile), true)['webhook'] ?? '';
        if (empty($url)) return;
        $payload = json_encode(['action' => $actionName, 'table' => $table, 'id' => $recordId, 'time' => date('c')]);
        $signature = hash_hmac('sha256', $payload, Config::$webhookSecret);
        $db->ensureWebhookLogsTable();
        $attempt = 0; $success = false; $statusCode = 0; $response = '';
        while ($attempt < 3 && !$success) {
            $attempt++;
            $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Phpgen-Signature: ' . $signature], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $response = curl_exec($ch); $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($statusCode >= 200 && $statusCode < 300) $success = true; else if ($attempt < 3) sleep(pow(2, $attempt - 1));
        }
        try { $db->pdo()->prepare("INSERT INTO webhook_logs (table_name, record_id, action, url, status_code, response_body, attempt) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$table, (int)$recordId, $actionName, $url, $statusCode, mb_substr((string)$response, 0, 1000), $attempt]); } catch (\Throwable $e) {}
    };

    try {
        switch ($method) {
            case 'GET':
                if ($id !== null) {
                    $stmt = $db->pdo()->prepare("SELECT * FROM `$table` WHERE `$pk` = ? AND `deleted_at` IS NULL"); $stmt->execute([$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { http_response_code(404); echo json_encode(['status' => 'error', 'message' => 'Não encontrado']); exit; }
                    echo json_encode(['status' => 'success', 'data' => $row], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } else {
                    $limit = min(100, max(1, (int)($_GET['limit'] ?? 25))); $page = max(1, (int)($_GET['page'] ?? 1)); $offset = ($page - 1) * $limit;
                    $total = (int)$db->pdo()->query("SELECT COUNT(*) FROM `$table` WHERE `deleted_at` IS NULL")->fetchColumn();
                    $stmt = $db->pdo()->prepare("SELECT * FROM `$table` WHERE `deleted_at` IS NULL LIMIT $limit OFFSET $offset"); $stmt->execute();
                    echo json_encode(['status' => 'success', 'total' => $total, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
                break;
            case 'POST':
                $data = $sanitize($body); if (empty($data)) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Payload vazio']); exit; }
                $cols = array_keys($data); $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES ($placeholders)"; $db->pdo()->prepare($sql)->execute(array_values($data));
                $newId = $db->pdo()->lastInsertId(); $db->log($userId, 'CREATE', $table, (int)$newId); $fireWebhook('CREATE', (int)$newId);
                http_response_code(201); echo json_encode(['status' => 'success', 'id' => $newId]);
                break;
            case 'PUT': case 'PATCH':
                if (!$id) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Informe o ID']); exit; }
                $data = $sanitize($body); if (empty($data)) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Payload vazio']); exit; }
                $sets = []; $params = []; foreach ($data as $k => $v) { $sets[] = "`$k` = ?"; $params[] = $v; } $params[] = $id;
                $db->pdo()->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk` = ?")->execute($params);
                $db->log($userId, 'UPDATE', $table, (int)$id); $fireWebhook('UPDATE', (int)$id);
                echo json_encode(['status' => 'success', 'id' => $id]);
                break;
            case 'DELETE':
                if (!$id) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Informe o ID']); exit; }
                $db->pdo()->prepare("UPDATE `$table` SET `deleted_at` = NOW() WHERE `$pk` = ?")->execute([$id]);
                $db->log($userId, 'DELETE', $table, (int)$id); $fireWebhook('DELETE', (int)$id);
                echo json_encode(['status' => 'success', 'id' => $id]);
                break;
            default: http_response_code(405); echo json_encode(['status' => 'error', 'message' => 'Método não suportado']);
        }
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'Erro interno']); error_log($e->getMessage()); }
    exit;
}

// ============================================================
// AUDITORIA
// ============================================================
if ($action === 'audit') {
    $db = new Introspector();
    $db->ensureAuditLogsTable();
    $fUser = trim($_GET['user'] ?? ''); $fAction = trim($_GET['action_f'] ?? ''); $fTable = trim($_GET['table_f'] ?? ''); $fFrom = trim($_GET['from'] ?? ''); $fTo = trim($_GET['to'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1)); $limit = 25; $offset = ($page - 1) * $limit;
    $where = []; $params = [];
    if ($fUser) { $where[] = "a.user_id = ?"; $params[] = (int)$fUser; }
    if ($fAction) { $where[] = "a.action = ?"; $params[] = $fAction; }
    if ($fTable) { $where[] = "a.table_name = ?"; $params[] = $fTable; }
    if ($fFrom) { $where[] = "a.created_at >= ?"; $params[] = $fFrom . ' 00:00:00'; }
    if ($fTo) { $where[] = "a.created_at <= ?"; $params[] = $fTo . ' 23:59:59'; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmtCount = $db->pdo()->prepare("SELECT COUNT(*) FROM audit_logs a $whereSql"); $stmtCount->execute($params); $total = (int)$stmtCount->fetchColumn();
    $stmt = $db->pdo()->prepare("SELECT a.*, u.nome AS user_nome, u.email AS user_email FROM audit_logs a LEFT JOIN usuarios u ON u.id = a.user_id $whereSql ORDER BY a.id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params); $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = $db->pdo()->query("SELECT id, nome, email FROM usuarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    $actions = $db->pdo()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $tablesWithLogs = $db->pdo()->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    echo $twig->render('audit.twig', ['logs' => $logs, 'total' => $total, 'page' => $page, 'totalPages' => ceil($total / $limit), 'users' => $users, 'actions' => $actions, 'tablesWithLogs' => $tablesWithLogs, 'filters' => ['user' => $fUser, 'action' => $fAction, 'table' => $fTable, 'from' => $fFrom, 'to' => $fTo]]); exit;
}

// ============================================================
// DASHBOARD
// ============================================================
if ($action === 'dashboard') {
    $db = new Introspector();
    $data = $db->getDashboardStats();
    $chartData = [];
    foreach ($data['stats'] as $s) {
        if (!empty($s['chart'])) {
            $chartData[$s['name']] = $s['chart'];
        }
    }
    echo $twig->render('dashboard.twig', [
        'stats' => $data['stats'],
        'statsJson' => jsonSafe($data['stats']),
        'chartJson' => jsonSafe($chartData),
        'top5Json' => jsonSafe($data['top5']),
        'timelineJson' => jsonSafe($data['timeline']),
        'mainTable' => $data['mainTable'],
    ]);
    exit;
}

// ============================================================
// EXCLUSÃO EM MASSA
// ============================================================
if ($action === 'delete_multiple') {
    header('Content-Type: application/json'); csrfCheck();
    $input = json_decode(file_get_contents('php://input'), true); $table = $input['table'] ?? ''; $ids = $input['ids'] ?? [];
    $db = new Introspector();
    try {
        $db->assertValidTable($table);
        if (!$db->can($userId, $table, 'DELETE')) throw new \Exception('Permissão negada');
        if (empty($ids) || !is_array($ids)) throw new \Exception('Nenhum ID informado');
        $schema = $db->tableSchema($table); $pk = $db->primaryKey($schema) ?? 'id';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->pdo()->beginTransaction();
        $db->pdo()->prepare("UPDATE `$table` SET `deleted_at` = NOW() WHERE `$pk` IN ($placeholders)")->execute($ids);
        foreach ($ids as $id) { $db->log($userId, 'DELETE_MULTIPLE', $table, (int)$id); }
        $db->pdo()->commit();
        echo json_encode(['status' => 'success', 'count' => count($ids)]);
    } catch (\Throwable $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        http_response_code(400); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// FILTROS AVANÇADOS
// ============================================================
if ($action === 'advanced_filter') {
    header('Content-Type: application/json'); $table = $_GET['table'] ?? ''; $db = new Introspector();
    try { $db->assertValidTable($table); echo json_encode(['status' => 'success', 'schema' => $db->tableSchema($table)]); }
    catch (\Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit;
}

// ============================================================
// INLINE EDITING
// ============================================================
if ($action === 'save_inline') {
    csrfCheck(); $table = $_POST['table'] ?? ''; $id = $_POST['id'] ?? 0; $field = $_POST['field'] ?? ''; $value = $_POST['value'] ?? '';
    $db = new Introspector();
    try {
        $db->assertValidTable($table); $db->assertValidColumn($table, $field);
        if (!$db->can($userId, $table, 'UPDATE')) { http_response_code(403); echo "Permissão negada"; exit; }

        $schema = $db->tableSchema($table); $pk = $db->primaryKey($schema) ?? 'id';

        if ($field === 'deleted_at' || $field === $pk || $field === 'created_at') {
            http_response_code(400); echo "Campo não editável inline."; exit;
        }

        $fieldTypes = [];
        $cfgFile = __DIR__ . "/../generated/$table/config.json";
        if (file_exists($cfgFile)) { $cfg = json_decode(file_get_contents($cfgFile), true); $fieldTypes = $cfg['field_types'] ?? []; }
        if (($fieldTypes[$field] ?? '') === 'richtext' && $value !== '') {
            $value = \PhpGen\Validation\HtmlSanitizer::sanitize((string)$value);
        }

        $db->pdo()->prepare("UPDATE `$table` SET `$field` = ? WHERE `$pk` = ?")->execute([$value, $id]);
        echo "OK";
    } catch (\Throwable $e) { http_response_code(400); echo "Erro: " . $e->getMessage(); }
    exit;
}

// ============================================================
// EXPORTAÇÃO
// ============================================================
if (in_array($action, ['export', 'export_excel', 'export_pdf'])) {
    $table = $_GET['table'] ?? '';
    $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $table)));
    $ns = \PhpGen\Config::$generatedNamespace;
    $db = new Introspector();

    try {
        $db->assertValidTable($table);
        $dir = __DIR__ . "/../generated/$table";

        if ($action === 'export') {
            $f = $dir . "/{$class}Export.php";
            if (file_exists($f)) { require_once $f; $cls = $ns . '\\' . $class . 'Export'; (new $cls($db->pdo()))->download(); }
        } elseif ($action === 'export_excel') {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                http_response_code(500);
                die("PhpSpreadsheet não instalado. Rode: composer require phpoffice/phpspreadsheet");
            }
            $f = $dir . "/{$class}ExportExcel.php";
            if (file_exists($f)) { require_once $f; $cls = $ns . '\\' . $class . 'ExportExcel'; (new $cls($db->pdo()))->download(); }
        } elseif ($action === 'export_pdf') {
            if (!class_exists(\Dompdf\Dompdf::class)) {
                http_response_code(500);
                die("Dompdf não instalado. Rode: composer require dompdf/dompdf");
            }
            $f = $dir . "/{$class}ExportPdf.php";
            if (file_exists($f)) { require_once $f; $cls = $ns . '\\' . $class . 'ExportPdf'; (new $cls($db->pdo()))->download(); }
        }
    } catch (\Throwable $e) {
        http_response_code(400);
        die("Erro na exportação: " . htmlspecialchars($e->getMessage()));
    }
    exit;
}

// ============================================================
// FAKER
// ============================================================
if ($action === 'faker') {
    $table = $_GET['table'] ?? ''; $db = new Introspector();
    try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida."); }
    $schema = $db->tableSchema($table);
    for ($i = 0; $i < 20; $i++) {
        $sets = []; $params = [];
        foreach ($schema as $c) {
            if ($c['key'] === 'PRI' || in_array($c['name'], Config::$skipInCreate, true) || $c['name'] === 'deleted_at') continue;
            $name = strtolower($c['name']); $val = '';
            if (str_contains($name, 'nome') || str_contains($name, 'name')) $val = "Usuário Teste " . rand(1, 999);
            elseif (str_contains($name, 'email')) $val = "teste" . rand(1, 999) . "@exemplo.com";
            elseif (str_contains($name, 'cpf')) $val = rand(100, 999) . "." . rand(100, 999) . "." . rand(100, 999) . "-" . rand(10, 99);
            elseif (str_contains($name, 'telefone') || str_contains($name, 'celular')) $val = "(" . rand(11, 99) . ") 9" . rand(1000, 9999) . "-" . rand(1000, 9999);
            elseif (str_contains($name, 'ativo')) $val = rand(0, 1);
            elseif (str_contains($name, 'preco') || str_contains($name, 'valor')) $val = rand(10, 500) + 0.90;
            elseif (str_contains($name, 'estoque')) $val = rand(1, 100);
            elseif ($c['type'] === 'int' || $c['type'] === 'tinyint') $val = rand(1, 100);
            elseif ($c['type'] === 'date') $val = date('Y-m-d', strtotime('-' . rand(1, 365) . ' days'));
            elseif ($c['type'] === 'datetime') $val = date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'));
            else $val = "Texto de teste " . rand(1, 100);
            $sets[] = "`{$c['name']}` = ?"; $params[] = $val;
        }
        try { $db->pdo()->prepare("INSERT INTO `$table` SET " . implode(', ', $sets))->execute($params); } catch (\Throwable $e) {}
    }
    header("Location: ?action=view_grid&table=$table"); exit;
}

// ============================================================
// CRIAÇÃO DE TABELA
// ============================================================
if ($action === 'create_table_form') { echo $twig->render('wizard_create_table.twig'); exit; }
if ($action === 'create_table') {
    $tableName = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['table_name'] ?? ''));
    if (!$tableName) { die("Nome da tabela inválido."); }
    $fields = $_POST['fields'] ?? []; $sqlCols = ["`id` INT AUTO_INCREMENT PRIMARY KEY"];
    foreach ($fields as $f) {
        if (empty($f['name'])) continue;
        $fname = preg_replace('/[^a-z0-9_]/', '', strtolower($f['name'])); $ftype = $f['type'] ?? 'VARCHAR(255)';
        $allowedTypes = ['VARCHAR(255)','VARCHAR(100)','TEXT','INT','BIGINT','TINYINT','DECIMAL(10,2)','DATE','DATETIME','TIMESTAMP','BOOLEAN'];
        if (!in_array($ftype, $allowedTypes, true)) $ftype = 'VARCHAR(255)';
        $fnull = isset($f['null']) ? 'NULL' : 'NOT NULL'; $sqlCols[] = "`$fname` $ftype $fnull";
    }
    $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (" . implode(', ', $sqlCols) . ") DEFAULT CHARSET=utf8mb4;";
    try { (new Introspector())->pdo()->exec($sql); header("Location: ?action=tables&created=1"); exit; }
    catch (\Throwable $e) { die("Erro: " . htmlspecialchars($e->getMessage()) . "<br><a href='?action=create_table_form'>Voltar</a>"); }
}

// ============================================================
// WIZARD
// ============================================================
if ($action === 'welcome') { header('Location: ?action=dashboard'); exit; }
if ($action === 'connect') {
    $erro = null; $cfg = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cfg = ['host' => $_POST['host'] ?? 'localhost', 'port' => (int)($_POST['port'] ?? 3306), 'database' => $_POST['database'] ?? '', 'user' => $_POST['user'] ?? 'root', 'password' => $_POST['password'] ?? '', 'charset' => 'utf8mb4'];
        try {
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
            new PDO($dsn, $cfg['user'], $cfg['password']);
            $configFile = __DIR__ . '/../src/Config.php'; $configContent = file_get_contents($configFile);
            foreach ($cfg as $key => $val) { $valStr = is_int($val) ? $val : "'" . addslashes($val) . "'"; $configContent = preg_replace("/'$key'\s*=>\s*.*?,/", "'$key' => $valStr,", $configContent); }
            file_put_contents($configFile, $configContent); header('Location: ?action=tables'); exit;
        } catch (\Throwable $e) { $erro = 'Erro ao conectar: ' . $e->getMessage(); }
    }
    echo $twig->render('wizard_step2.twig', ['erro' => $erro, 'cfg' => $cfg]); exit;
}
if ($action === 'tables') { try { $db = new Introspector(); $tables = $db->listTables(); } catch (\Throwable $e) { echo $twig->render('wizard_step2.twig', ['erro' => 'Erro: ' . $e->getMessage()]); exit; } echo $twig->render('wizard_step3.twig', ['tables' => $tables, 'cfg' => Config::$db]); exit; }
if ($action === 'configure') { $table = $_GET['table'] ?? ''; $db = new Introspector(); try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida"); } echo $twig->render('wizard_step4.twig', ['table' => $table, 'schema' => $db->tableSchema($table), 'fks' => $db->foreignKeys($table), 'cfg' => Config::$db]); exit; }
if ($action === 'appearance') {
    $table = $_POST['table'] ?? $_GET['table'] ?? ''; $pages = $_POST['pages'] ?? $_GET['pages'] ?? ['list', 'edit', 'add', 'delete']; $fieldTypes = $_POST['field_types'] ?? []; $fieldMasks = $_POST['field_masks'] ?? [];
    $db = new Introspector(); try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida"); }
    echo $twig->render('wizard_step5.twig', ['table' => $table, 'pages' => $pages, 'schema' => $db->tableSchema($table), 'fieldTypes' => $fieldTypes, 'fieldMasks' => $fieldMasks, 'formData' => $_POST]); exit;
}
if ($action === 'relationships') {
    $table = $_POST['table'] ?? $_GET['table'] ?? ''; $pages = $_POST['pages'] ?? $_GET['pages'] ?? ['list', 'edit', 'add', 'delete'];
    $formData = $_POST; unset($formData['action']); if (!$table) { header('Location: ?action=tables'); exit; }
    $db = new Introspector(); try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida"); }
    $allTables = $db->listTables(); $todasFKs = []; foreach ($allTables as $t) { $todasFKs[$t] = $db->foreignKeys($t); }
    $orderToSave = $_POST['field_order'] ?? ''; $cfgFile = __DIR__ . "/../generated/$table/config.json";
    if ($orderToSave && is_dir(dirname($cfgFile))) { $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : []; $cfg['field_order'] = $orderToSave; file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
    echo $twig->render('wizard_step6.twig', ['table' => $table, 'pages' => $pages, 'all_tables' => $allTables, 'formData' => $formData, 'fksJson' => jsonSafe($todasFKs)]); exit;
}
if ($action === 'generate') {
    $table = $_POST['table'] ?? ''; $relacionamentos = json_decode($_POST['relacionamentos_json'] ?? '[]', true) ?: [];
    $fieldOrder = !empty($_POST['field_order']) ? array_values(array_filter(array_map('trim', explode(',', $_POST['field_order'])))) : [];
    $options = [
        'pages' => $_POST['pages'] ?? [], 'theme' => $_POST['theme'] ?? 'light', 'border_radius' => $_POST['border_radius'] ?? 'rounded-lg', 'border_width' => $_POST['border_width'] ?? 'border',
        'form_layout' => (int)($_POST['form_layout'] ?? 1), 'labels' => $_POST['labels'] ?? [], 'show_in_grid' => $_POST['show_in_grid'] ?? [], 'show_in_form' => $_POST['show_in_form'] ?? [],
        'required_field' => $_POST['required_field'] ?? [], 'field_types' => $_POST['field_types'] ?? [], 'field_masks' => $_POST['field_masks'] ?? [], 'field_order' => $fieldOrder,
        'relacionamentos' => $relacionamentos, 'webhook_url' => $_POST['webhook_url'] ?? '', 'translations' => $translations,
    ];
    $db = new Introspector(); $gen = new Generator($db);
    try { $files = $gen->generate($table, $options); echo $twig->render('wizard_done.twig', ['table' => $table, 'files' => $files, 'pages' => $options['pages']]); }
    catch (\Throwable $e) { echo $twig->render('wizard_done.twig', ['table' => $table, 'erro' => $e->getMessage()]); }
    exit;
}

// ============================================================
// NOTIFICAÇÕES
// ============================================================
if ($action === 'notifications_list') { header('Content-Type: application/json'); $db = new Introspector(); $db->ensureNotificationsTable(); $stmt = $db->pdo()->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 10"); $stmt->execute([$userId]); $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC); echo json_encode(['status' => 'success', 'data' => $notifs, 'count' => count($notifs)]); exit; }
if ($action === 'notification_read') {
    header('Content-Type: application/json');
    csrfCheck();
    $db = new Introspector();
    $stmt = $db->pdo()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)($_POST['id'] ?? 0), $userId]);
    echo json_encode(['status' => 'success']);
    exit;
}

// ============================================================
// AI CODE ASSIST
// ============================================================
if ($action === 'ai') { echo $twig->render('ai.twig'); exit; }
if ($action === 'ai_assist') {
    header('Content-Type: application/json'); $prompt = $_POST['prompt'] ?? '';
    if (empty(Config::$geminiApiKey)) { echo json_encode(['reply' => 'A chave do Google Gemini não está configurada no Config.php.']); exit; }
    if (!function_exists('curl_init')) { echo json_encode(['reply' => 'Erro: A extensão cURL do PHP não está ativada.']); exit; }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . Config::$geminiApiKey;
    $payload = ['contents' => [['parts' => [['text' => "Você é um assistente especialista em banco de dados MySQL. O usuário vai descrever um sistema e você deve responder APENAS com o código SQL CREATE TABLE necessário. Não adicione explicações em texto, apenas o bloco SQL.\n\nPedido do usuário: " . $prompt]]]]];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
    $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
    if ($response === false) { echo json_encode(['reply' => "Erro no cURL: " . $curlError]); exit; }
    $data = json_decode($response, true);
    if ($httpCode !== 200) { $apiError = $data['error']['message'] ?? 'Erro desconhecido'; echo json_encode(['reply' => "Erro do Gemini (HTTP $httpCode): " . $apiError]); exit; }
    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'A IA não retornou texto.';
    echo json_encode(['reply' => nl2br(htmlspecialchars($reply))]); exit;
}

// ============================================================
// CRUD
// ============================================================
$table = $_GET['table'] ?? 'usuarios'; $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $table)));
$ns = \PhpGen\Config::$generatedNamespace; $db = new Introspector();

if ($action === 'view_grid') {
    if (!$db->can($userId, $table, 'SELECT')) { http_response_code(403); die("Você não tem permissão para visualizar '$table'."); }
    $f = __DIR__ . "/../generated/$table/{$class}Grid.php";
    if (file_exists($f)) {
        require_once $f; $cls = $ns . '\\' . $class . 'Grid'; $grid = new $cls($db->pdo());
        $cfgFile = __DIR__ . "/../generated/$table/config.json"; $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : []; $theme = $cfg['theme'] ?? 'light';
        $canInsert = $db->can($userId, $table, 'INSERT'); $canUpdate = $db->can($userId, $table, 'UPDATE'); $canDelete = $db->can($userId, $table, 'DELETE');
        echo "<html><head><meta charset='utf-8'><title>$table</title><link href='https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css' rel='stylesheet' /><script src='https://cdn.tailwindcss.com'></script></head>";
        echo "<body data-theme='$theme' class='bg-base-200 min-h-screen p-8'><div class='max-w-6xl mx-auto'><div class='flex justify-between mb-6'><h1 class='text-3xl font-bold text-base-content'>$table</h1><div><a href='?action=tables' class='btn btn-ghost'>Voltar</a>";
        if ($canInsert) { echo " <a href='?action=add&table=$table' class='btn btn-primary'>+ Adicionar</a>"; }
        echo "</div></div><script>window.CSRF_TOKEN = '" . csrfToken() . "';</script><script>window.CAN_UPDATE = " . ($canUpdate ? 'true' : 'false') . ";</script><script>window.CAN_DELETE = " . ($canDelete ? 'true' : 'false') . ";</script>";
        echo $grid->render(); echo "</div></body></html>";
    } else { echo "Grid não foi gerado."; }
    exit;
}

if ($action === 'view_record') {
    if (!$db->can($userId, $table, 'SELECT')) { http_response_code(403); die("Acesso negado."); }
    $f = __DIR__ . "/../generated/$table/{$class}View.php";
    if (file_exists($f)) { require_once $f; $cls = $ns . '\\' . $class . 'View'; $view = new $cls($db->pdo()); echo "<html><head><link href='https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css' rel='stylesheet' /><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-base-200 p-8'><div class='max-w-xl mx-auto'><a href='?action=view_grid&table=$table' class='btn btn-ghost mb-4'>Voltar</a>"; echo $view->render($_GET['id'] ?? 0); echo "</div></body></html>"; }
    else { echo "View não gerada."; }
    exit;
}

if ($action === 'add' || $action === 'edit') {
    $required = ($action === 'add') ? 'INSERT' : 'UPDATE';
    if (!$db->can($userId, $table, $required)) { http_response_code(403); die("Acesso negado."); }
    $f = __DIR__ . "/../generated/$table/{$class}Form.php";
    if (file_exists($f)) {
        require_once $f; $cls = $ns . '\\' . $class . 'Form'; $form = new $cls($db->pdo()); $formError = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['_csrf'])) csrfCheck();
            try { $form->handle($_POST, $_FILES, $userId); header("Location: ?action=view_grid&table=$table"); exit; }
            catch (\InvalidArgumentException $e) { $formError = $e->getMessage(); }
            catch (\Throwable $e) { $formError = 'Erro: ' . $e->getMessage(); }
        }
        $data = [];
        if ($action === 'edit' && isset($_GET['id'])) { $schema = $db->tableSchema($table); $pk = $db->primaryKey($schema) ?? 'id'; $stmt = $db->pdo()->prepare("SELECT * FROM `$table` WHERE `$pk` = ?"); $stmt->execute([$_GET['id']]); $data = $stmt->fetch(PDO::FETCH_ASSOC); }
        elseif ($formError) { $data = $_POST; }
        $cfgFile = __DIR__ . "/../generated/$table/config.json"; $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : []; $theme = $cfg['theme'] ?? 'light';
        echo "<html><head><meta charset='utf-8'><title>$table</title><link href='https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css' rel='stylesheet' /><script src='https://cdn.tailwindcss.com'></script></head><body data-theme='$theme' class='bg-base-200 min-h-screen p-8'>";
        echo "<div class='max-w-xl mx-auto'><div class='flex items-center justify-between mb-6'><h1 class='text-2xl font-bold text-base-content'>" . ($action === 'add' ? 'Adicionar' : 'Editar') . " em <span class='text-primary'>$table</span></h1><a href='?action=view_grid&table=$table' class='btn btn-ghost'>Voltar</a></div><div class='card bg-base-100 shadow-xl p-6'>";
        $html = $form->render($data); $html = str_replace('</form>', '<input type="hidden" name="_csrf" value="' . csrfToken() . '"></form>', $html);
        if ($action === 'edit') { $html = str_replace('</form>', '<input type="hidden" name="id" value="' . htmlspecialchars($_GET['id'], ENT_QUOTES) . '"></form>', $html); }
        if (!empty($formError)) { $html = '<div class="alert alert-error mb-4"><span>' . htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') . '</span></div>' . $html; }
        echo $html; echo "</div></div></body></html>";
    } else { echo "Form não gerado."; }
    exit;
}

if ($action === 'delete') {
    if (!$db->can($userId, $table, 'DELETE')) { http_response_code(403); die("Acesso negado."); }
    $f = __DIR__ . "/../generated/$table/{$class}Delete.php";
    if (file_exists($f)) { require_once $f; $cls = $ns . '\\' . $class . 'Delete'; $del = new $cls($db->pdo()); $del->handle($_GET['id'], $userId); header("Location: ?action=view_grid&table=$table"); exit; }
    else { echo "Delete não gerado."; }
    exit;
}

// ============================================================
// OpenAPI — documentação da API (público)
// ============================================================
if ($action === 'openapi') {
    $f = __DIR__ . '/openapi.json';
    if (!file_exists($f)) { http_response_code(404); die("openapi.json não encontrado"); }
    header('Content-Type: application/json; charset=utf-8');
    readfile($f);
    exit;
}

// ============================================================
// Swagger UI — renderiza o openapi.json
// ============================================================
if ($action === 'api_docs') {
    echo $twig->render('openapi.twig', ['title' => 'API Docs']);
    exit;
}

die("Ação desconhecida: $action");