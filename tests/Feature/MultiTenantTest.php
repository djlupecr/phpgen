<?php
namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PhpGen\Database\Introspector;

#[Group('db')]
class MultiTenantTest extends TestCase
{
    private Introspector $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Introspector();
        $this->db->ensureTenantTable();
        
        // Garante que a sessão tenha um tenant_id para o teste
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['tenant_id'] = 1;
    }

    public function testGetTenantId(): void
    {
        $this->assertSame(1, $this->db->getTenantId());
    }

    public function testApplyTenantFilter(): void
    {
        // Garante que a tabela 'usuarios' tem a coluna tenant_id
        // (ela foi adicionada na migração v2.6.0)
        $schema = $this->db->tableSchema('usuarios');
        $cols = array_column($schema, 'name');
        
        if (!in_array('tenant_id', $cols)) {
            $this->markTestSkipped('A tabela usuarios não tem a coluna tenant_id. Rode migrar_v260.php.');
        }

        $filter = $this->db->applyTenantFilter('usuarios');
        $this->assertStringContainsString('tenant_id = 1', $filter);
    }
}