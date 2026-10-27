<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class HealthController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $checks = [];

        try {
            $db = new Introspector();
            $db->pdo()->query("SELECT 1");
            $checks['db'] = ['ok' => true, 'detail' => 'conexão PDO'];
        } catch (\Throwable $e) {
            $checks['db'] = ['ok' => false, 'detail' => $e->getMessage()];
        }

        $checks['libs'] = [
            'ok' => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)
                 && class_exists(\Dompdf\Dompdf::class)
                 && class_exists(\Firebase\JWT\JWT::class)
                 && class_exists(\PragmaRX\Google2FA\Google2FA::class),
            'detail' => [
                'phpspreadsheet' => class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class),
                'dompdf'         => class_exists(\Dompdf\Dompdf::class),
                'firebase_jwt'   => class_exists(\Firebase\JWT\JWT::class),
                'google2fa'      => class_exists(\PragmaRX\Google2FA\Google2FA::class),
            ],
        ];

        $checks['config'] = [
            'ok' => Config::$generatedNamespace === 'PhpGen\\Generated'
                 && strlen(Config::$jwtSecret) >= 32
                 && !empty(Config::$webhookSecret),
            'detail' => [
                'generatedNamespace' => Config::$generatedNamespace,
                'jwtSecret_len'      => strlen(Config::$jwtSecret),
                'webhookSecret_set'  => !empty(Config::$webhookSecret),
            ],
        ];

        $tabelasGeradas = [];
        if (is_dir(Config::$outputDir)) {
            foreach (scandir(Config::$outputDir) as $d) {
                if ($d === '.' || $d === '..' || !is_dir(Config::$outputDir . '/' . $d)) continue;
                $tabelasGeradas[] = $d;
            }
        }
        $checks['generated'] = ['ok' => !empty($tabelasGeradas), 'detail' => $tabelasGeradas];

        $uploadDir = __DIR__ . '/../../../public/uploads';
        $checks['uploads_writable'] = ['ok' => is_dir($uploadDir) && is_writable($uploadDir), 'detail' => $uploadDir];

        $allOk = true;
        foreach ($checks as $c) { if (!$c['ok']) { $allOk = false; break; } }

        http_response_code($allOk ? 200 : 503);
        echo json_encode([
            'status' => $allOk ? 'ok' : 'degraded',
            'timestamp' => date('c'),
            'checks' => $checks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}