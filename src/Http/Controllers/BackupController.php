<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class BackupController
{
    public function index(): void
    {
        global $twig;
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }
        echo $twig->render('backup.twig', [
            'flash_success' => $_SESSION['flash_success'] ?? null,
            'csrf' => csrfToken(),
        ]);
        unset($_SESSION['flash_success']);
    }

    public function download(): void
    {
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }
        $manager = new \PhpGen\Validation\BackupManager($db);
        $zipPath = $manager->createBackup();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.zip"');
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    public function restore(): void
    {
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $manager = new \PhpGen\Validation\BackupManager($db);
            $result = $manager->restoreBackup($_FILES['backup_file']['tmp_name']);
            $_SESSION['flash_success'] = "Restaurado! {$result['files']} arquivos.";
        }
        header('Location: ?action=backup');
        exit;
    }
}