<?php
/**
 * gerar_tabelas.php — Regenera as tabelas via CLI, sem precisar do navegador.
 *
 * Uso:
 *   php tools/gerar_tabelas.php                 # gera todas as tabelas do banco
 *   php tools/gerar_tabelas.php usuarios        # gera só "usuarios"
 *   php tools/gerar_tabelas.php usuarios produtos pedidos
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Config;
use PhpGen\Database\Introspector;
use PhpGen\Generator\Generator;

$args = array_slice($argv, 1);
$tabelas = [];
$options = [
    'pages'        => ['list', 'edit', 'add', 'delete'],
    'theme'        => 'light',
    'form_layout'  => 1,
    'border_radius'=> 'rounded-lg',
    'border_width' => 'border',
];
$force = false;

foreach ($args as $arg) {
    if ($arg === '--force') { $force = true; continue; }
    if (str_starts_with($arg, '--theme='))        { $options['theme'] = substr($arg, 8); continue; }
    if (str_starts_with($arg, '--layout='))       { $options['form_layout'] = (int)substr($arg, 9); continue; }
    if (str_starts_with($arg, '--pages='))        { $options['pages'] = explode(',', substr($arg, 8)); continue; }
    if (str_starts_with($arg, '--'))              { fwrite(STDERR, "Opção desconhecida: $arg\n"); exit(1); }
    $tabelas[] = $arg;
}

echo "============================================================\n";
echo "  PHPGEN - Gerador de tabelas (CLI)\n";
echo "============================================================\n\n";

$db = new Introspector();

if (empty($tabelas)) {
    $tabelas = $db->listTables();
    echo "Nenhuma tabela especificada. Gerando todas do banco `" . Config::$db['database'] . "`:\n";
    foreach ($tabelas as $t) echo "  - $t\n";
    echo "\n";
}

if (empty($tabelas)) {
    fwrite(STDERR, "ERRO: Nenhuma tabela encontrada no banco.\n");
    exit(1);
}

$gen = new Generator($db);
$ok = 0;
$erro = 0;
$puladas = 0;

foreach ($tabelas as $tabela) {
    try {
        $db->assertValidTable($tabela);
    } catch (\Throwable $e) {
        echo "  [ERRO] {$tabela}: " . $e->getMessage() . "\n";
        $erro++;
        continue;
    }

    $dir = Config::$outputDir . '/' . $tabela;
    $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $tabela)));

    if (is_dir($dir) && file_exists("$dir/{$class}Grid.php") && !$force) {
        echo "  [PULADA] {$tabela}: ja existe (use --force para sobrescrever)\n";
        $puladas++;
        continue;
    }

    try {
        $files = $gen->generate($tabela, $options);
        echo "  [OK] {$tabela}: " . count($files) . " arquivos gerados\n";
        foreach ($files as $f) {
            echo "       -> " . str_replace(Config::$outputDir, 'generated', $f) . "\n";
        }
        $ok++;
    } catch (\Throwable $e) {
        echo "  [ERRO] {$tabela}: " . $e->getMessage() . "\n";
        $erro++;
    }
}

echo "\n------------------------------------------------------------\n";
echo " Resumo: OK={$ok} | Puladas={$puladas} | Erros={$erro}\n";
echo "------------------------------------------------------------\n";

if ($ok > 0) {
    echo "\nProximo passo: composer dump-autoload --optimize\n";
}

exit($erro > 0 ? 1 : 0);