#!/usr/bin/env python3
"""
setup_phpunit.py — Automação da implementação dos Testes PHPUnit (v2.8.0).

Uso:
    python tools/setup_phpunit.py
"""

import json
import os
import subprocess
import sys
from pathlib import Path

# ============================================================
# CONFIGURAÇÃO
# ============================================================
ROOT = Path(__file__).resolve().parent.parent
DOCKER = True  # Usa Docker (mude para False se estiver em XAMPP)

# ============================================================
# HELPERS
# ============================================================
def log(msg, emoji="ℹ️"):
    print(f"{emoji} {msg}")

def success(msg):
    print(f"✅ {msg}")

def error(msg):
    print(f"❌ {msg}")
    sys.exit(1)

def run(cmd, cwd=None):
    """Executa um comando e retorna o output."""
    try:
        result = subprocess.run(
            cmd, shell=True, cwd=cwd or ROOT,
            capture_output=True, text=True, timeout=300
        )
        if result.returncode != 0:
            return False, result.stderr.strip()
        return True, result.stdout.strip()
    except subprocess.TimeoutExpired:
        return False, "Timeout"
    except Exception as e:
        return False, str(e)

# ============================================================
# 1. CRIAR ESTRUTURA DE PASTAS
# ============================================================
def criar_estrutura():
    log("Criando estrutura de pastas em tests/...")
    pastas = [
        "tests",
        "tests/Unit/Validation",
        "tests/Unit/Database",
        "tests/Unit/Auth",
        "tests/Feature",
        "tests/Integration/Api",
        "tests/Integration/Web",
    ]
    for p in pastas:
        (ROOT / p).mkdir(parents=True, exist_ok=True)
    success("Estrutura criada")

# ============================================================
# 2. CRIAR bootstrap.php
# ============================================================
def criar_bootstrap():
    log("Criando tests/bootstrap.php...")
    content = """<?php
require __DIR__ . '/../vendor/autoload.php';

// Carrega o .env (se existir)
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}

// Define o timezone padrão
date_default_timezone_set('America/Sao_Paulo');
"""
    (ROOT / "tests/bootstrap.php").write_text(content, encoding="utf-8")
    success("bootstrap.php criado")

# ============================================================
# 3. CRIAR phpunit.xml
# ============================================================
def criar_phpunit_xml():
    log("Criando phpunit.xml...")
    content = """<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <coverage>
        <report>
            <html outputDirectory="coverage/"/>
            <text outputFile="coverage.txt"/>
        </report>
    </coverage>
</phpunit>
"""
    (ROOT / "phpunit.xml").write_text(content, encoding="utf-8")
    success("phpunit.xml criado")

# ============================================================
# 4. CRIAR TESTES UNITÁRIOS
# ============================================================
def criar_teste_brvalidator():
    log("Criando tests/Unit/Validation/BrValidatorTest.php...")
    content = """<?php
namespace Tests\\Unit\\Validation;

use PHPUnit\\Framework\\TestCase;
use PhpGen\\Validation\\BrValidator;

class BrValidatorTest extends TestCase
{
    /**
     * @dataProvider cpfProvider
     */
    public function testCpf(string $cpf, bool $esperado): void
    {
        $this->assertSame($esperado, BrValidator::isValidCpf($cpf));
    }

    public static function cpfProvider(): array
    {
        return [
            'válido formatado' => ['529.982.247-25', true],
            'válido sem máscara' => ['52998224725', true],
            'todos dígitos iguais' => ['111.111.111-11', false],
            'inválido' => ['123.456.789-00', false],
        ];
    }

    /**
     * @dataProvider cnpjProvider
     */
    public function testCnpj(string $cnpj, bool $esperado): void
    {
        $this->assertSame($esperado, BrValidator::isValidCnpj($cnpj));
    }

    public static function cnpjProvider(): array
    {
        return [
            'válido formatado' => ['11.222.333/0001-81', true],
            'válido sem máscara' => ['11222333000181', true],
            'inválido' => ['11.222.333/0001-80', false],
        ];
    }

    /**
     * @dataProvider horaProvider
     */
    public function testHora(string $hora, bool $esperado): void
    {
        $this->assertSame($esperado, BrValidator::validateByType('hora', $hora));
    }

    public static function horaProvider(): array
    {
        return [
            'válida' => ['23:59', true],
            'inválida' => ['24:00', false],
            'inválida negativa' => ['-1:00', false],
        ];
    }
}
"""
    (ROOT / "tests/Unit/Validation/BrValidatorTest.php").write_text(content, encoding="utf-8")
    success("BrValidatorTest.php criado")

