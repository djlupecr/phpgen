<?php
namespace PhpGen\Generated;
use Dompdf\Dompdf;
use PDO;

class WebhookQueueExportPdf {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function download(): void {
        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            die('Dompdf não instalado. Rode: composer require dompdf/dompdf');
        }

        $cols = array (
  0 => 'id',
  1 => 'url',
  2 => 'signature',
  3 => 'attempts',
  4 => 'max_attempts',
  5 => 'status',
  6 => 'last_attempt',
  7 => 'next_attempt',
  8 => 'last_status_code',
  9 => 'created_at',
);

        $html = '<html><head><meta charset="utf-8"><style>';
        $html .= 'body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; }';
        $html .= 'th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }';
        $html .= 'th { background-color: #f0f0f0; font-weight: bold; }';
        $html .= 'h1 { text-align: center; color: #333; }';
        $html .= '.footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; }';
        $html .= '</style></head><body>';
        $html .= '<h1>Relatório: Webhook_queue</h1>';
        $html .= '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<table><thead><tr><th>Id</th><th>Url</th><th>Signature</th><th>Attempts</th><th>Max_attempts</th><th>Status</th><th>Last_attempt</th><th>Next_attempt</th><th>Last_status_code</th><th>Created_at</th></tr></thead><tbody>';

        $stmt = $this->pdo->query("SELECT `id`, `url`, `signature`, `attempts`, `max_attempts`, `status`, `last_attempt`, `next_attempt`, `last_status_code`, `created_at` FROM `webhook_queue` WHERE `deleted_at` IS NULL");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr>';
            foreach ($cols as $col) {
                $html .= '<td>' . htmlspecialchars((string)($row[$col] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">phpgen - Sistema de Gestão</div>';
        $html .= '</body></html>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream("webhook_queue.pdf", ["Attachment" => true]);
        exit;
    }
}