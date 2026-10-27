<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpGen\Database\Introspector;

$db = new Introspector();
$stmt = $db->pdo()->prepare("SELECT id, email, senha FROM usuarios WHERE email = ?");
$stmt->execute(['admin@teste.com']);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    echo "❌ NAO ENCONTRADO\n";
    exit;
}

echo "ID: {$u['id']}\n";
echo "Email: {$u['email']}\n";
echo "Hash: {$u['senha']}\n";
echo "Verifica '123456': " . (password_verify('123456', $u['senha']) ? 'SIM' : 'NAO') . "\n";

// Se não verifica, reseta
if (!password_verify('123456', $u['senha'])) {
    $novoHash = password_hash('123456', PASSWORD_DEFAULT);
    $db->pdo()->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$novoHash, $u['id']]);
    echo "🔁 Hash RESETADO para '123456'\n";
    echo "Novo hash: $novoHash\n";
}