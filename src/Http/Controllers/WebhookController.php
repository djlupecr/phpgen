<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class WebhookController
{
    public function index(): void
    {
        global $twig;
        $db = new Introspector();
        $db->ensureWebhookLogsTable();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * 25;
        $total = (int)$db->pdo()->query("SELECT COUNT(*) FROM webhook_logs")->fetchColumn();
        $logs = $db->pdo()->query("SELECT * FROM webhook_logs ORDER BY id DESC LIMIT 25 OFFSET $offset")->fetchAll(\PDO::FETCH_ASSOC);
        echo $twig->render('webhook_logs.twig', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / 25),
        ]);
    }
	
	public function process(): void
	{
		header('Content-Type: application/json; charset=utf-8');

		// Autorização via secret na URL (para cron)
		$secret = $_GET['secret'] ?? '';
		if ($secret !== \PhpGen\Config::$webhookSecret) {
			http_response_code(403);
			echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
			exit;
		}

		$db = new \PhpGen\Database\Introspector();
		$processor = new \PhpGen\Jobs\WebhookProcessor($db);
		$result = $processor->process(50);

		echo json_encode(['status' => 'success', 'result' => $result]);
	}
}