<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;
use PhpGen\Generator\Generator;

class WizardController
{
    public function welcome(): void
    {
        header('Location: ?action=dashboard');
        exit;
    }

    public function connect(): void
    {
        global $twig;
        $erro = null; $cfg = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cfg = [
                'host' => $_POST['host'] ?? 'localhost',
                'port' => (int)($_POST['port'] ?? 3306),
                'database' => $_POST['database'] ?? '',
                'user' => $_POST['user'] ?? 'root',
                'password' => $_POST['password'] ?? '',
                'charset' => 'utf8mb4',
            ];
            try {
                $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
                new \PDO($dsn, $cfg['user'], $cfg['password']);
                $configFile = __DIR__ . '/../../../src/Config.php';
                $configContent = file_get_contents($configFile);
                foreach ($cfg as $key => $val) {
                    $valStr = is_int($val) ? $val : "'" . addslashes($val) . "'";
                    $configContent = preg_replace("/'$key'\s*=>\s*.*?,/", "'$key' => $valStr,", $configContent);
                }
                file_put_contents($configFile, $configContent);
                header('Location: ?action=tables');
                exit;
            } catch (\Throwable $e) {
                $erro = 'Erro ao conectar: ' . $e->getMessage();
            }
        }
        echo $twig->render('wizard_step2.twig', ['erro' => $erro, 'cfg' => $cfg]);
    }

    public function tables(): void
    {
        global $twig;
        try {
            $db = new Introspector();
            $tables = $db->listTables();
        } catch (\Throwable $e) {
            echo $twig->render('wizard_step2.twig', ['erro' => 'Erro: ' . $e->getMessage()]);
            return;
        }
        echo $twig->render('wizard_step3.twig', ['tables' => $tables, 'cfg' => Config::$db]);
    }

    public function configure(): void
    {
        global $twig;
        $table = $_GET['table'] ?? '';
        $db = new Introspector();
        try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida"); }
        echo $twig->render('wizard_step4.twig', [
            'table' => $table,
            'schema' => $db->tableSchema($table),
            'fks' => $db->foreignKeys($table),
            'cfg' => Config::$db,
        ]);
    }

    public function appearance(): void
    {
        global $twig;
        $table = $_POST['table'] ?? $_GET['table'] ?? '';
        $pages = $_POST['pages'] ?? $_GET['pages'] ?? ['list', 'edit', 'add', 'delete'];
        $fieldTypes = $_POST['field_types'] ?? [];
        $fieldMasks = $_POST['field_masks'] ?? [];
        $db = new Introspector();
        try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida"); }
        echo $twig->render('wizard_step5.twig', [
            'table' => $table,
            'pages' => $pages,
            'schema' => $db->tableSchema($table),
            'fieldTypes' => $fieldTypes,
            'fieldMasks' => $fieldMasks,
            'formData' => $_POST,
        ]);
    }

    public function relationships(): void
    {
        global $twig;
        $table = $_POST['table'] ?? $_GET['table'] ?? '';
        $pages = $_POST['pages'] ?? $_GET['pages'] ?? ['list', 'edit', 'add', 'delete'];
        $formData = $_POST;
        unset($formData['action']);
        if (!$table) {
            header('Location: ?action=tables');
            exit;
        }
        $db = new Introspector();
        try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida"); }
        $allTables = $db->listTables();
        $todasFKs = [];
        foreach ($allTables as $t) { $todasFKs[$t] = $db->foreignKeys($t); }
        $orderToSave = $_POST['field_order'] ?? '';
        $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
        if ($orderToSave && is_dir(dirname($cfgFile))) {
            $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];
            $cfg['field_order'] = $orderToSave;
            file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        echo $twig->render('wizard_step6.twig', [
            'table' => $table,
            'pages' => $pages,
            'all_tables' => $allTables,
            'formData' => $formData,
            'fksJson' => jsonSafe($todasFKs),
        ]);
    }

    public function generate(): void
    {
        global $twig;
        global $translations;
        $table = $_POST['table'] ?? '';
        $relacionamentos = json_decode($_POST['relacionamentos_json'] ?? '[]', true) ?: [];
        $fieldOrder = !empty($_POST['field_order'])
            ? array_values(array_filter(array_map('trim', explode(',', $_POST['field_order']))))
            : [];
        $options = [
            'pages' => $_POST['pages'] ?? [],
            'theme' => $_POST['theme'] ?? 'light',
            'border_radius' => $_POST['border_radius'] ?? 'rounded-lg',
            'border_width' => $_POST['border_width'] ?? 'border',
            'form_layout' => (int)($_POST['form_layout'] ?? 1),
            'labels' => $_POST['labels'] ?? [],
            'show_in_grid' => $_POST['show_in_grid'] ?? [],
            'show_in_form' => $_POST['show_in_form'] ?? [],
            'required_field' => $_POST['required_field'] ?? [],
            'field_types' => $_POST['field_types'] ?? [],
            'field_masks' => $_POST['field_masks'] ?? [],
            'field_order' => $fieldOrder,
            'relacionamentos' => $relacionamentos,
            'webhook_url' => $_POST['webhook_url'] ?? '',
            'translations' => $translations,
        ];
        $db = new Introspector();
        $gen = new Generator($db);
        try {
            $files = $gen->generate($table, $options);
            echo $twig->render('wizard_done.twig', ['table' => $table, 'files' => $files, 'pages' => $options['pages']]);
        } catch (\Throwable $e) {
            echo $twig->render('wizard_done.twig', ['table' => $table, 'erro' => $e->getMessage()]);
        }
    }

    public function createTableForm(): void
    {
        global $twig;
        echo $twig->render('wizard_create_table.twig');
    }

    public function createTable(): void
    {
        $tableName = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['table_name'] ?? ''));
        if (!$tableName) { die("Nome da tabela inválido."); }
        $fields = $_POST['fields'] ?? [];
        $sqlCols = ["`id` INT AUTO_INCREMENT PRIMARY KEY"];
        foreach ($fields as $f) {
            if (empty($f['name'])) continue;
            $fname = preg_replace('/[^a-z0-9_]/', '', strtolower($f['name']));
            $ftype = $f['type'] ?? 'VARCHAR(255)';
            $allowed = ['VARCHAR(255)', 'VARCHAR(100)', 'TEXT', 'INT', 'BIGINT', 'TINYINT', 'DECIMAL(10,2)', 'DATE', 'DATETIME', 'TIMESTAMP', 'BOOLEAN'];
            if (!in_array($ftype, $allowed, true)) $ftype = 'VARCHAR(255)';
            $fnull = isset($f['null']) ? 'NULL' : 'NOT NULL';
            $sqlCols[] = "`$fname` $ftype $fnull";
        }
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (" . implode(', ', $sqlCols) . ") DEFAULT CHARSET=utf8mb4;";
        try {
            (new Introspector())->pdo()->exec($sql);
            header("Location: ?action=tables&created=1");
            exit;
        } catch (\Throwable $e) {
            die("Erro: " . htmlspecialchars($e->getMessage()) . "<br><a href='?action=create_table_form'>Voltar</a>");
        }
    }
}