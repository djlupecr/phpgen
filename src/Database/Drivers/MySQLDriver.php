<?php
namespace PhpGen\Database\Drivers;

use PDO;

class MySQLDriver implements DatabaseDriver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listTables(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function tableSchema(string $table): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Nome inválido.");
        }
        $stmt = $this->pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($cols as $c) {
            $out[] = [
                'name'     => $c['Field'],
                'type'     => $this->normalizeType($c['Type']),
                'raw_type' => $c['Type'],
                'nullable' => $c['Null'] === 'YES',
                'key'      => $c['Key'],
                'default'  => $c['Default'],
                'extra'    => $c['Extra'],
            ];
        }
        return $out;
    }

    public function primaryKey(array $schema): ?string
    {
        foreach ($schema as $col) {
            if ($col['key'] === 'PRI') return $col['name'];
        }
        return null;
    }

    public function foreignKeys(string $table): array
    {
        $db = \PhpGen\Config::$db['database'];
        $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = :db
                  AND TABLE_NAME = :t
                  AND REFERENCED_TABLE_NAME IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':db' => $db, ':t' => $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'column'     => $r['COLUMN_NAME'],
                'ref_table'  => $r['REFERENCED_TABLE_NAME'],
                'ref_column' => $r['REFERENCED_COLUMN_NAME'],
            ];
        }
        return $out;
    }

    public function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function limitOffset(int $limit, int $offset): string
    {
        return "LIMIT $limit OFFSET $offset";
    }

    public function describeTable(string $table): array
    {
        return $this->tableSchema($table);
    }

    public function supportsSoftDelete(): bool
    {
        return true;
    }

    private function normalizeType(string $raw): string
    {
        $raw = strtolower($raw);
        return preg_match('/^([a-z]+)/', $raw, $m) ? $m[1] : $raw;
    }
}