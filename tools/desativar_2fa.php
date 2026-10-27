<?php
require __DIR__ . '/../vendor/autoload.php';
$db = new PhpGen\Database\Introspector();
$db->pdo()->exec("DELETE FROM user_2fa WHERE user_id = (SELECT id FROM usuarios WHERE email = 'admin@teste.com')");
echo "2FA desativado.\n";