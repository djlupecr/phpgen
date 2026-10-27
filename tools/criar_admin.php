<?php
// Cria (ou reseta) um usuário admin para testes.
// Uso: php C:\phpgen\tools\criar_admin.php
require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Database\Introspector;

$email = 'admin@teste.com';
$senha = '123456';
$nome  = 'Admin';

$db   = new Introspector();
$hash = password_hash($senha, PASSWORD_DEFAULT);

// Já existe?
$stmt = $db->pdo()->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existingId = $stmt->fetchColumn();

if ($existingId) {
    $db->pdo()->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")
       ->execute([$hash, $existingId]);
    echo "🔁 Senha do usuário ID $existingId ($email) foi RESETADA para '$senha'.\n";
} else {
    $db->pdo()->prepare("INSERT INTO usuarios (nome, email, senha, ativo) VALUES (?,?,?,1)")
       ->execute([$nome, $email, $hash]);
    echo "✅ Usuário criado. ID = " . $db->pdo()->lastInsertId() . " ($email / $senha)\n";
}