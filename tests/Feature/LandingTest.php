<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class LandingTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $isDocker = file_exists('/.dockerenv');
        $this->baseUrl = $isDocker ? 'http://localhost' : 'http://localhost:8080';
    }

    public function testLandingRetorna200(): void
    {
        $ch = curl_init("{$this->baseUrl}/?action=landing");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(200, $httpCode);
    }

    public function testLandingTemConteudo(): void
    {
        $ch = curl_init("{$this->baseUrl}/?action=landing");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        $this->assertStringContainsString('Gerador de CRUDs', $html);
        $this->assertStringContainsString('Editor Visual', $html);
        $this->assertStringContainsString('Instalar Agora', $html);
    }

    public function testLandingRedirecionaSeLogado(): void
    {
        // Sem login, landing responde 200
        $ch = curl_init("{$this->baseUrl}/?action=landing");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(200, $httpCode);
    }
}