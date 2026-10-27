<?php
namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PhpGen\Database\Introspector;
use PhpGen\Jobs\WebhookProcessor;

#[Group('db')]
class WebhookQueueTest extends TestCase
{
    private Introspector $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Introspector();
        $this->db->ensureWebhookQueueTable();
        
        // Limpa a fila antes de cada teste para evitar interferência
        $this->db->pdo()->exec("DELETE FROM webhook_queue");
    }

    public function testEnqueueWebhook(): void
    {
        $id = $this->db->enqueueWebhook(
            'http://localhost/?action=health',
            '{"test":true}',
            'abc123'
        );
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testProcessWebhook(): void
	{
		// Detecta o ambiente: dentro do Docker = porta 80, host = porta 8080
		$isDocker = file_exists('/.dockerenv');
		$baseUrl = $isDocker ? 'http://localhost' : 'http://localhost:8080';

		// Enfileira um webhook para o health-check (que sempre responde 200)
		$this->db->enqueueWebhook(
			"{$baseUrl}/?action=health",
			'{"test":true}',
			'abc123'
		);

		$processor = new WebhookProcessor($this->db);
		$result = $processor->process(10);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('success', $result);
		$this->assertGreaterThanOrEqual(1, $result['success']);
	}

    public function testDeadLetterForInvalidUrl(): void
	{
		$id = $this->db->enqueueWebhook(
			'http://localhost:9999/invalido',  // ← porta inválida (não muda)
			'{"test":true}',
			'abc123'
		);
        
        // Força max_attempts = 1 para ir direto para dead_letter
        $this->db->pdo()->exec("UPDATE webhook_queue SET max_attempts = 1 WHERE id = $id");
        
        $processor = new WebhookProcessor($this->db);
        $processor->process(10);
        
        $stmt = $this->db->pdo()->prepare("SELECT status FROM webhook_queue WHERE id = ?");
        $stmt->execute([$id]);
        $status = $stmt->fetchColumn();
        
        $this->assertSame('dead_letter', $status);
    }
}