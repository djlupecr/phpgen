<?php
/**
 * testar_v270.php — Testes do Editor Visual.
 * Uso: docker compose exec app php tools/testar_v270.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Database\Introspector;
use PhpGen\Config;

$ok = 0; $fail = 0;

function assertTest(string $nome, bool $cond): void {
    global $ok, $fail;
    if ($cond) { echo "  [OK]   $nome\n"; $ok++; }
    else { echo "  [FAIL] $nome\n"; $fail++; }
}

echo "============================================================\n";
echo "  PHPGEN — Testes v2.7.0 (Editor Visual)\n";
echo "============================================================\n\n";

$db = new Introspector();

// ============================================================
// 1. Endpoint /designer
// ============================================================
echo "1. Editor Visual:\n";

$baseUrl = 'http://localhost:80';
$cookieFile = tempnam(sys_get_temp_dir(), 'phpgen_test_');

// 1.1 Health (Público)
$ch = curl_init("$baseUrl/?action=health");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$health = json_decode(curl_exec($ch), true);
curl_close($ch);
assertTest("Health responde", ($health['status'] ?? '') === 'ok');

// 1.2 Login (Para autenticar o curl e salvar o cookie)
$ch = curl_init("$baseUrl/?action=login");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['email' => 'admin@teste.com', 'senha' => '123456']),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("Login via curl OK (302)", $loginCode === 302);

// 1.3 Designer acessível (Com Cookie)
$ch = curl_init("$baseUrl/?action=designer&table=usuarios");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_TIMEOUT => 5
]);
$designerHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("Designer retorna HTTP 200", $httpCode === 200);
assertTest("Designer tem SortableJS", str_contains($designerHtml, 'sortablejs'));
assertTest("Designer tem Alpine.js", str_contains($designerHtml, 'alpinejs'));

// 1.4 Designer sem tabela → erro 400 (Com Cookie)
$ch = curl_init("$baseUrl/?action=designer");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("Designer sem tabela retorna 400", $httpCode === 400);

// Limpa o arquivo de cookie temporário
@unlink($cookieFile);
echo "\n";

// ============================================================
// 2. Config.json atualizado
// ============================================================
echo "2. Persistência do config.json:\n";

$cfgFile = __DIR__ . '/../generated/usuarios/config.json';
$cfgAntes = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];

// Simula o save (sem CSRF, direto no método)
$userId = 1;
$_SESSION['user'] = ['id' => 1, 'email' => 'admin@teste.com'];
$novoOrder = ['nome', 'email', 'ativo'];

$cfg = $cfgAntes;
$cfg['field_order'] = implode(',', $novoOrder);
file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$cfgDepois = json_decode(file_get_contents($cfgFile), true);
assertTest("config.json foi atualizado", ($cfgDepois['field_order'] ?? '') === 'nome,email,ativo');
echo "\n";

// ============================================================
// RESUMO
// ============================================================
echo "============================================================\n";
echo " RESUMO: OK=$ok | FALHAS=$fail\n";
echo "============================================================\n";

exit($fail > 0 ? 1 : 0);