def criar_teste_htmlsanitizer():
    log("Criando tests/Unit/Validation/HtmlSanitizerTest.php...")
    content = """<?php
namespace Tests\\Unit\\Validation;

use PHPUnit\\Framework\\TestCase;
use PhpGen\\Validation\\HtmlSanitizer;

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
"""
    (ROOT / "tests/Unit/Validation/HtmlSanitizerTest.php").write_text(content, encoding="utf-8")
    success("HtmlSanitizerTest.php criado")

# ============================================================
# 5. CRIAR TESTES DE FEATURE
# ============================================================
def criar_teste_webhookqueue():
    log("Criando tests/Feature/WebhookQueueTest.php...")
    content = """<?php
namespace Tests\\Feature;

use PHPUnit\\Framework\\TestCase;
use PhpGen\\Database\\Introspector;
use PhpGen\\Jobs\\WebhookProcessor;

class WebhookQueueTest extends TestCase
{
    private Introspector $db;

    protected function setUp(): void
    {
        $this->db = new Introspector();
        $this->db->ensureWebhookQueueTable();
        $this->db->pdo()->exec("DELETE FROM webhook_queue");
    }

    public function testEnqueueWebhook(): void
    {
        $id = $this->db->enqueueWebhook(
            'http://localhost/?action=health',
            '{"test":true}',
            'abc123'
        );
        $this->assertGreaterThan(0, $id);
    }

    public function testProcessWebhook(): void
    {
        $this->db->enqueueWebhook(
            'http://localhost/?action=health',
            '{"test":true}',
            'abc123'
        );
        $processor = new WebhookProcessor($this->db);
        $result = $processor->process(10);
        $this->assertGreaterThanOrEqual(1, $result['success']);
    }
}
"""
    (ROOT / "tests/Feature/WebhookQueueTest.php").write_text(content, encoding="utf-8")
    success("WebhookQueueTest.php criado")

def criar_teste_multitenant():
    log("Criando tests/Feature/MultiTenantTest.php...")
    content = """<?php
namespace Tests\\Feature;

use PHPUnit\\Framework\\TestCase;
use PhpGen\\Database\\Introspector;

class MultiTenantTest extends TestCase
{
    private Introspector $db;

    protected function setUp(): void
    {
        $this->db = new Introspector();
        $this->db->ensureTenantTable();
        $_SESSION['tenant_id'] = 1;
    }

    public function testGetTenantId(): void
    {
        $this->assertSame(1, $this->db->getTenantId());
    }

    public function testApplyTenantFilter(): void
    {
        $filter = $this->db->applyTenantFilter('usuarios');
        $this->assertStringContainsString('tenant_id = 1', $filter);
    }
}
"""
    (ROOT / "tests/Feature/MultiTenantTest.php").write_text(content, encoding="utf-8")
    success("MultiTenantTest.php criado")

# ============================================================
# 6. ATUALIZAR composer.json
# ============================================================
def atualizar_composer():
    log("Atualizando composer.json...")
    composer_path = ROOT / "composer.json"
    composer = json.loads(composer_path.read_text(encoding="utf-8"))

    # require-dev
    composer.setdefault("require-dev", {})
    composer["require-dev"]["phpunit/phpunit"] = "^10.0"

    # scripts
    composer.setdefault("scripts", {})
    composer["scripts"]["test"] = "phpunit"
    composer["scripts"]["test:unit"] = "phpunit --testsuite Unit"
    composer["scripts"]["test:feature"] = "phpunit --testsuite Feature"
    composer["scripts"]["test:integration"] = "phpunit --testsuite Integration"
    composer["scripts"]["test:coverage"] = "phpunit --coverage-html coverage/"

    composer_path.write_text(json.dumps(composer, indent=4, ensure_ascii=False), encoding="utf-8")
    success("composer.json atualizado")

