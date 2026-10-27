<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class ExportController
{
    private function load(string $table, string $suffix): void
    {
        $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $table)));
        $ns = Config::$generatedNamespace;
        $db = new Introspector();
        try {
            $db->assertValidTable($table);
        } catch (\Throwable $e) {
            http_response_code(400);
            die("Tabela inválida: " . htmlspecialchars($e->getMessage()));
        }
        $f = __DIR__ . "/../../../generated/$table/{$class}{$suffix}.php";
        if (!file_exists($f)) {
            http_response_code(404);
            die("Arquivo de exportação não encontrado.");
        }
        require_once $f;
        $cls = $ns . '\\' . $class . $suffix;
        (new $cls($db->pdo()))->download();
    }

    public function csv(): void { $this->load($_GET['table'] ?? '', 'Export'); }
    public function excel(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            http_response_code(500);
            die("PhpSpreadsheet não instalado.");
        }
        $this->load($_GET['table'] ?? '', 'ExportExcel');
    }
    public function pdf(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            http_response_code(500);
            die("Dompdf não instalado.");
        }
        $this->load($_GET['table'] ?? '', 'ExportPdf');
    }
}