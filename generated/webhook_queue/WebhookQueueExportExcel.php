<?php
namespace PhpGen\Generated;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PDO;

class WebhookQueueExportExcel {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function download(): void {
        if (!class_exists(Spreadsheet::class)) {
            http_response_code(500);
            die('PhpSpreadsheet não instalado. Rode: composer require phpoffice/phpspreadsheet');
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

        $stmt = $this->pdo->query("SELECT `id`, `url`, `signature`, `attempts`, `max_attempts`, `status`, `last_attempt`, `next_attempt`, `last_status_code`, `created_at` FROM `webhook_queue` WHERE `deleted_at` IS NULL");
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
        header('Content-Disposition: attachment; filename="webhook_queue.xlsx"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}