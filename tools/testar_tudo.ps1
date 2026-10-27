<#
.SYNOPSIS
    Roda todos os testes do sistema (unitários, integração, auditoria, Onda 2, Editor Visual) no Docker.
#>

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host "`n=== INICIANDO SUÍTE DE TESTES PHPGEN (Docker) ===`n" -ForegroundColor Cyan

# 1. Testes Unitários
Write-Host "1. Testes Unitários (Core)..." -ForegroundColor Yellow
docker compose exec app php tools/testar_sistema.php
if ($LASTEXITCODE -ne 0) { Write-Host "Falha nos testes unitários" -ForegroundColor Red; exit 1 }

# 2. Testes de Validação
Write-Host "`n2. Testes de Validação (18 testes)..." -ForegroundColor Yellow
docker compose exec app php tools/testar_validacao.php
if ($LASTEXITCODE -ne 0) { Write-Host "Falha nos testes de validação" -ForegroundColor Red; exit 1 }

# 3. Auditoria do Sistema
Write-Host "`n3. Auditoria do Sistema..." -ForegroundColor Yellow
docker compose exec app php tools/verificar_sistema.php
if ($LASTEXITCODE -ne 0) { Write-Host "Falha na auditoria" -ForegroundColor Red; exit 1 }

# 4. Testes de Integração (API / JWT / Rate Limit)
Write-Host "`n4. Testes de Integração (API / JWT / Rate Limit)..." -ForegroundColor Yellow
powershell -ExecutionPolicy Bypass -File tools/testar_api_jwt.ps1 -BaseUrl "http://localhost:8080"
if ($LASTEXITCODE -ne 0) { Write-Host "Falha nos testes de integração" -ForegroundColor Red; exit 1 }

# 5. Testes Onda 2 (Fila, Multi-Tenant, Diff)
Write-Host "`n5. Testes v2.6.0 (Onda 2: Fila, Multi-Tenant, Diff)..." -ForegroundColor Yellow
docker compose exec app php tools/testar_v260.php
if ($LASTEXITCODE -ne 0) { Write-Host "Falha nos testes v2.6.0" -ForegroundColor Red; exit 1 }

# 6. Testes Onda 3 (Editor Visual)
Write-Host "`n6. Testes v2.7.0 (Editor Visual)..." -ForegroundColor Yellow
docker compose exec app php tools/testar_v270.php
if ($LASTEXITCODE -ne 0) { Write-Host "Falha nos testes v2.7.0" -ForegroundColor Red; exit 1 }

Write-Host "`n=== TODOS OS TESTES PASSARAM COM SUCESSO! ===" -ForegroundColor Green