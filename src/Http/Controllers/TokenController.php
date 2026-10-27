<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class TokenController
{
    public function index(): void
    {
        global $twig;
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureApiTokensTable();
        $tokens = $db->pdo()->query("SELECT id, name, token_prefix, last_used_at, revoked_at, created_at FROM api_tokens WHERE user_id = $userId ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
        echo $twig->render('tokens.twig', [
            'tokens' => $tokens,
            'flash_token' => $_SESSION['flash_token'] ?? null,
            'csrf' => csrfToken(),
        ]);
        unset($_SESSION['flash_token'], $_SESSION['flash_success']);
    }

    public function create(): void
    {
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureApiTokensTable();
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $plain = bin2hex(random_bytes(32));
            $prefix = substr($plain, 0, 12);
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            $db->pdo()->prepare("INSERT INTO api_tokens (user_id, name, token_hash, token_prefix) VALUES (?, ?, ?, ?)")
               ->execute([$userId, $name, $hash, $prefix]);
            $_SESSION['flash_token'] = $plain;
            $_SESSION['flash_success'] = "Token criado!";
        }
        header('Location: ?action=tokens');
        exit;
    }

    public function revoke(): void
    {
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureApiTokensTable();
        if (isset($_GET['id'])) {
            $db->pdo()->prepare("UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND user_id = ?")
               ->execute([(int)$_GET['id'], $userId]);
        }
        header('Location: ?action=tokens');
        exit;
    }
}