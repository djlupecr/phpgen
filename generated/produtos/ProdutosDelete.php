<?php
namespace PhpGen\Generated;
use PDO;
class ProdutosDelete {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function handle($id, int $userId = 0): void {
        $stmt = $this->pdo->prepare("UPDATE `produtos` SET `deleted_at` = NOW() WHERE `id` = ?");
        $stmt->execute([$id]);
        try {
            $stmtLog = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, 'DELETE', ?, ?, ?)");
            $stmtLog->execute([$userId, 'produtos', $id, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Throwable $e) {}
        $webhookUrl = '';
        if (!empty($webhookUrl)) {
            $payload = json_encode(['action' => 'DELETE', 'table' => 'produtos', 'id' => $id]);
            $ch = curl_init($webhookUrl); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            curl_exec($ch); curl_close($ch);
        }
    }
}