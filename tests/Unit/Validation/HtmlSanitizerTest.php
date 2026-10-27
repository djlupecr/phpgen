<?php
namespace Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use PhpGen\Validation\HtmlSanitizer;

class HtmlSanitizerTest extends TestCase
{
    public function testRemoveScriptTag(): void
    {
        $html = '<p>Olá</p><script>alert("xss")</script>';
        $limpo = HtmlSanitizer::sanitize($html);
        $this->assertStringNotContainsString('<script>', $limpo);
        $this->assertStringContainsString('Olá', $limpo);
    }

    public function testMantemTextoNormal(): void
    {
        $html = '<p>Texto <strong>normal</strong></p>';
        $limpo = HtmlSanitizer::sanitize($html);
        $this->assertStringContainsString('<p>', $limpo);
        $this->assertStringContainsString('<strong>', $limpo);
    }
}
