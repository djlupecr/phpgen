<?php
namespace PhpGen\Generated;
use PDO;
class WebhookQueueExport {
    private PDO $pdo; public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function download(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="webhook_queue.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "﻿");
        fputcsv($output, [id, url, signature, attempts, max_attempts, status, last_attempt, next_attempt, last_status_code, created_at], ';');
        $stmt = $this->pdo->query("SELECT `id`, `url`, `signature`, `attempts`, `max_attempts`, `status`, `last_attempt`, `next_attempt`, `last_status_code`, `created_at` FROM `webhook_queue` WHERE `deleted_at` IS NULL");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row, ';');
        fclose($output); exit;
    }
}