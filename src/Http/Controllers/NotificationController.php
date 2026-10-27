<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class NotificationController
{
    public function list(): void
    {
        header('Content-Type: application/json');
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $db->ensureNotificationsTable();
        $stmt = $db->pdo()->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 10");
        $stmt->execute([$userId]);
        $notifs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $notifs, 'count' => count($notifs)]);
    }

    public function read(): void
    {
        header('Content-Type: application/json');
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $stmt = $db->pdo()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)($_POST['id'] ?? 0), $userId]);
        echo json_encode(['status' => 'success']);
    }
}