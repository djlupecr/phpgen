<?php
namespace PhpGen;

class Config
{
    public static array $db = [
        'host'     => 'db',
        'port'     => 3306,
        'database' => 'test',
        'user'     => 'phpgen',
        'password' => 'phpgen',
        'charset'  => 'utf8mb4',
    ];

    public static string $outputDir = __DIR__ . '/../generated';
    public static string $generatedNamespace = 'PhpGen\\Generated';
    public static string $appName = 'phpgen';

    public static string $turnstileSiteKey   = '';
    public static string $turnstileSecretKey = '';
    public static string $geminiApiKey       = '';
    public static string $openAiApiKey       = '';
    public static string $jwtSecret          = '7c528230a40ce28011b5a2e6b0d6d16437df299f48c20a1744c53a9f55b39131';
    public static string $webhookSecret      = '14ffb5ba1a2f143adfeddf89feb954617eff5d80fb0e08df0abdf93eddb79eda';

    public static array $inputMap = [
        'int' => 'number', 'tinyint' => 'number', 'smallint' => 'number', 'bigint' => 'number',
        'decimal' => 'number', 'float' => 'number', 'double' => 'number',
        'varchar' => 'text', 'char' => 'text', 'text' => 'textarea', 'tinytext' => 'textarea', 'mediumtext' => 'textarea', 'longtext' => 'textarea',
        'date' => 'date', 'datetime' => 'datetime-local', 'timestamp' => 'datetime-local', 'time' => 'time',
        'year' => 'number', 'boolean' => 'checkbox', 'enum' => 'select',
    ];

    public static array $skipInCreate = ['id', 'created_at', 'updated_at'];
    public static array $skipInEdit   = ['id', 'created_at'];
    public static array $skipInGrid   = ['text', 'tinytext', 'mediumtext', 'longtext'];
}