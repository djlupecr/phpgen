<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpGen\Database\Introspector;

$userId = 8;
$db = new Introspector();
$db->ensureRbacTables();

// Remove todos os papéis atuais
$db->pdo()->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);

// Adiciona admin
$stmt = $db->pdo()->prepare("
    INSERT INTO user_roles (user_id, role_id)
    SELECT ?, id FROM roles WHERE slug = 'admin'
");
$stmt->execute([$userId]);

echo "✅ User $userId promovido a admin\n";
echo "Roles: " . implode(', ', $db->getUserRoles($userId)) . "\n";