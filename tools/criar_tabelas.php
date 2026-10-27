<?php
/**
 * criar_tabelas.php — Cria as tabelas de domínio (usuarios, produtos, pedidos).
 * Uso: docker compose exec app php tools/criar_tabelas.php
 */

$pdo = new PDO(
    'mysql:host=db;dbname=test;charset=utf8mb4',
    'phpgen',
    'phpgen'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0,
    estoque INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    deleted_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4;
SQL;

$pdo->exec($sql);
echo "Tabelas criadas com sucesso!" . PHP_EOL . PHP_EOL;

echo "Tabelas no banco `test`:" . PHP_EOL;
foreach ($pdo->query('SHOW TABLES') as $row) {
    echo "  - " . $row[0] . PHP_EOL;
}