# ============================================================
# 7. ATUALIZAR GITHUB ACTIONS
# ============================================================
def atualizar_github_actions():
    log("Atualizando .github/workflows/ci.yml...")
    ci_path = ROOT / ".github/workflows/ci.yml"
    ci_path.parent.mkdir(parents=True, exist_ok=True)
    content = """name: CI

on:
  push:
    branches: [main, master]
  pull_request:
    branches: [main, master]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.2]

    services:
      mysql:
        image: mariadb:11
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test
          MYSQL_USER: phpgen
          MYSQL_PASSWORD: phpgen
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mariadb-admin ping -h 127.0.0.1 -uroot -proot"
          --health-interval=5s
          --health-timeout=5s
          --health-retries=10

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo, pdo_mysql, curl, zip, mbstring, gd
          coverage: xdebug

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Validate composer.json
        run: composer validate --strict

      - name: Lint PHP
        run: find src public tools -name "*.php" -print0 | xargs -0 -n1 php -l

      - name: Autoload
        run: composer dump-autoload --optimize --classmap-authoritative

      - name: Config para CI
        run: |
          cp src/Config.php src/Config.php.bak
          sed -i "s/'host'     => 'localhost'/'host'     => '127.0.0.1'/" src/Config.php
          sed -i "s/'user'     => 'root'/'user'     => 'phpgen'/" src/Config.php
          sed -i "s/'password' => ''/'password' => 'phpgen'/" src/Config.php

      - name: Criar tabelas
        run: php tools/criar_tabelas.php || true

      - name: Run PHPUnit
        run: vendor/bin/phpunit --coverage-text

      - name: Restore Config
        run: mv src/Config.php.bak src/Config.php
"""
    ci_path.write_text(content, encoding="utf-8")
    success("GitHub Actions atualizado")

# ============================================================
# 8. EXECUTAR COMANDOS
# ============================================================
def executar_composer_update():
    log("Rodando composer update...")
    if DOCKER:
        ok, out = run("docker compose exec app composer update --no-interaction")
    else:
        ok, out = run("composer update --no-interaction")
    if not ok:
        error(f"Falha no composer update: {out}")
    success("composer update OK")

def executar_phpunit():
    log("Rodando PHPUnit...")
    if DOCKER:
        ok, out = run("docker compose exec app vendor/bin/phpunit --coverage-text")
    else:
        ok, out = run("vendor/bin/phpunit --coverage-text")
    print(out)
    if not ok:
        print("⚠️  PHPUnit falhou (alguns testes podem ter dependências externas)")
    else:
        success("PHPUnit OK")

# ============================================================
# MAIN
# ============================================================
def main():
    print("\n" + "=" * 60)
    print("  PHPGEN — Setup PHPUnit (v2.8.0)")
    print("=" * 60 + "\n")

    if not (ROOT / "composer.json").exists():
        error("composer.json não encontrado. Rode o script na raiz do projeto.")

    criar_estrutura()
    criar_bootstrap()
    criar_phpunit_xml()
    criar_teste_brvalidator()
    criar_teste_htmlsanitizer()
    criar_teste_webhookqueue()
    criar_teste_multitenant()
    atualizar_composer()
    atualizar_github_actions()

    print("\n" + "=" * 60)
    print("  Arquivos criados/atualizados com sucesso!")
    print("=" * 60 + "\n")

    executar_composer_update()
    executar_phpunit()

    print("\n" + "=" * 60)
    print("  ✅ SETUP CONCLUÍDO!")
    print("=" * 60)
    print("\nPróximos passos:")
    print("  1. Rodar: composer test")
    print("  2. Ver cobertura: coverage/index.html")
    print("  3. Fazer commit e push (CI/CD vai rodar PHPUnit)")
    print()

if __name__ == "__main__":
    main()