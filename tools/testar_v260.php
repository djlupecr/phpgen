<?php
/**
 * testar_v260.php — Testes das novas features da Onda 2.
 * Uso: docker compose exec app php tools/testar_v260.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Database\Introspector;
use PhpGen\Jobs\WebhookProcessor;
use PhpGen\Config;

$ok = 0; $fail = 0;

function assertTest(string $nome, bool $cond): void {
    global $ok, $fail;
    if ($cond) {
        echo "  [OK]   $nome\n";
        $ok++;
    } else {
        echo "  [FAIL] $nome\n";
        $fail++;
    }
}

echo "============================================================\n";
echo "  PHPGEN — Testes v2.6.0 (Onda 2)\n";
echo "============================================================\n\n";

$db = new Introspector();

// ============================================================
// 1. FILA DE WEBHOOKS
// ============================================================
echo "1. Fila Persistente de Webhooks:\n";

// Limpa a fila antes do teste
$db->ensureWebhookQueueTable();
$db->pdo()->exec("DELETE FROM webhook_queue");

$url = 'http://localhost:80/?action=health'; // URL que sempre responde
$payload = json_encode(['test' => true, 'time' => date('c')]);
$signature = hash_hmac('sha256', $payload, Config::$webhookSecret);

$id = $db->enqueueWebhook($url, $payload, $signature);
assertTest("Enfileirou webhook (id=$id)", $id > 0);

$jobs = $db->fetchPendingWebhooks(10);
assertTest("Fila tem 1 job pendente", count($jobs) === 1);

$processor = new WebhookProcessor($db);
$result = $processor->process(10);
assertTest("Processou o webhook com sucesso", $result['success'] >= 1);

// Testa dead letter (URL inválida)
$badId = $db->enqueueWebhook('http://localhost:9999/invalido', $payload, $signature);
$db->pdo()->exec("UPDATE webhook_queue SET max_attempts = 1 WHERE id = $badId");
$processor->process(10);
$stmt = $db->pdo()->prepare("SELECT status FROM webhook_queue WHERE id = ?");
$stmt->execute([$badId]);
assertTest("Webhook inválido foi para dead_letter", $stmt->fetchColumn() === 'dead_letter');

// Testa recuperação de stuck (processing > 5min)
$db->pdo()->exec("UPDATE webhook_queue SET status = 'processing', last_attempt = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = $badId");
$recovered = $db->recoverStuckWebhooks(5);
assertTest("Recuperou webhooks stuck", $recovered >= 1);
echo "\n";

// ============================================================
// 2. MULTI-TENANT
// ============================================================
echo "2. Multi-Tenant:\n";

$db->ensureTenantTable();
$stmt = $db->pdo()->query("SELECT COUNT(*) FROM tenants");
assertTest("Tabela tenants tem ao menos 1 tenant (default)", (int)$stmt->fetchColumn() >= 1);

$_SESSION['tenant_id'] = 1;
assertTest("getTenantId() retorna 1", $db->getTenantId() === 1);

// Testa isolamento
$hasTenant = in_array('tenant_id', array_column($db->tableSchema('usuarios'), 'name'));
assertTest("Tabela usuarios tem coluna tenant_id", $hasTenant);

if ($hasTenant) {
    $filter = $db->applyTenantFilter('usuarios');
    assertTest("applyTenantFilter retorna filtro válido", str_contains($filter, 'tenant_id = 1'));
}
echo "\n";

// ============================================================
// 3. AUDITORIA COM DIFF
// ============================================================
echo "3. Auditoria com Diff:\n";

// Verifica se as colunas existem
$schema = $db->tableSchema('audit_logs');
$cols = array_column($schema, 'name');
assertTest("Coluna details_json existe", in_array('details_json', $cols));
assertTest("Coluna user_agent existe", in_array('user_agent', $cols));

// Testa o logChange
$before = ['nome' => 'João', 'email' => 'joao@teste.com', 'ativo' => 1];
$after  = ['nome' => 'João Silva', 'email' => 'joao@teste.com', 'ativo' => 0];

$db->logChange(1, 'UPDATE', 'usuarios', 1, $before, $after);

$stmt = $db->pdo()->query("SELECT details_json FROM audit_logs WHERE action = 'UPDATE' ORDER BY id DESC LIMIT 1");
$json = $stmt->fetchColumn();
$diff = json_decode($json, true);

assertTest("Diff contém alteração em 'nome'", isset($diff['nome']));
assertTest("Diff contém alteração em 'ativo'", isset($diff['ativo']));
assertTest("Diff NÃO contém campo inalterado 'email'", !isset($diff['email']));
assertTest("Diff de 'nome' tem old='João'", ($diff['nome']['old'] ?? '') === 'João');
assertTest("Diff de 'nome' tem new='João Silva'", ($diff['nome']['new'] ?? '') === 'João Silva');
echo "\n";

// ============================================================
// RESUMO
// ============================================================
echo "============================================================\n";
echo " RESUMO: OK=$ok | FALHAS=$fail\n";
echo "============================================================\n";

exit($fail > 0 ? 1 : 0);