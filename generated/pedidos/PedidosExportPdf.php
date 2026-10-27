<?php
namespace PhpGen\Generated;
use Dompdf\Dompdf;
use PDO;

class PedidosExportPdf {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function download(): void {
        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            die('Dompdf não instalado. Rode: composer require dompdf/dompdf');
        }

        $cols = array (
  0 => 'id',
  1 => 'usuario_id',
  2 => 'produto_id',
  3 => 'quantidade',
  4 => 'total',
  5 => 'criado_em',
);

        $html = '<html><head><meta charset="utf-8"><style>';
        $html .= 'body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; }';
        $html .= 'th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }';
        $html .= 'th { background-color: #f0f0f0; font-weight: bold; }';
        $html .= 'h1 { text-align: center; color: #333; }';
        $html .= '.footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; }';
        $html .= '</style></head><body>';
        $html .= '<h1>Relatório: Pedidos</h1>';
        $html .= '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<table><thead><tr><th>Id</th><th>Usuario_id</th><th>Produto_id</th><th>Quantidade</th><th>Total</th><th>Criado_em</th></tr></thead><tbody>';

        $stmt = $this->pdo->query("SELECT `id`, `usuario_id`, `produto_id`, `quantidade`, `total`, `criado_em` FROM `pedidos` WHERE `deleted_at` IS NULL");
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
        $dompdf->stream("pedidos.pdf", ["Attachment" => true]);
        exit;
    }
}