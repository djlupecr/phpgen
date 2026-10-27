<?php
/**
 * migrar_v260.php — Cria as tabelas da v2.6.0 (Onda 2).
 * Uso: docker compose exec app php tools/migrar_v260.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Database\Introspector;

$db = new Introspector();

echo "============================================================\n";
echo "  PHPGEN — Migração v2.6.0 (Onda 2)\n";
echo "============================================================\n\n";

echo "1. Criando tabela `webhook_queue`...\n";
$db->ensureWebhookQueueTable();
echo "   ✅ OK\n\n";

echo "2. Criando tabela `tenants`...\n";
$db->ensureTenantTable();
echo "   ✅ OK\n\n";

echo "3. Adicionando coluna `details_json` em `audit_logs`...\n";
try {
    $db->pdo()->query("SELECT details_json FROM audit_logs LIMIT 1");
    echo "   ✅ Já existe\n";
} catch (\Throwable $e) {
    try {
        $db->pdo()->exec("ALTER TABLE audit_logs ADD COLUMN details_json JSON NULL AFTER details");
        $db->pdo()->exec("ALTER TABLE audit_logs ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address");
        echo "   ✅ Colunas adicionadas\n";
    } catch (\Throwable $e2) {
        echo "   ⚠️  " . $e2->getMessage() . "\n";
    }
}
echo "\n";

echo "4. Adicionando `tenant_id` em `usuarios` (se não existir)...\n";
try {
    $db->pdo()->query("SELECT tenant_id FROM usuarios LIMIT 1");
    echo "   ✅ Já existe\n";
} catch (\Throwable $e) {
    try {
        $db->pdo()->exec("ALTER TABLE usuarios ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id");
        $db->pdo()->exec("CREATE INDEX idx_tenant ON usuarios (tenant_id)");
        echo "   ✅ Coluna adicionada\n";
    } catch (\Throwable $e2) {
        echo "   ⚠️  " . $e2->getMessage() . "\n";
    }
}
echo "\n";

echo "============================================================\n";
echo "  MIGRAÇÃO CONCLUÍDA!\n";
echo "============================================================\n";