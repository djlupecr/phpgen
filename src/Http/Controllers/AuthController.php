<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class AuthController
{
    private function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
            || ($_GET['format'] ?? '') === 'json';
    }

    public function login(): void
    {
        $wantsJson = $this->wantsJson();
        $erro = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $attempts = (int)($_SESSION['login_attempts'] ?? 0);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $dbRate = new Introspector();

            // Rate Limit
            if (!$dbRate->checkRateLimit($ip, 'login', 5, 300)) {
                if ($wantsJson) {
                    http_response_code(429);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Muitas tentativas. Aguarde 5 minutos.']);
                    exit;
                }
                $erro = "Muitas tentativas. Aguarde 5 minutos.";
            }

            // Turnstile
            if (!$erro && !$wantsJson && !empty(Config::$turnstileSecretKey)) {
                $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
                $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'secret' => Config::$turnstileSecretKey,
                        'response' => $turnstileResponse,
                        'remoteip' => $ip,
                    ]),
                    CURLOPT_RETURNTRANSFER => true,
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

                $db = new Introspector();
                $stmt = $db->pdo()->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($user && password_verify($senha, $user['senha'])) {
                    $db->ensure2faTable();
                    $stmt2fa = $db->pdo()->prepare("SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1");
                    $stmt2fa->execute([$user['id']]);

                    if ($stmt2fa->fetchColumn()) {
                        if ($wantsJson) {
                            http_response_code(401);
                            header('Content-Type: application/json');
                            echo json_encode(['status' => 'error', 'message' => '2FA necessário', 'requires_2fa' => true, 'user_id' => $user['id']]);
                            exit;
                        }
                        $_SESSION['pre_2fa_user'] = $user;
                        header('Location: ?action=2fa_verify');
                        exit;
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
                            'iss' => 'phpgen', 'iat' => time(), 'exp' => time() + 3600,
                            'jti' => $jti, 'sub' => $user['id'],
                            'email' => $user['email'], 'roles' => $db->getUserRoles($user['id']),
                        ];
                        $jwtToken = \Firebase\JWT\JWT::encode($payload, Config::$jwtSecret, 'HS256');
                    }

                    if ($wantsJson) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'success',
                            'user' => ['id' => $user['id'], 'nome' => $user['nome'] ?? '', 'email' => $user['email'] ?? ''],
                            'csrf' => csrfToken(),
                            'jwt' => $jwtToken,
                        ]);
                        exit;
                    }
                    header('Location: ?action=dashboard');
                    exit;
                } else {
                    $db->logLogin($email, false, 'Credenciais inválidas');
                    $dbRate->incrementRateLimit($ip, 'login');
                    $_SESSION['login_attempts'] = $attempts + 1;
                    $erro = "E-mail ou senha inválidos.";
                }
            }
        }

        if ($wantsJson) {
            http_response_code($erro ? 401 : 400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $erro ?? 'Envie POST com email e senha']);
            exit;
        }

        global $twig;
        echo $twig->render('login.twig', [
            'erro' => $erro,
            'install_success' => $_SESSION['install_success'] ?? null,
            'turnstile_site_key' => Config::$turnstileSiteKey,
        ]);
        unset($_SESSION['install_success']);
    }

    public function logout(): void
    {
        session_destroy();
        header('Location: ?action=login');
        exit;
    }

    public function logoutApi(): void
    {
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
}