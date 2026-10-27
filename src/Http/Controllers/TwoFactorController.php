<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class TwoFactorController
{
    public function setup(): void
    {
        global $twig;
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensure2faTable();
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        $stmt = $db->pdo()->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        $erro = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrfCheck();
            $code = $_POST['code'] ?? '';
            $secret = $_POST['secret'] ?? '';
            if ($google2fa->verifyKey($secret, $code)) {
                $db->pdo()->prepare("INSERT INTO user_2fa (user_id, secret, enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE secret = ?, enabled = 1")
                   ->execute([$userId, $secret, $secret]);
                $_SESSION['flash_success'] = '2FA ativado!';
                header('Location: ?action=dashboard');
                exit;
            }
            $erro = 'Código inválido.';
        }

        $secret = null;
        $qrCodeUrl = null;
        if (!$existing || !$existing['enabled']) {
            $secret = $google2fa->generateSecretKey();
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($google2fa->getQRCodeUrl(Config::$appName ?? 'phpgen', $_SESSION['user']['email'], $secret)) . '&size=200x200';
        }

        echo $twig->render('2fa_setup.twig', [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'enabled' => $existing['enabled'] ?? false,
            'erro' => $erro,
            'csrf' => csrfToken(),
        ]);
    }

    public function verify(): void
    {
        global $twig;
        if (!isset($_SESSION['pre_2fa_user'])) {
            header('Location: ?action=login');
            exit;
        }
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $db = new Introspector();
        $user = $_SESSION['pre_2fa_user'];
        $erro = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrfCheck();
            $code = $_POST['code'] ?? '';
            $stmt = $db->pdo()->prepare("SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1");
            $stmt->execute([$user['id']]);
            $secret = $stmt->fetchColumn();

            if ($google2fa->verifyKey($secret, $code)) {
                $_SESSION['user'] = $user;
                unset($_SESSION['pre_2fa_user']);
                $_SESSION['login_attempts'] = 0;
                $db->logLogin($user['email'], true);
                header('Location: ?action=dashboard');
                exit;
            }
            $erro = 'Código inválido.';
        }

        echo $twig->render('2fa_verify.twig', ['erro' => $erro, 'csrf' => csrfToken()]);
    }
}