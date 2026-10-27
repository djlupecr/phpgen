<#
.SYNOPSIS
    Deploy completo do phpgen (Docker ou XAMPP).
.DESCRIPTION
    Valida sintaxe, regenera autoload, gera tabelas, roda testes e health-check.
    Detecta automaticamente se o Docker está rodando e executa os comandos no container.
.EXAMPLE
    .\tools\deploy.ps1
#>

param(
    [string[]]$Tabelas = @('usuarios', 'produtos', 'pedidos'),
    [string]$BaseUrl = ""
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

# ============================================================
# Detectar Docker
# ============================================================
$useDocker = $false
try {
    $dockerStatus = docker compose ps 2>&1
    if ($dockerStatus -match 'phpgen-app') {
        $useDocker = $true
    }
} catch {}

if ([string]::IsNullOrEmpty($BaseUrl)) {
    if ($useDocker) { $BaseUrl = "http://localhost:8080" }
    else { $BaseUrl = "http://localhost:8000" }
}

$mode = if ($useDocker) { "Docker" } else { "XAMPP / Local" }

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  PHPGEN - Deploy v2.4.0 ($mode)" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# ============================================================
# 1. Validar sintaxe
# ============================================================
Write-Host "[1/7] Validando sintaxe..." -ForegroundColor Yellow
$files = @(
    'src/Config.php',
    'src/Database/Introspector.php',
    'src/Generator/Generator.php',
    'src/Validation/BrValidator.php',
    'src/Validation/HtmlSanitizer.php',
    'src/Validation/BackupManager.php',
    'public/index.php',
    'public/install.php',
    'tools/gerar_tabelas.php',
    'tools/criar_tabelas.php',
    'tools/verificar_sistema.php'
)
foreach ($f in $files) {
    if (-not (Test-Path $f)) { continue }
    $out = php -l $f 2>&1
    if ($out -notmatch 'No syntax errors') {
        Write-Host "  ERRO em $f" -ForegroundColor Red
        Write-Host $out -ForegroundColor Red
        exit 1
    }
}
Write-Host "  OK" -ForegroundColor Green

# ============================================================
# 2. Composer update (se lock não existir)
# ============================================================
Write-Host "[2/7] Verificando dependências..." -ForegroundColor Yellow
if ($useDocker) {
    docker compose exec app composer install --no-interaction --no-progress 2>&1 | Out-Null
} else {
    if (-not (Test-Path 'composer.lock')) {
        composer install --no-interaction
    }
}
Write-Host "  Dependências OK" -ForegroundColor Green

# ============================================================
# 3. Gerar tabelas via CLI
# ============================================================
Write-Host "[3/7] Gerando tabelas..." -ForegroundColor Yellow
if ($useDocker) {
    docker compose exec app php tools/gerar_tabelas.php --force $Tabelas
} else {
    php tools/gerar_tabelas.php --force $Tabelas
}
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ERRO ao gerar tabelas" -ForegroundColor Red
    exit 1
}

# ============================================================
# 4. Autoload otimizado
# ============================================================
Write-Host "[4/7] Otimizando autoload..." -ForegroundColor Yellow
if ($useDocker) {
    docker compose exec app composer dump-autoload --optimize --classmap-authoritative 2>&1 | Out-Null
} else {
    composer dump-autoload --optimize --classmap-authoritative 2>&1 | Out-Null
}
Write-Host "  OK" -ForegroundColor Green

# ============================================================
# 5. Testes de Validação
# ============================================================
Write-Host "[5/7] Rodando testes de validação..." -ForegroundColor Yellow
if ($useDocker) {
    docker compose exec app php tools/testar_validacao.php
} else {
    php tools/testar_validacao.php
}
if ($LASTEXITCODE -ne 0) { Write-Host "  Testes de validação falharam" -ForegroundColor Red; exit 1 }

# ============================================================
# 6. Auditoria do Sistema
# ============================================================
Write-Host "[6/7] Auditoria do sistema..." -ForegroundColor Yellow
if ($useDocker) {
    docker compose exec app php tools/verificar_sistema.php
} else {
    php tools/verificar_sistema.php
}
if ($LASTEXITCODE -ne 0) { Write-Host "  Auditoria falhou" -ForegroundColor Red; exit 1 }

# ============================================================
# 7. Health-check
# ============================================================
Write-Host "[7/7] Health-check..." -ForegroundColor Yellow
try {
    $h = Invoke-RestMethod -Uri "$BaseUrl/?action=health" -TimeoutSec 5
    if ($h.status -eq 'ok') {
        Write-Host "  Sistema saudável" -ForegroundColor Green
    } else {
        Write-Host "  Status: $($h.status)" -ForegroundColor Yellow
        $h.checks.PSObject.Properties | ForEach-Object {
            if (-not $_.Value.ok) {
                Write-Host "    FALHA: $($_.Name) - $($_.Value.detail)" -ForegroundColor Red
            }
        }
    }
} catch {
    Write-Host "  Não foi possível contatar o servidor em $BaseUrl (está rodando?)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  DEPLOY CONCLUIDO" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""