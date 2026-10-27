<?php
/**
 * verificar_sistema.php — Auditoria completa: libs, config, banco, tabelas geradas, sintaxe.
 *
 * Uso: php tools/verificar_sistema.php
 * Exit code: 0 se tudo OK, 1 se houver erro.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Config;

$ok = 0;
$warn = 0;
$fail = 0;

function ok(string $label, string $detail = ''): void {
    global $ok;
    echo "  ✅ {$label}" . ($detail ? " — {$detail}" : "") . "\n";
    $ok++;
}
function warn(string $label, string $detail = ''): void {
    global $warn;
    echo "  ⚠️  {$label}" . ($detail ? " — {$detail}" : "") . "\n";
    $warn++;
}
function fail(string $label, string $detail = ''): void {
    global $fail;
    echo "  ❌ {$label}" . ($detail ? " — {$detail}" : "") . "\n";
    $fail++;
}

echo "════════════════════════════════════════════════════════════\n";
echo "  PHPGEN — Auditoria do Sistema (v2.4.0)\n";
echo "════════════════════════════════════════════════════════════\n\n";

// 1. PHP
echo "🐘 PHP:\n";
ok('versão', PHP_VERSION);
ok('ext-pdo', extension_loaded('pdo') ? 'ok' : 'FALTA');
ok('ext-pdo_mysql', extension_loaded('pdo_mysql') ? 'ok' : 'FALTA');
ok('ext-curl', extension_loaded('curl') ? 'ok' : 'FALTA');
ok('ext-zip', extension_loaded('zip') ? 'ok' : 'FALTA');
ok('ext-mbstring', extension_loaded('mbstring') ? 'ok' : 'FALTA');
echo "\n";

// 2. Libs
echo "📦 Libs (composer):\n";
ok('Twig', class_exists(\Twig\Environment::class) ? 'ok' : 'FALTA');
ok('Google2FA', class_exists(\PragmaRX\Google2FA\Google2FA::class) ? 'ok' : 'FALTA');
ok('Firebase PHP-JWT', class_exists(\Firebase\JWT\JWT::class) ? 'ok' : 'FALTA');
ok('PhpSpreadsheet', class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) ? 'ok' : 'FALTA');
ok('Dompdf', class_exists(\Dompdf\Dompdf::class) ? 'ok' : 'FALTA');
echo "\n";

// 3. Config
echo "⚙️  Config (src/Config.php):\n";
ok('generatedNamespace', Config::$generatedNamespace);
Config::$generatedNamespace === 'PhpGen\\Generated'
    ? ok('namespace alinhado com composer.json', Config::$generatedNamespace)
    : fail('namespace NÃO bate com composer.json', 'esperado PhpGen\Generated, obtido ' . Config::$generatedNamespace);
strlen(Config::$jwtSecret) >= 32
    ? ok('jwtSecret >= 32 bytes', strlen(Config::$jwtSecret) . ' chars')
    : fail('jwtSecret muito curto', strlen(Config::$jwtSecret) . ' chars');
Config::$webhookSecret !== Config::$jwtSecret
    ? ok('webhookSecret separado do JWT')
    : fail('webhookSecret igual ao JWT', 'use chaves separadas');
!empty(Config::$appName) ? ok('appName', Config::$appName) : warn('appName vazio');
echo "\n";

// 4. Banco
echo "🗄️  Banco de dados:\n";
try {
    $pdo = new PDO(
        "mysql:host=" . Config::$db['host'] . ";port=" . Config::$db['port'] . ";dbname=" . Config::$db['database'] . ";charset=utf8mb4",
        Config::$db['user'],
        Config::$db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ok('conexão PDO', Config::$db['user'] . '@' . Config::$db['host'] . '/' . Config::$db['database']);
    $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    ok('tabelas no banco', count($tabelas) . ' encontradas');
    
    // Verifica as tabelas do sistema
    $tabelasSistema = [
        'audit_logs', 'api_tokens', 'roles', 'user_roles', 
        'role_permissions', 'webhook_logs', 'notifications', 
        'user_2fa', 'revoked_tokens', 'rate_limit'
    ];
    foreach ($tabelasSistema as $t) {
        in_array($t, $tabelas) ? ok("tabela sistema `$t`") : warn("tabela sistema `$t` ausente", 'será criada no primeiro uso');
    }
} catch (\Throwable $e) {
    fail('conexão PDO', $e->getMessage());
}
echo "\n";

// 5. Tabelas geradas
echo "📁 Tabelas geradas (generated/):\n";
if (!is_dir(Config::$outputDir)) {
    fail('diretório generated/ não existe');
} else {
    $dirs = array_filter(scandir(Config::$outputDir), fn($d) => $d !== '.' && $d !== '..' && is_dir(Config::$outputDir . '/' . $d));
    if (empty($dirs)) {
        fail('nenhuma tabela gerada', 'rode: php tools/gerar_tabelas.php');
    } else {
        foreach ($dirs as $t) {
            $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $t)));
            $esperados = ['Grid', 'Form', 'View', 'Delete', 'Export', 'ExportExcel', 'ExportPdf'];
            $presentes = 0;
            foreach ($esperados as $tipo) {
                if (file_exists(Config::$outputDir . "/$t/{$class}{$tipo}.php")) $presentes++;
            }
            if ($presentes === count($esperados)) {
                ok($t, "$presentes/7 arquivos");
            } else {
                fail($t, "$presentes/7 arquivos (rode --force para regenerar)");
            }
            // Valida namespace
            $grid = Config::$outputDir . "/$t/{$class}Grid.php";
            if (file_exists($grid)) {
                $linhas = file($grid, FILE_IGNORE_NEW_LINES);
                $ns = $linhas[1] ?? '';
                $ns === 'namespace PhpGen\Generated;'
                    ? ok("$t/namespace")
                    : fail("$t/namespace", "esperado 'namespace PhpGen\Generated;', obtido '$ns'");
            }
        }
    }
}
echo "\n";

// 6. Sintaxe dos arquivos gerados
echo "🔍 Sintaxe dos arquivos gerados:\n";
$arquivosGerados = [];
if (is_dir(Config::$outputDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Config::$outputDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php') $arquivosGerados[] = $f->getPathname();
    }
}
if (empty($arquivosGerados)) {
    warn('nenhum arquivo .php gerado');
} else {
    $comErro = 0;
    foreach ($arquivosGerados as $path) {
        $out = shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1');
        if (!str_contains((string)$out, 'No syntax errors')) {
            fail(basename($path), trim((string)$out));
            $comErro++;
        }
    }
    if ($comErro === 0) ok('todos os ' . count($arquivosGerados) . ' arquivos OK');
}
echo "\n";

// 7. Uploads
echo "📤 Uploads:\n";
$uploadDir = __DIR__ . '/../public/uploads';
if (!is_dir($uploadDir)) {
    fail('diretório public/uploads/ não existe', 'crie com: mkdir public/uploads');
} elseif (!is_writable($uploadDir)) {
    fail('public/uploads/ sem permissão de escrita');
} else {
    ok('public/uploads/ gravável');
}
echo "\n";

// 8. Novos Recursos (v2.4.0)
echo "🔒 Segurança (v2.4.0):\n";
try {
    $pdo = new PDO(
        "mysql:host=" . Config::$db['host'] . ";port=" . Config::$db['port'] . ";dbname=" . Config::$db['database'] . ";charset=utf8mb4",
        Config::$db['user'],
        Config::$db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verifica se as tabelas estão no banco
    $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    in_array('revoked_tokens', $tabelas) ? ok('JWT Denylist configurado') : warn('JWT Denylist não inicializado', 'será criado no primeiro login');
    in_array('rate_limit', $tabelas) ? ok('Rate Limit configurado') : warn('Rate Limit não inicializado', 'será criado no primeiro login');
    
    // Verifica se a API está documentada
    $openapiFile = __DIR__ . '/../public/openapi.json';
    file_exists($openapiFile) ? ok('OpenAPI documentado (Swagger)') : warn('OpenAPI não encontrado', 'crie public/openapi.json');
    
} catch (\Throwable $e) {
    fail('Erro ao verificar novos recursos', $e->getMessage());
}
echo "\n";

// Resumo
echo "════════════════════════════════════════════════════════════\n";
echo " RESUMO: ✅ {$ok} ok | ⚠️  {$warn} avisos | ❌ {$fail} falhas\n";
echo "════════════════════════════════════════════════════════════\n";
exit($fail > 0 ? 1 : 0);