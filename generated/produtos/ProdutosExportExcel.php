<?php
namespace PhpGen\Generated;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PDO;

class ProdutosExportExcel {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function download(): void {
        if (!class_exists(Spreadsheet::class)) {
            http_response_code(500);
            die('PhpSpreadsheet não instalado. Rode: composer require phpoffice/phpspreadsheet');
        }

        $cols = array (
  0 => 'id',
  1 => 'nome',
  2 => 'preco',
  3 => 'estoque',
  4 => 'ativo',
);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colLetter = 'A';
        foreach ($cols as $col) {
            $sheet->setCellValue($colLetter . '1', ucfirst($col));
            $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
            $sheet->getStyle($colLetter . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            $colLetter++;
        }

        $stmt = $this->pdo->query("SELECT `id`, `nome`, `preco`, `estoque`, `ativo` FROM `produtos` WHERE `deleted_at` IS NULL");
        $rowNum = 2;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $colLetter = 'A';
            foreach ($cols as $col) {
                $sheet->setCellValue($colLetter . $rowNum, $row[$col] ?? '');
                $sheet->getStyle($colLetter . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $colLetter++;
            }
            $rowNum++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="produtos.xlsx"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}