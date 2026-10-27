<?php
namespace PhpGen\Generated;
use PDO;
class PedidosExport {
    private PDO $pdo; public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function download(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "﻿");
        fputcsv($output, [id, usuario_id, produto_id, quantidade, total, criado_em], ';');
        $stmt = $this->pdo->query("SELECT `id`, `usuario_id`, `produto_id`, `quantidade`, `total`, `criado_em` FROM `pedidos` WHERE `deleted_at` IS NULL");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row, ';');
        fclose($output); exit;
    }
}