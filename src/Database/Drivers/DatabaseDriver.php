<?php
namespace PhpGen\Database\Drivers;

interface DatabaseDriver
{
    public function listTables(): array;
    public function tableSchema(string $table): array;
    public function primaryKey(array $schema): ?string;
    public function foreignKeys(string $table): array;
    public function quote(string $identifier): string;
    public function limitOffset(int $limit, int $offset): string;
}