<?php
namespace Tests\Integration\Api;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('db')]
class CrudTest extends TestCase
{
    private string $baseUrl;
    private string $jwt = '';

    protected function setUp(): void
    {
        // Dentro do Docker, usa localhost:80 (Apache interno)
        // No host, usa localhost:8080 (Apache do Docker exposto)
        $isDocker = file_exists('/.dockerenv');
        $this->baseUrl = $isDocker ? 'http://localhost' : 'http://localhost:8080';

        // Login para obter JWT
        $ch = curl_init("{$this->baseUrl}/?action=login&format=json");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['email' => 'admin@teste.com', 'senha' => '123456']),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($r['jwt'])) {
            $this->jwt = $r['jwt'];
        } else {
            $this->markTestSkipped('Login falhou — servidor não está respondendo.');
        }
    }

    public function testCriarUsuario(): void
    {
        $body = json_encode([
            'nome' => 'Teste PHPUnit',
            'email' => 'unit' . time() . '@teste.com',
            'senha' => '123456',
            'ativo' => 1,
        ]);

        $ch = curl_init("{$this->baseUrl}/?action=api&table=usuarios");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->jwt}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $this->assertSame('success', $r['status']);
        $this->assertGreaterThan(0, $r['id']);
    }
}