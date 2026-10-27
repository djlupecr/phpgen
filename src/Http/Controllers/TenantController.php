<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class TenantController
{
    public function index(): void
    {
        global $twig;
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureTenantTable();

        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }

        $tenants = $db->pdo()->query("SELECT * FROM tenants ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        echo $twig->render('tenants.twig', [
            'tenants' => $tenants,
            'csrf' => csrfToken(),
        ]);
    }

    public function switch(): void
    {
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureTenantTable();

        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }

        $tenantId = (int)($_POST['tenant_id'] ?? 1);
        $stmt = $db->pdo()->prepare("SELECT 1 FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        if ($stmt->fetchColumn()) {
            $_SESSION['tenant_id'] = $tenantId;
        }

        header('Location: ?action=dashboard');
        exit;
    }
}