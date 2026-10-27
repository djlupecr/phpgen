<#
.SYNOPSIS
    Suite de testes da API REST com JWT.
.DESCRIPTION
    Testa: login, CRUD via JWT, revogação de token (Denylist), Rate Limit e exportações.
.EXAMPLE
    .\tools\testar_api_jwt.ps1
#>

param(
    [string]$BaseUrl = "http://localhost:8080",
    [string]$Email   = "admin@teste.com",
    [string]$Senha   = "123456"
)

$ErrorActionPreference = 'Continue'
$ok = 0; $fail = 0
$jwt = $null

function Test-Item {
    param([string]$Nome, [scriptblock]$Bloco)
    try {
        $resultado = & $Bloco
        if ($resultado) {
            Write-Host "  [OK]   $Nome" -ForegroundColor Green
            $script:ok++
        } else {
            Write-Host "  [FAIL] $Nome" -ForegroundColor Red
            $script:fail++
        }
    } catch {
        Write-Host "  [FAIL] $Nome - $($_.Exception.Message)" -ForegroundColor Red
        $script:fail++
    }
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  PHPGEN - Suite de Testes da API (Integração)" -ForegroundColor Cyan
Write-Host "  URL: $BaseUrl" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Health-check
Write-Host "Health-check:" -ForegroundColor Yellow
Test-Item "health responde com JSON" {
    $r = curl.exe -s "$BaseUrl/?action=health"
    ($r | ConvertFrom-Json).status -ne $null
}
Write-Host ""

# 2. Login JWT
Write-Host "Autenticação (JWT):" -ForegroundColor Yellow
Test-Item "login retorna sucesso e JWT" {
    $body = @{email=$Email; senha=$Senha} | ConvertTo-Json
    $r = Invoke-RestMethod -Uri "$BaseUrl/?action=login&format=json" -Method POST -Body $body -ContentType "application/json"
    if ($r.status -eq 'success' -and ![string]::IsNullOrEmpty($r.jwt)) {
        $script:jwt = $r.jwt
        $true
    } else { $false }
}
Write-Host ""

# 3. CRUD via API
if ($script:jwt) {
    Write-Host "CRUD via API REST:" -ForegroundColor Yellow
    $h = @{Authorization="Bearer $script:jwt"}
    $novoId = $null

    Test-Item "GET lista de usuários" {
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios" -Headers $h -ErrorAction Stop
        $r.status -eq 'success' -and $r.data -ne $null
    }

    Test-Item "POST cria novo usuário" {
        $body = @{nome="Teste CI $(Get-Random)"; email="ci$(Get-Random)@teste.com"; senha="123456"; ativo=1} | ConvertTo-Json
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios" -Method POST -Headers $h -Body $body -ContentType "application/json" -ErrorAction Stop
        if ($r.status -eq 'success' -and $r.id -gt 0) {
            $script:novoId = $r.id
            $true
        } else { $false }
    }

    Test-Item "PUT atualiza o usuário criado" {
        if (!$script:novoId) { return $false }
        $body = @{nome="Teste CI Atualizado"} | ConvertTo-Json
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios&id=$($script:novoId)" -Method PUT -Headers $h -Body $body -ContentType "application/json" -ErrorAction Stop
        $r.status -eq 'success'
    }

    Test-Item "DELETE (soft delete) remove o usuário" {
        if (!$script:novoId) { return $false }
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios&id=$($script:novoId)" -Method DELETE -Headers $h -ErrorAction Stop
        $r.status -eq 'success'
    }
    Write-Host ""
}

# 4. Segurança: Revogação de JWT (Denylist)
if ($script:jwt) {
    Write-Host "Segurança (Denylist JWT):" -ForegroundColor Yellow
    $h = @{Authorization="Bearer $script:jwt"}

    Test-Item "Token funciona antes da revogação" {
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios" -Headers $h -ErrorAction Stop
        $r.status -eq 'success'
    }

    Test-Item "Rota de logout_api revoga o token" {
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=logout_api" -Method POST -Headers $h -ErrorAction Stop
        $r.status -eq 'success'
    }

    Test-Item "Token é rejeitado após revogação (401)" {
        try {
            Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios" -Headers $h -ErrorAction Stop | Out-Null
            $false
        } catch {
            $_.Exception.Response.StatusCode.value__ -eq 401
        }
    }
    Write-Host ""
}

# 5. Rate Limit (Força Bruta)
Write-Host "Segurança (Rate Limit):" -ForegroundColor Yellow
Test-Item "Bloqueia após 5 tentativas de login inválidas" {
    $bloqueado = $false
    # Usamos um IP fictício para não bloquear o localhost real
    $headers = @{ "X-Forwarded-For" = "1.2.3.4" }
    
    for ($i = 1; $i -le 6; $i++) {
        try {
            $body = @{email="hacker@teste.com"; senha="errada"} | ConvertTo-Json
            $r = Invoke-RestMethod -Uri "$BaseUrl/?action=login&format=json" -Method POST -Body $body -ContentType "application/json" -Headers $headers -ErrorAction Stop
        } catch {
            # O PHP retorna 429 (Too Many Requests) ou a mensagem "Muitas tentativas"
            if ($_.Exception.Response.StatusCode.value__ -eq 429 -or $_.ErrorDetails.Message -match "Muitas tentativas") {
                $bloqueado = $true
                break
            }
        }
    }
    $bloqueado
}
Write-Host ""

# 6. Exportações (CSV)
Write-Host "Exportações:" -ForegroundColor Yellow
if ($script:jwt) {
    # Precisamos de uma sessão logada para exportar (não usa JWT, usa Cookie)
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    try {
        $body = @{email=$Email; senha=$Senha}
        Invoke-WebRequest -Uri "$BaseUrl/?action=login" -Method POST -Body $body -WebSession $session -UseBasicParsing -ErrorAction Stop | Out-Null
        
        Test-Item "Exportação CSV gera arquivo" {
            $r = Invoke-WebRequest -Uri "$BaseUrl/?action=export&table=usuarios" -WebSession $session -UseBasicParsing -ErrorAction Stop
            $r.StatusCode -eq 200 -and $r.RawContentLength -gt 0
        }
    } catch {}
}
Write-Host ""

# 7. Limpeza do Rate Limit
# Para não bloquear o IP do host para sempre, limpamos a tabela via Docker
Write-Host "Limpando Rate Limit..." -ForegroundColor DarkGray
try {
    docker compose exec app php -r "require 'vendor/autoload.php'; `$db = new PhpGen\Database\Introspector(); `$db->pdo()->exec('DELETE FROM rate_limit'); echo 'Limpo!' . PHP_EOL;"
} catch {
    Write-Host "  (Aviso: Não foi possível limpar a tabela rate_limit via Docker)" -ForegroundColor Yellow
}
Write-Host ""

# Resumo
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  RESUMO: OK=$ok | FALHAS=$fail" -ForegroundColor $(if ($fail -eq 0) { 'Green' } else { 'Red' })
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

exit $(if ($fail -eq 0) { 0 } else { 1 })