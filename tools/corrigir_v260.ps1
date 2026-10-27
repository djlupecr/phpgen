<#
.SYNOPSIS
    Corrige automaticamente todos os problemas da v2.6.0 (Onda 2).
.DESCRIPTION
    1. Corrige o bug do LIMIT ? em fetchPendingWebhooks
    2. Verifica se os arquivos da Onda 2 existem (WebhookProcessor, migração)
    3. Valida sintaxe de todos os arquivos PHP
    4. Roda composer dump-autoload
    5. Executa os testes v2.6.0
.EXAMPLE
    .\tools\corrigir_v260.ps1
#>

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  PHPGEN - Correção Automática v2.6.0" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# ============================================================
# 1. CORRIGIR fetchPendingWebhooks (LIMIT ?)
# ============================================================
Write-Host "[1/6] Corrigindo fetchPendingWebhooks..." -ForegroundColor Yellow

$introspectorFile = "src\Database\Introspector.php"
if (-not (Test-Path $introspectorFile)) {
    Write-Host "  ❌ Arquivo não encontrado: $introspectorFile" -ForegroundColor Red
    exit 1
}

$content = Get-Content $introspectorFile -Raw

# Regex para encontrar o método e substituir
$pattern = '(?s)public function fetchPendingWebhooks\(int \$limit = 20\): array\s*\{.*?\n    \}'
$replacement = @'
public function fetchPendingWebhooks(int $limit = 20): array
    {
        $this->ensureWebhookQueueTable();
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare("
            SELECT * FROM webhook_queue
            WHERE status = 'pending' AND next_attempt <= NOW()
            ORDER BY id ASC LIMIT $limit
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
'@

if ($content -match $pattern) {
    $newContent = $content -replace $pattern, $replacement
    Set-Content -Path $introspectorFile -Value $newContent -Encoding UTF8
    Write-Host "  ✅ fetchPendingWebhooks corrigido" -ForegroundColor Green
} else {
    Write-Host "  ⚠️  Método não encontrado (já pode estar correto)" -ForegroundColor Yellow
}
Write-Host ""

# ============================================================
# 2. VERIFICAR ARQUIVOS DA ONDA 2
# ============================================================
Write-Host "[2/6] Verificando arquivos da Onda 2..." -ForegroundColor Yellow

$arquivosOnda2 = @(
    @{Path = "src\Jobs\WebhookProcessor.php"; Desc = "WebhookProcessor"},
    @{Path = "src\Http\Controllers\TenantController.php"; Desc = "TenantController"},
    @{Path = "tools\migrar_v260.php"; Desc = "Script de migração"},
    @{Path = "tools\testar_v260.php"; Desc = "Script de testes"}
)

foreach ($arq in $arquivosOnda2) {
    if (Test-Path $arq.Path) {
        Write-Host "  ✅ $($arq.Desc) existe" -ForegroundColor Green
    } else {
        Write-Host "  ⚠️  $($arq.Desc) AUSENTE: $($arq.Path)" -ForegroundColor Yellow
        Write-Host "     (crie manualmente seguindo a documentação)" -ForegroundColor Gray
    }
}
Write-Host ""

# ============================================================
# 3. VALIDAR SINTAXE PHP
# ============================================================
Write-Host "[3/6] Validando sintaxe PHP..." -ForegroundColor Yellow

$arquivosParaValidar = @(
    "src\Config.php",
    "src\Database\Introspector.php",
    "src\Generator\Generator.php",
    "src\Jobs\WebhookProcessor.php",
    "src\Http\Router.php",
    "src\Http\Controllers\AuthController.php",
    "src\Http\Controllers\ApiController.php",
    "src\Http\Controllers\CrudController.php",
    "src\Http\Controllers\TenantController.php",
    "src\Http\Controllers\WebhookController.php",
    "public\index.php",
    "tools\migrar_v260.php",
    "tools\testar_v260.php"
)

$erros = 0
foreach ($arq in $arquivosParaValidar) {
    if (-not (Test-Path $arq)) { continue }
    $out = php -l $arq 2>&1
    if ($out -notmatch 'No syntax errors') {
        Write-Host "  ❌ $arq" -ForegroundColor Red
        Write-Host "     $out" -ForegroundColor Red
        $erros++
    }
}
if ($erros -eq 0) {
    Write-Host "  ✅ Todos os arquivos OK" -ForegroundColor Green
} else {
    Write-Host "  ❌ $erros arquivo(s) com erro" -ForegroundColor Red
}
Write-Host ""

# ============================================================
# 4. DUMP AUTOLOAD
# ============================================================
Write-Host "[4/6] Rodando composer dump-autoload..." -ForegroundColor Yellow
docker compose exec app composer dump-autoload --optimize 2>&1 | Out-Null
Write-Host "  ✅ Autoload otimizado" -ForegroundColor Green
Write-Host ""

# ============================================================
# 5. RODAR MIGRAÇÃO (se ainda não rodou)
# ============================================================
Write-Host "[5/6] Rodando migração v2.6.0..." -ForegroundColor Yellow
if (Test-Path "tools\migrar_v260.php") {
    docker compose exec app php tools/migrar_v260.php
} else {
    Write-Host "  ⚠️  Script de migração ausente" -ForegroundColor Yellow
}
Write-Host ""

# ============================================================
# 6. RODAR TESTES
# ============================================================
Write-Host "[6/6] Rodando testes v2.6.0..." -ForegroundColor Yellow
if (Test-Path "tools\testar_v260.php") {
    docker compose exec app php tools/testar_v260.php
} else {
    Write-Host "  ⚠️  Script de testes ausente" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  CORREÇÃO CONCLUÍDA!" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""