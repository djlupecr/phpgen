<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$erro = null;
$sucesso = false;

// [FIX] Não bloqueia mais se já estiver instalado — sempre permite reinstalar
$configFile = __DIR__ . '/../src/Config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host     = trim($_POST['host'] ?? 'localhost');
    $port     = (int)($_POST['port'] ?? 3306);
    $database = trim($_POST['database'] ?? '');
    $user     = trim($_POST['user'] ?? 'root');
    $password = $_POST['password'] ?? '';
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_password'] ?? '';

    if (empty($database) || empty($adminEmail) || empty($adminPass)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } else {
        try {
            // 1. Tenta conectar
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // 2. Cria as tabelas do sistema (se não existirem)
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(20) NOT NULL,
                table_name VARCHAR(100) NOT NULL,
                record_id BIGINT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                token_prefix VARCHAR(12) NOT NULL,
                last_used_at DATETIME NULL,
                expires_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                PRIMARY KEY (user_id, role_id)
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                table_name VARCHAR(100) NULL,
                can_select TINYINT(1) NOT NULL DEFAULT 0,
                can_insert TINYINT(1) NOT NULL DEFAULT 0,
                can_update TINYINT(1) NOT NULL DEFAULT 0,
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY uk_role_table (role_id, table_name)
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS webhook_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL,
                record_id BIGINT NULL,
                action VARCHAR(20) NOT NULL,
                url VARCHAR(255) NOT NULL,
                status_code INT NULL,
                response_body TEXT NULL,
                attempt INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS user_2fa (
                user_id INT PRIMARY KEY,
                secret VARCHAR(255) NOT NULL,
                enabled TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARSET=utf8mb4;");

            // 3. Garante que a tabela usuarios exista
            try {
                $pdo->query("SELECT 1 FROM usuarios LIMIT 1");
            } catch (Throwable $e) {
                $pdo->exec("CREATE TABLE usuarios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(100) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    senha VARCHAR(255) NOT NULL,
                    ativo TINYINT(1) DEFAULT 1,
                    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                    deleted_at DATETIME NULL
                ) DEFAULT CHARSET=utf8mb4;");
            }

            // 4. Cria/atualiza o admin
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$adminEmail]);
            $existing = $stmt->fetchColumn();

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);

            if ($existing) {
                $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $existing]);
                $adminId = $existing;
            } else {
                $pdo->prepare("INSERT INTO usuarios (nome, email, senha, ativo) VALUES ('Admin', ?, ?, 1)")->execute([$adminEmail, $hash]);
                $adminId = $pdo->lastInsertId();
            }

            // 5. Dá permissão de admin
            $pdo->exec("INSERT IGNORE INTO roles (slug, name, description) VALUES ('admin', 'Administrador', 'Acesso total')");
            $roleId = $pdo->query("SELECT id FROM roles WHERE slug = 'admin'")->fetchColumn();
            if ($roleId) {
                $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$adminId, $roleId]);
            }

            // 6. Gera o Config.php
            $configContent = "<?php
namespace PhpGen;

class Config
{
    public static array \$db = [
        'host'     => '{$host}',
        'port'     => {$port},
        'database' => '{$database}',
        'user'     => '{$user}',
        'password' => '{$password}',
        'charset'  => 'utf8mb4',
    ];

    public static string \$outputDir = __DIR__ . '/../generated';
    public static string \$generatedNamespace = 'PhpGen\\\\Generated';
    public static string \$appName = 'phpgen';

    public static string \$turnstileSiteKey   = '';
    public static string \$turnstileSecretKey = '';
    public static string \$geminiApiKey       = '';
    public static string \$openAiApiKey       = '';
    public static string \$jwtSecret          = '" . bin2hex(random_bytes(32)) . "';
    public static string \$webhookSecret      = '" . bin2hex(random_bytes(32)) . "';

    public static array \$inputMap = [
        'int' => 'number', 'tinyint' => 'number', 'smallint' => 'number', 'bigint' => 'number',
        'decimal' => 'number', 'float' => 'number', 'double' => 'number',
        'varchar' => 'text', 'char' => 'text', 'text' => 'textarea', 'tinytext' => 'textarea', 'mediumtext' => 'textarea', 'longtext' => 'textarea',
        'date' => 'date', 'datetime' => 'datetime-local', 'timestamp' => 'datetime-local', 'time' => 'time',
        'year' => 'number', 'boolean' => 'checkbox', 'enum' => 'select',
    ];

    public static array \$skipInCreate = ['id', 'created_at', 'updated_at'];
    public static array \$skipInEdit   = ['id', 'created_at'];
    public static array \$skipInGrid   = ['text', 'tinytext', 'mediumtext', 'longtext'];
}";

            file_put_contents($configFile, $configContent);

            $_SESSION['install_success'] = true;
            header('Location: ?action=login');
            exit;

        } catch (Throwable $e) {
            $erro = "Erro na instalação: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Instalação do phpgen</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center p-4">
    <div class="card bg-base-100 shadow-xl w-full max-w-lg">
        <div class="card-body">
            <h2 class="card-title text-primary text-2xl font-bold">🚀 Instalação do phpgen</h2>
            <p class="text-sm text-base-content/70 mb-4">Configure seu banco de dados e crie o usuário administrador.</p>

            <?php if ($erro): ?>
                <div class="alert alert-error mb-4"><span><?= htmlspecialchars($erro) ?></span></div>
            <?php endif; ?>

            <form method="post">
                <h3 class="font-bold text-lg mb-2">1. Conexão com o Banco</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Host</span></label>
                        <input type="text" name="host" class="input input-bordered" value="<?= htmlspecialchars($_POST['host'] ?? 'db') ?>" required>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Porta</span></label>
                        <input type="number" name="port" class="input input-bordered" value="<?= htmlspecialchars($_POST['port'] ?? '3306') ?>" required>
                    </div>
                </div>
                <div class="form-control mt-2">
                    <label class="label"><span class="label-text">Nome do Banco (Database)</span></label>
                    <input type="text" name="database" class="input input-bordered" placeholder="ex: meu_sistema" value="<?= htmlspecialchars($_POST['database'] ?? 'test') ?>" required>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-2">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Usuário MySQL</span></label>
                        <input type="text" name="user" class="input input-bordered" value="<?= htmlspecialchars($_POST['user'] ?? 'phpgen') ?>" required>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Senha MySQL</span></label>
                        <input type="password" name="password" class="input input-bordered" value="<?= htmlspecialchars($_POST['password'] ?? 'phpgen') ?>">
                    </div>
                </div>

                <h3 class="font-bold text-lg mt-6 mb-2">2. Usuário Administrador</h3>
                <div class="form-control">
                    <label class="label"><span class="label-text">E-mail</span></label>
                    <input type="email" name="admin_email" class="input input-bordered" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'admin@teste.com') ?>" required>
                </div>
                <div class="form-control mt-2">
                    <label class="label"><span class="label-text">Senha</span></label>
                    <input type="password" name="admin_password" class="input input-bordered" value="<?= htmlspecialchars($_POST['admin_password'] ?? '123456') ?>" required>
                </div>

                <button type="submit" class="btn btn-primary w-full mt-6">Instalar e Acessar</button>
            </form>
        </div>
    </div>
</body>
</html>