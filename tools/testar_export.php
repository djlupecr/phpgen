<?php
/**
 * testar_export.php — Valida se as libs de exportação estão instaladas
 * e se os arquivos gerados são sintaticamente válidos.
 *
 * Uso: php tools/testar_export.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Config;

$ok = 0;
$fail = 0;
$warn = 0;

function check(string $label, bool $cond, string $detail = ''): void {
    global $ok, $fail;
    if ($cond) {
        echo "  ✅ {$label}\n";
        $ok++;
    } else {
        echo "  ❌ {$label}" . ($detail ? " — {$detail}" : "") . "\n";
        $fail++;
    }
}

function warn(string $label, string $detail = ''): void {
    global $warn;
    echo "  ⚠️  {$label}" . ($detail ? " — {$detail}" : "") . "\n";
    $warn++;
}

echo "============================================================\n";
echo " TESTES DE EXPORTAÇÃO — phpgen\n";
echo "============================================================\n\n";

// 1. Dependências instaladas
echo "📦 Dependências:\n";
check(
    'PhpSpreadsheet instalado',
    class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class),
    'rode: composer require phpoffice/phpspreadsheet'
);
check(
    'Dompdf instalado',
    class_exists(\Dompdf\Dompdf::class),
    'rode: composer require dompdf/dompdf'
);
check(
    'Firebase PHP-JWT instalado',
    class_exists(\Firebase\JWT\JWT::class),
    'rode: composer require firebase/php-jwt'
);
check(
    'Google2FA instalado',
    class_exists(\PragmaRX\Google2FA\Google2FA::class),
    'rode: composer require pragmarx/google2fa'
);
echo "\n";

// 2. Configuração
echo "⚙️  Configuração:\n";
check(
    'Config::$generatedNamespace = PhpGen\\Generated',
    Config::$generatedNamespace === 'PhpGen\\Generated',
    'atual: ' . Config::$generatedNamespace
);
check(
    'Config::$jwtSecret >= 32 chars',
    strlen(Config::$jwtSecret) >= 32,
    'atual: ' . strlen(Config::$jwtSecret) . ' chars'
);
check(
    'Config::$webhookSecret definido e separado do JWT',
    !empty(Config::$webhookSecret) && Config::$webhookSecret !== Config::$jwtSecret,
    'configure um segredo separado'
);
echo "\n";

// 3. Diretório generated/
echo "📁 Diretório generated/:\n";
check(
    'Diretório existe',
    is_dir(Config::$outputDir),
    Config::$outputDir
);

$tabelas = is_dir(Config::$outputDir)
    ? array_filter(scandir(Config::$outputDir), fn($d) => $d !== '.' && $d !== '..' && is_dir(Config::$outputDir . '/' . $d))
    : [];

if (empty($tabelas)) {
    warn('Nenhuma tabela gerada ainda', 'rode o wizard em ?action=tables');
} else {
    echo "  Tabelas encontradas: " . implode(', ', $tabelas) . "\n";
    foreach ($tabelas as $t) {
        $dir = Config::$outputDir . '/' . $t;
        $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $t)));
        $arquivos = [
            'Grid'        => "{$class}Grid.php",
            'Form'        => "{$class}Form.php",
            'View'        => "{$class}View.php",
            'Delete'      => "{$class}Delete.php",
            'Export CSV'  => "{$class}Export.php",
            'Export XLSX' => "{$class}ExportExcel.php",
            'Export PDF'  => "{$class}ExportPdf.php",
            'config.json' => 'config.json',
        ];
        foreach ($arquivos as $tipo => $file) {
            $path = $dir . '/' . $file;
            $exists = file_exists($path);
            if (!$exists) {
                check("{$t}/{$tipo}", false, 'arquivo ausente');
                continue;
            }
            if ($file === 'config.json') {
                $json = json_decode(file_get_contents($path), true);
                check("{$t}/config.json válido", is_array($json), 'JSON inválido');
            } else {
                // valida sintaxe PHP
                $out = shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1');
                $sintaxeOk = str_contains((string)$out, 'No syntax errors');
                check("{$t}/{$tipo} (sintaxe)", $sintaxeOk, trim((string)$out));
            }
        }

        // valida namespace
        $primeiroArquivo = $dir . '/' . $class . 'Grid.php';
        if (file_exists($primeiroArquivo)) {
            $linhas = file($primeiroArquivo, FILE_IGNORE_NEW_LINES);
            $linhaNamespace = $linhas[1] ?? '';
            check(
                "{$t}/namespace correto (1 barra)",
                $linhaNamespace === 'namespace PhpGen\Generated;',
                'encontrado: ' . $linhaNamespace
            );
        }
    }
}
echo "\n";

// 4. Banco de dados
echo "🗄️  Banco de dados:\n";
try {
    $pdo = new PDO(
        "mysql:host=" . Config::$db['host'] . ";port=" . Config::$db['port'] . ";dbname=" . Config::$db['database'] . ";charset=utf8mb4",
        Config::$db['user'],
        Config::$db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    check('Conexão PDO com o banco', true);
} catch (\Throwable $e) {
    check('Conexão PDO com o banco', false, $e->getMessage());
}
echo "\n";

// 5. Resumo
echo "============================================================\n";
echo " RESUMO: ✅ {$ok} ok | ⚠️  {$warn} avisos | ❌ {$fail} falhas\n";
echo "============================================================\n";

if ($fail > 0) {
    echo "\n🚨 Corrija as falhas acima antes de fazer deploy.\n";
    exit(1);
} elseif ($warn > 0) {
    echo "\n⚠️  Sistema funcional, mas há avisos a revisar.\n";
    exit(0);
} else {
    echo "\n🎉 Tudo pronto para produção!\n";
    exit(0);
}