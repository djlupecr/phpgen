<?php
require __DIR__ . '/../vendor/autoload.php';

// Detecta se está rodando dentro do Docker
$isDocker = file_exists('/.dockerenv') || gethostname() !== false && str_contains(gethostname(), 'phpgen');

// Se estiver no Docker, usa as variáveis do compose (db:3306)
if ($isDocker) {
    // Não sobrescreve — deixa o Config.php usar DB_HOST=db
    // Apenas garante que o autoload está carregado
} else {
    // No host: lê o .env.testing (127.0.0.1:3307)
    $envFile = __DIR__ . '/../.env.testing';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            putenv(trim($line));
        }
    }

    if (getenv('DB_HOST')) {
        \PhpGen\Config::$db = [
            'host'     => getenv('DB_HOST'),
            'port'     => (int)getenv('DB_PORT'),
            'database' => getenv('DB_NAME'),
            'user'     => getenv('DB_USER'),
            'password' => getenv('DB_PASS'),
            'charset'  => 'utf8mb4',
        ];
    }
}

date_default_timezone_set('America/Sao_Paulo');