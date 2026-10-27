<?php
namespace PhpGen\Database\Drivers;

use PDO;

class PostgreSQLDriver implements DatabaseDriver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listTables(): array
    {
        $stmt = $this->pdo->query("
            SELECT table_name FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function tableSchema(string $table): array
    {
        $stmt = $this->pdo->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);
        // ...
    }

    public function limitOffset(int $limit, int $offset): string
    {
        return "LIMIT $limit OFFSET $offset";
    }
}