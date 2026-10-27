<?php
namespace PhpGen\Validation;

use PhpGen\Database\Introspector;

class BackupManager
{
    private Introspector $db;
    private string $generatedDir;

    public function __construct(Introspector $db)
    {
        $this->db = $db;
        $this->generatedDir = __DIR__ . '/../../generated';
    }

    /**
     * [FIX #56] Cria um backup completo (arquivos + banco de dados)
     */
    public function createBackup(): string
    {
        $zipFile = sys_get_temp_dir() . '/phpgen_backup_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Não foi possível criar o arquivo ZIP.");
        }

        // 1. Adiciona a pasta generated/ inteira
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->generatedDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'generated/' . substr($filePath, strlen($this->generatedDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        // 2. Gera o SQL (estrutura de tabelas + dados essenciais)
        $sqlDump = $this->generateSqlDump();
        $zip->addFromString('database.sql', $sqlDump);

        $zip->close();
        return $zipFile;
    }

    /**
     * Gera um dump SQL simplificado (estrutura + dados de controle)
     */
    private function generateSqlDump(): string
    {
        $sql = "-- PHPGEN BACKUP\n-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $this->db->listTables();
        $controlTables = ['usuarios', 'roles', 'user_roles', 'role_permissions', 'api_tokens', 'audit_logs', 'webhook_logs'];

        foreach ($tables as $table) {
            $schema = $this->db->tableSchema($table);
            $cols = [];
            foreach ($schema as $c) {
                $type = $c['raw_type'];
                $null = $c['nullable'] ? 'NULL' : 'NOT NULL';
                $default = $c['default'] !== null ? "DEFAULT '{$c['default']}'" : '';
                $extra = $c['extra'];
                $key = $c['key'] === 'PRI' ? 'PRIMARY KEY' : '';
                $cols[] = trim("`{$c['name']}` $type $null $default $extra $key");
            }
            $sql .= "CREATE TABLE IF NOT EXISTS `$table` (\n  " . implode(",\n  ", array_filter($cols)) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";

            // Se for tabela de controle, exporta os dados
            if (in_array($table, $controlTables)) {
                $stmt = $this->db->pdo()->query("SELECT * FROM `$table`");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $vals = array_map(fn($v) => $v === null ? 'NULL' : $this->db->pdo()->quote($v), $row);
                    $colsStr = "`" . implode('`, `', array_keys($row)) . "`";
                    $valsStr = implode(", ", $vals);
                    $sql .= "INSERT INTO `$table` ($colsStr) VALUES ($valsStr);\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    /**
     * [FIX #57] Restaura um backup a partir de um arquivo ZIP
     */
    public function restoreBackup(string $zipPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Não foi possível abrir o arquivo ZIP.");
        }

        $restoredFiles = 0;

        // 1. Extrai os arquivos para generated/
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_starts_with($filename, 'generated/')) {
                $targetPath = __DIR__ . '/../../' . $filename;
                $dir = dirname($targetPath);
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                file_put_contents($targetPath, $zip->getFromIndex($i));
                $restoredFiles++;
            }
        }

        // 2. Executa o SQL
        $sql = $zip->getFromName('database.sql');
        if ($sql) {
            // Remove comentários e divide por ';'
            $sql = preg_replace('/--.*\n/', '', $sql);
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($queries as $q) {
                if (!empty($q)) {
                    try { $this->db->pdo()->exec($q); } catch (\Throwable $e) { error_log('Restore SQL: ' . $e->getMessage()); }
                }
            }
        }

        $zip->close();
        return ['files' => $restoredFiles, 'sql' => !empty($sql)];
    }
}