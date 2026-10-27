param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$Email   = "admin@teste.com",
    [string]$Senha   = "123456",
    [string]$Tabela  = "usuarios"
)

$ErrorActionPreference = "Stop"
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# Helper: normaliza resposta (remove BOM, converte string em objeto)
function Normalize-Json($obj) {
    if ($obj -is [string]) {
        # Remove BOM
        $obj = $obj.TrimStart([char]0xFEFF)
        try { return $obj | ConvertFrom-Json } catch { return $obj }
    }
    return $obj
}

function Test-Step($desc, [scriptblock]$block) {
    Write-Host ""
    Write-Host "-- $desc --" -ForegroundColor Cyan
    try { & $block } catch {
        Write-Host "FALHOU: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
            Write-Host "   HTTP $([int]$_.Exception.Response.StatusCode)" -ForegroundColor Yellow
        }
    }
}

# 1. LOGIN
Test-Step "1. Login (JSON)" {
    $body = @{ email = $Email; senha = $Senha } | ConvertTo-Json
    $r = Invoke-RestMethod -Uri "$BaseUrl/?action=login" -Method POST `
         -Body $body -ContentType "application/json" `
         -WebSession $session -UseBasicParsing
    $r = Normalize-Json $r
    if ($r.status -ne "success") { throw "Login falhou: $($r | ConvertTo-Json -Compress)" }
    Write-Host "OK Logado como $($r.user.email) (id=$($r.user.id))" -ForegroundColor Green
    $global:userId = $r.user.id
}

if (-not $session.Cookies.GetCookies($BaseUrl).Count) {
    Write-Host "Sem cookie de sessao. Abortando." -ForegroundColor Red
    return
}

# 2. LISTAR
Test-Step "2. GET lista" {
    $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela&limit=3" `
         -WebSession $session -UseBasicParsing
    $r = Normalize-Json $r
    Write-Host "OK status=$($r.status) total=$($r.total)" -ForegroundColor Green
}

# 3. CRIAR
Test-Step "3. POST criar" {
    $email = "smoke$(Get-Random -Max 999999)@x.com"
    $body  = @{ nome = "Smoke Test"; email = $email; senha = "abc12345" } | ConvertTo-Json
    $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela" -Method POST `
         -Body $body -ContentType "application/json" `
         -WebSession $session -UseBasicParsing
    $r = Normalize-Json $r
    if ($r.status -ne "success") { throw "POST: $($r | ConvertTo-Json -Compress)" }
    Write-Host "OK Criado id=$($r.id) ($email)" -ForegroundColor Green
    $global:novoId = $r.id
}

# 4. LER
if ($global:novoId) {
    Test-Step "4. GET id=$($global:novoId)" {
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela&id=$($global:novoId)" `
             -WebSession $session -UseBasicParsing
        $r = Normalize-Json $r
        Write-Host "OK Lido: $($r.data.nome)" -ForegroundColor Green
    }
}

# 5. ATUALIZAR
if ($global:novoId) {
    Test-Step "5. PUT atualizar" {
        $body = @{ nome = "Atualizado via PUT" } | ConvertTo-Json
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela&id=$($global:novoId)" -Method PUT `
             -Body $body -ContentType "application/json" `
             -WebSession $session -UseBasicParsing
        $r = Normalize-Json $r
        Write-Host "OK status=$($r.status)" -ForegroundColor Green
    }
}

# 6. VALIDAR 422
Test-Step "6. POST sem obrigatorio (espera 422)" {
    try {
        $body = @{ nome = "So nome" } | ConvertTo-Json
        Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela" -Method POST `
          -Body $body -ContentType "application/json" `
          -WebSession $session -UseBasicParsing | Out-Null
        Write-Host "AVISO: nao retornou 422" -ForegroundColor Yellow
    } catch {
        $code = [int]$_.Exception.Response.StatusCode
        if ($code -eq 422) { Write-Host "OK 422" -ForegroundColor Green }
        else { Write-Host "AVISO: retornou $code" -ForegroundColor Yellow }
    }
}

# 7. VALIDAR 404 tabela invalida
Test-Step "7. GET tabela invalida (espera 404)" {
    try {
        Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=usuarios;DROP" `
          -WebSession $session -UseBasicParsing | Out-Null
        Write-Host "ERRO: deveria ser 404!" -ForegroundColor Red
    } catch {
        $code = [int]$_.Exception.Response.StatusCode
        if ($code -eq 404) { Write-Host "OK 404" -ForegroundColor Green }
        else { Write-Host "AVISO: $code" -ForegroundColor Yellow }
    }
}

# 8. SOFT DELETE
if ($global:novoId) {
    Test-Step "8. DELETE (soft)" {
        $r = Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela&id=$($global:novoId)" `
             -Method DELETE -WebSession $session -UseBasicParsing
        $r = Normalize-Json $r
        Write-Host "OK status=$($r.status)" -ForegroundColor Green
    }

    Test-Step "9. GET do excluido (espera 404)" {
        try {
            Invoke-RestMethod -Uri "$BaseUrl/?action=api&table=$Tabela&id=$($global:novoId)" `
              -WebSession $session -UseBasicParsing | Out-Null
            Write-Host "ERRO: ainda acessivel!" -ForegroundColor Red
        } catch {
            $code = [int]$_.Exception.Response.StatusCode
            if ($code -eq 404) { Write-Host "OK 404 (soft delete funcionou)" -ForegroundColor Green }
            else { Write-Host "AVISO: $code" -ForegroundColor Yellow }
        }
    }
}

Write-Host ""
Write-Host "== Testes concluidos ==" -ForegroundColor Cyan