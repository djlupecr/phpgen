<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpGen\Database\Introspector;

// Uso: php promover_por_email.php <email> <role>
// Exemplo: php promover_por_email.php admin@teste.com admin

$email = $argv[1] ?? 'admin@teste.com';
$role  = $argv[2] ?? 'admin';

$db = new Introspector();
$db->ensureRbacTables();

// Achar o usuário
$stmt = $db->pdo()->prepare("SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "❌ Usuário '$email' não encontrado.\n";
    exit(1);
}

// Achar o papel
$stmtR = $db->pdo()->prepare("SELECT id FROM roles WHERE slug = ? LIMIT 1");
$stmtR->execute([$role]);
$roleId = (int)$stmtR->fetchColumn();

if (!$roleId) {
    echo "❌ Papel '$role' não existe. Use 'admin', 'editor' ou 'viewer'.\n";
    exit(1);
}

// Remove papéis antigos e adiciona o novo
$db->pdo()->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$user['id']]);
$db->pdo()->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$user['id'], $roleId]);

echo "✅ Usuário '{$user['nome']}' (ID={$user['id']}) agora é '$role'\n";
echo "   Roles: " . implode(', ', $db->getUserRoles((int)$user['id'])) . "\n";