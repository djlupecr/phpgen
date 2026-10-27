<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class RoleController
{
    public function index(): void
    {
        global $twig;
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureRbacTables();

        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }

        $usuarios = $db->pdo()->query("SELECT id, nome, email FROM usuarios ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC);
        $roles = $db->pdo()->query("SELECT id, slug, name FROM roles ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $papeisPorUsuario = [];
        foreach ($db->pdo()->query("SELECT ur.user_id, r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id") as $row) {
            $papeisPorUsuario[(int)$row['user_id']][] = $row['slug'];
        }

        echo $twig->render('roles.twig', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'papeisPorUsuario' => $papeisPorUsuario,
            'csrf' => csrfToken(),
        ]);
    }

    public function assign(): void
    {
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureRbacTables();
        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }

        $targetUser = (int)($_POST['user_id'] ?? 0);
        $roleSlug = trim($_POST['role'] ?? '');
        if ($targetUser && $roleSlug) {
            $stmtR = $db->pdo()->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1");
            $stmtR->execute([$roleSlug]);
            if ($roleId = (int)$stmtR->fetchColumn()) {
                $db->pdo()->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")
                   ->execute([$targetUser, $roleId]);
            }
        }
        header('Location: ?action=roles');
        exit;
    }

    public function remove(): void
    {
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureRbacTables();
        if (!in_array('admin', $db->getUserRoles($userId), true)) {
            http_response_code(403);
            die("Acesso negado.");
        }

        if (isset($_GET['user_id'], $_GET['role'])) {
            $stmtR = $db->pdo()->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1");
            $stmtR->execute([$_GET['role']]);
            if ($roleId = (int)$stmtR->fetchColumn()) {
                $db->pdo()->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?")
                   ->execute([(int)$_GET['user_id'], $roleId]);
            }
        }
        header('Location: ?action=roles');
        exit;
    }
}