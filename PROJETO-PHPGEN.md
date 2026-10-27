# 🚀 PROJETO PHPGEN — Clone Enterprise do PHPRunner

## 🎯 Status Atual: Enterprise Final (v2.7.0)
O sistema atingiu o nível máximo de maturidade e foi **100% validado em testes de produção e ambiente Docker** (**108 testes executados, 0 falhas**).

Nesta versão, o **Editor Visual de Formulários** foi implementado, permitindo que usuários montem formulários com **drag-and-drop** e visualizem o resultado em **tempo real**. O código já havia sido refatorado para **MVC leve** na v2.5.0 e recebeu **Multi-Tenant**, **Fila Persistente de Webhooks** e **Auditoria com Diff** na v2.6.0.

**Última atualização:** 27/10/2026
**Status:** Production-Ready (v2.7.0 Final). Pronto para deploy em Docker ou XAMPP.

---

## 🛠️ Stack Tecnológica
- **PHP 8.2** (Docker / XAMPP)
- **MySQL 8 / MariaDB 11** (via PDO)
- **Docker + Docker Compose** (Deploy reproduzível)
- **Twig 3** (templates)
- **Tailwind CSS + DaisyUI 4** (via CDN)
- **Chart.js 4** (Dashboard)
- **TinyMCE 6** (Rich Text)
- **Swagger UI 5** (Documentação da API)
- **SortableJS 1.15** (Editor Visual)
- **Alpine.js 3** (Reatividade no Editor Visual)
- **Cloudflare Turnstile** (Anti-Bot)
- **PragmaRX Google2FA** (2FA)
- **Google Gemini API** (AI Code Assist)
- **Firebase PHP-JWT ^7.0** (Autenticação Stateless)
- **PhpSpreadsheet ^2.0** (Exportação XLSX)
- **Dompdf ^3.1** (Exportação PDF)
- **Composer 2** (gerenciador de dependências)
- **GitHub Actions** (CI/CD)

---

## 📁 Estrutura de Pastas e Arquivos
```text
C:\phpgen
├── .github/workflows/ci.yml         # CI/CD (Lint, Testes, Autoload)
├── composer.json
├── composer.lock
├── Dockerfile                       # PHP 8.2 + Apache + SetEnvIf Authorization
├── docker-compose.yml               # Orquestração (app + db MariaDB)
├── vendor\                          
├── src
│   ├── Config.php                   # Banco (env vars) + JWT Secret + Webhook Secret
│   ├── Http                         # ← Arquitetura MVC
│   │   ├── Router.php               # Mapeia ?action= para Controllers
│   │   └── Controllers/
│   │       ├── AuthController.php
│   │       ├── TwoFactorController.php
│   │       ├── DashboardController.php
│   │       ├── AuditController.php
│   │       ├── BackupController.php
│   │       ├── AiController.php
│   │       ├── NotificationController.php
│   │       ├── TokenController.php
│   │       ├── RoleController.php
│   │       ├── WebhookController.php
│   │       ├── CrudController.php
│   │       ├── ApiController.php
│   │       ├── ExportController.php
│   │       ├── HealthController.php
│   │       ├── WizardController.php
│   │       ├── TenantController.php     # v2.6.0
│   │       └── DesignerController.php   # v2.7.0 ← NOVO
│   ├── Jobs                         # v2.6.0
│   │   └── WebhookProcessor.php     # Processa a fila de webhooks
│   ├── Database
│   │   └── Introspector.php         # Banco + RBAC + Fila + Rate Limit + Tenant + Diff
│   ├── Generator
│   │   └── Generator.php            # Cérebro (Grid, Form, View, Export, Delete)
│   └── Validation
│       ├── BrValidator.php          # Validação CPF/CNPJ/Data/Hora
│       ├── HtmlSanitizer.php        # Sanitização anti-XSS (Whitelist)
│       └── BackupManager.php        # Export/Import ZIP+SQL
├── locales
│   ├── pt_BR.php
│   ├── en.php
│   └── es.php
├── public
│   ├── install.php                  # Instalador Gráfico
│   ├── index.php                    # Roteador (~120 linhas)
│   ├── openapi.json                 # Contrato da API REST
│   └── uploads\                     # Arquivos enviados
├── templates
│   ├── layout.twig                  # Layout base
│   ├── login.twig
│   ├── dashboard.twig
│   ├── audit.twig
│   ├── tokens.twig
│   ├── roles.twig
│   ├── webhook_logs.twig
│   ├── backup.twig
│   ├── ai.twig
│   ├── 2fa_setup.twig
│   ├── 2fa_verify.twig
│   ├── openapi.twig                 # Tela do Swagger UI
│   ├── tenants.twig                 # v2.6.0
│   ├── designer.twig                # v2.7.0 ← NOVO
│   └── wizard_step1..6.twig
├── tools
│   ├── criar_admin.php              # CLI: Cria/reseta admin
│   ├── criar_tabelas.php            # CLI: Cria tabelas base no Docker
│   ├── gerar_tabelas.php            # CLI: Regenera tabelas
│   ├── migrar_v260.php              # CLI: Migração Onda 2 (v2.6.0)
│   ├── processar_webhooks.php       # CLI: Processa fila (para cron)
│   ├── verificar_sistema.php        # CLI: Auditoria (39 verificações)
│   ├── testar_sistema.php           # CLI: 17 testes unitários
│   ├── testar_validacao.php         # CLI: 18 testes de validação
│   ├── testar_api_jwt.ps1           # CLI: 11 testes de integração (API)
│   ├── testar_v260.php              # CLI: 16 testes Onda 2
│   ├── testar_v270.php              # CLI: 7 testes Editor Visual
│   ├── testar_tudo.ps1              # CLI: Roda todos os testes (108)
│   └── deploy.ps1                   # CLI: Deploy completo
└── generated\                       # Saída do gerador (classmap)
    └── <tabela>
        ├── <Tabela>Grid.php         # Listagem (RBAC via JS)
        ├── <Tabela>Form.php         # Form (máscaras, XSS Shield, upload)
        ├── <Tabela>View.php         # View + Master-Detail
        ├── <Tabela>Delete.php       # Soft delete
        ├── <Tabela>Export.php       # CSV
        ├── <Tabela>ExportExcel.php  # XLSX
        ├── <Tabela>ExportPdf.php    # PDF
        └── config.json
✅ Funcionalidades Ativas (Enterprise Final)
1. Instalador Gráfico (install.php)
Independente do Config.php; cria tabelas, usuarios e admin.

Gera o Config.php com JWT/Webhook secrets aleatórios.

Aceita ?force=1 para reinstalar.

2. Autenticação e Segurança (Hardened)
Login: password_verify() + Rate Limit.

Rate Limit (Anti-Brute Force): Bloqueia IPs após 5 falhas em 5 min (HTTP 429 na API).

2FA: Google Authenticator.

Captcha: Cloudflare Turnstile.

JWT: HS256, 1h, revogável via Denylist (?action=logout_api).

CSRF: hash_equals em writes.

XSS Shield: HtmlSanitizer com whitelist.

3. Gerador de Código (CRUD Completo)
Grid: Busca, paginação, Inline Editing, Ações em Massa, Filtros. RBAC via JS.

Form: FKs inteligentes, upload, Rich Text, Wizard Form.

Duplicatas: Introspector::isDuplicate().

Exportação: CSV, XLSX, PDF.

4. Arquitetura MVC (v2.5.0)
Router.php: mapeia ?action=xxx para Controllers.

17 Controllers especializados (incluindo DesignerController).

index.php enxuto (~120 linhas).

5. Docker e Infraestrutura
Dockerfile: php:8.2-apache + SetEnvIf Authorization + DocumentRoot=/public.

docker-compose.yml: app (8080) + db (MariaDB 11, 3307).

6. Documentação da API (OpenAPI)
Swagger UI em ?action=api_docs.

Contrato em public/openapi.json.

7. CI/CD (GitHub Actions)
Workflow em .github/workflows/ci.yml.

8. Suíte de Testes (108 testes)
Unitários: 17.

Validação: 18.

Auditoria: 39.

Integração: 11.

Onda 2 (v2.6.0): 16.

Editor Visual (v2.7.0): 7.

🆕 Onda 2 (v2.6.0) — Escalabilidade e Multiusuário
1. Fila Persistente de Webhooks
Tabela webhook_queue com status pending, processing, success, dead_letter.

Retry com backoff exponencial (até max_attempts).

Dead Letter Queue para jobs que esgotaram tentativas.

Recuperação de jobs travados em processing por mais de 5 min.

Processamento via ?action=process_webhooks&secret=XXX ou CLI.

2. Multi-Tenant
Tabela tenants (com tenant default criado automaticamente).

Coluna tenant_id em usuarios.

Introspector::getTenantId() e applyTenantFilter($table).

Troca de tenant via ?action=tenants (admin).

3. Auditoria com Diff
Colunas details_json e user_agent em audit_logs.

Introspector::logChange() registra apenas o que mudou (old/new).

🆕 Onda 3 (v2.7.0) — Editor Visual
1. Editor Visual de Formulários
Drag-and-drop com SortableJS (arrastar campos entre colunas).

Preview em tempo real com Alpine.js.

Salvamento no config.json (field_order).

Rota: ?action=designer&table=usuarios.

RBAC integrado (apenas UPDATE).

Validação CSRF no salvamento.

2. Arquivos
src/Http/Controllers/DesignerController.php

templates/designer.twig

Rotas designer e designer_save no index.php.

Testes em tools/testar_v270.php.

🆕 Correções Aplicadas (v2.6.0 → v2.7.0)
#	Correção	Arquivo
1	Editor Visual (drag-and-drop + preview)	DesignerController.php, designer.twig
2	Rotas designer e designer_save	public/index.php
3	Testes do Editor Visual (login via curl + cookie)	tools/testar_v270.php
4	Correção do autoload (removido WebSocket/Server.php)	src/WebSocket/
5	testar_tudo.ps1 atualizado para 108 testes	tools/testar_tudo.ps1
🧪 Como Testar (Docker)
bash
# 1. Subir os containers
docker compose up -d

# 2. Rodar o instalador (Navegador)
# Acesse: http://localhost:8080/install.php
# Host: db | Porta: 3306 | Database: test | User: phpgen | Pass: phpgen

# 3. Rodar migrações (se necessário)
docker compose exec app php tools/migrar_v260.php

# 4. Rodar a suíte completa (108 testes)
.\tools\testar_tudo.ps1

# Saída esperada:
# 1. Testes Unitários (Core)...      OK=17 | FALHAS=0
# 2. Testes de Validação (18)...     18 ok, 0 falhas
# 3. Auditoria do Sistema...         ✅ 39 ok | ⚠️ 0 avisos | ❌ 0 falhas
# 4. Testes de Integração (API)...   OK=11 | FALHAS=0
# 5. Testes v2.6.0 (Onda 2)...       OK=16 | FALHAS=0
# 6. Testes v2.7.0 (Editor Visual)... OK=7 | FALHAS=0
# === TODOS OS TESTES PASSARAM COM SUCESSO! ===
🏁 Conclusão
O phpgen está oficialmente fechado e validado.

XAMPP local: Funciona.

Docker: Containerizado.

Arquitetura: MVC leve (Router + 17 Controllers).

Segurança: JWT revogável, Rate Limit, XSS Shield.

Escalabilidade: Fila persistente, Multi-Tenant.

Auditoria: Diff (LGPD).

Editor Visual: Drag-and-drop + Preview.

Testes: 108 testes, 100% verdes.

Status: Production-Ready (v2.7.0 Final).
Última atualização: 27/10/2026.

O projeto está pronto para uso em produção! 🚀

text

---

## 📄 2. `CHANGELOG.md`

```markdown
# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [2.7.0] - 2026-10-27

### Adicionado
- **Editor Visual de Formulários** (`?action=designer&table=usuarios`):
  - Drag-and-drop com **SortableJS** (arrastar entre colunas).
  - **Preview em tempo real** com **Alpine.js**.
  - Salvamento no `config.json` (`field_order`).
  - RBAC integrado (apenas `UPDATE`).
  - Validação CSRF no salvamento.
  - `DesignerController` + `designer.twig`.

### Corrigido
- **Autoload**: removido `WebSocket/Server.php` que causava aviso PSR-4.
- **Testes v2.7.0**: login via `curl` com `COOKIEJAR`/`COOKIEFILE` para simular usuário autenticado.

### Testes
- Total: **108 testes** (antes 101), 0 falhas:
  - 17 unitários.
  - 18 de validação.
  - 39 de auditoria.
  - 11 de integração (API).
  - 16 da Onda 2.
  - **7 do Editor Visual**.

## [2.6.0] - 2026-10-27

### Adicionado
- **Fila Persistente de Webhooks** (`webhook_queue`):
  - Retry com backoff exponencial.
  - Dead Letter Queue.
  - Recuperação de jobs travados.
- **Multi-Tenant**:
  - Tabela `tenants` + coluna `tenant_id`.
  - `getTenantId()` e `applyTenantFilter()`.
- **Auditoria com Diff** (`details_json`, `user_agent`).
- Migração `tools/migrar_v260.php`.

### Corrigido
- **`fetchPendingWebhooks`**: corrigido `LIMIT ?` (MariaDB não aceita bind em `LIMIT`).
- **`Introspector.php`**: removido BOM que causava `Namespace declaration statement has to be the very first statement`.
- **`composer.json`**: corrigido JSON inválido (chave `scripts` duplicada).

### Testes
- Total: **101 testes**, 0 falhas.

## [2.5.0] - 2026-10-27

### Adicionado
- **Arquitetura MVC leve**:
  - `src/Http/Router.php`.
  - 15 Controllers em `src/Http/Controllers/`.
  - `public/index.php` reduzido para ~120 linhas.
- **Twig global** injetado nos controllers.
- **Router** recarrega o usuário da sessão.

### Corrigido
- **`composer.json`**: JSON inválido (chave `scripts` duplicada).

### Testes
- Total: **85 testes**, 0 falhas.

## [2.4.0] - 2026-10-27

### Adicionado
- **JWT Denylist** (`revoked_tokens` + `?action=logout_api`).
- **Rate Limit por IP** (HTTP 429 na API).
- **HtmlSanitizer robusto** (whitelist de tags).
- **OpenAPI + Swagger UI** (`?action=api_docs`).
- **CI/CD** (GitHub Actions).
- Scripts de teste: `testar_sistema.php`, `testar_api_jwt.ps1`, `testar_tudo.ps1`.

### Testes
- Total: **85 testes**, 0 falhas.

## [2.3.0] - 2026-10-26

### Adicionado
- **Dockerfile** com `SetEnvIf Authorization` e `DocumentRoot=/public`.
- **docker-compose.yml** (app + MariaDB).
- CLI: `gerar_tabelas.php`, `criar_tabelas.php`.

### Corrigido
- **`Config.php`**: `getenv()` no `init()`.
- **`install.php`**: reescrito (aceita `?force=1`).
- **Grid**: RBAC via JS (sem `window.CAN_UPDATE` no PHP).
- **Autoload**: `classmap` para `generated/`.

## [2.2.0] - 2026-10-26

### Adicionado
- **`?action=health`** público.
- **CLI**: `gerar_tabelas.php`, `verificar_sistema.php`.
- Autoload otimizado (`psr-4` + `classmap`).

### Corrigido
- **`BrValidator::isValidTime`**: rejeita `24:00`.
- **`die()` duplicado** no `index.php`.
- **`$userId` warning** no RBAC.

## [2.1.1] - 2026-10-26

### Adicionado
- **Exportação Excel (XLSX)** e **PDF**.
- **`$webhookSecret`** separado do JWT.
- **CSRF** em `notification_read` e `save_inline`.
- **XSS Shield** no `save_inline`.

### Corrigido
- **`composer.json`** com versões vulneráveis.
- **`2fa_setup`** usando `$userId` indefinido.
- **`delete_multiple`** sem transação.
- **CSV** sem BOM UTF-8.

## [2.1.0] - 2026-10-26

### Adicionado
- **JWT Stateless** (Firebase PHP-JWT).
- **Detecção de duplicatas** no gerador.

## [2.0.0] - 2026-10-20

### Adicionado
- **CRUD completo** (Grid/Form/View/Delete).
- **RBAC** (admin/editor/viewer).
- **Webhooks** com retry + HMAC.
- **Backup/Restore**.
- **i18n** (pt/en/es).
- **Dashboard** Chart.js.
- **AI Code Assist** (Gemini).
- **Notificações** em tempo real.
📄 3. README.md
markdown
# 🚀 PHPGEN — Clone Enterprise do PHPRunner

Gerador de CRUDs PHP com arquitetura moderna, segurança bancária, IA integrada e deploy em Docker.

## ✨ Destaques

- **CRUD Completo**: Grid, Form, View, Delete, Master-Detail.
- **Editor Visual**: Monte formulários com drag-and-drop.
- **API REST**: JWT revogável, OpenAPI (Swagger UI).
- **Multi-Tenant**: Ideal para SaaS.
- **Auditoria com Diff**: LGPD ready.
- **Fila de Webhooks**: Retry + Dead Letter + Recuperação.
- **Docker**: Deploy reproduzível em qualquer máquina.
- **108 testes**: 100% verdes.

## 🚀 Quickstart

### Opção 1 — Docker (Recomendado)

```bash
# 1. Clonar o repositório
git clone <seu-repo> phpgen && cd phpgen

# 2. Subir os containers
docker compose up -d

# 3. Aguardar o MariaDB iniciar
Start-Sleep -Seconds 10

# 4. Acessar o instalador
# http://localhost:8080/install.php
# Host: db | Porta: 3306 | Database: test | User: phpgen | Pass: phpgen
Opção 2 — XAMPP
bash
# 1. Clonar o repositório em C:\xampp\htdocs\phpgen
# 2. Instalar dependências
composer install

# 3. Configurar o Config.php (banco local)
# 4. Acessar http://localhost/phpgen/public/install.php
🧪 Testar
bash
# Docker
.\tools\testar_tudo.ps1

# Saída esperada (108 testes):
# 1. Testes Unitários...       OK=17 | FALHAS=0
# 2. Testes de Validação...    18 ok, 0 falhas
# 3. Auditoria...              ✅ 39 ok | ❌ 0 falhas
# 4. Testes de Integração...   OK=11 | FALHAS=0
# 5. Testes v2.6.0...          OK=16 | FALHAS=0
# 6. Testes v2.7.0...          OK=7  | FALHAS=0
🎨 Como usar o Editor Visual
Acesse ?action=designer&table=usuarios.

Arraste campos da coluna "Disponíveis" para "Layout do Formulário".

Reordene os campos (arraste para cima/baixo).

Remova clicando no ✕.

Preview atualiza em tempo real.

Clique em 💾 Salvar Layout.

📚 Documentação Completa
PROJETO-PHPGEN.md — Arquitetura e funcionalidades.

CHANGELOG.md — Histórico de versões.

?action=api_docs — Swagger UI (API REST).

🛠️ Stack
PHP 8.2 + MariaDB 11

Docker + Compose

Twig 3 + Tailwind/DaisyUI 4

JWT (Firebase) + 2FA (Google2FA)

PhpSpreadsheet + Dompdf

SortableJS + Alpine.js

Swagger UI

GitHub Actions (CI/CD)

🔐 Segurança
JWT revogável (Denylist).

Rate Limit por IP.

2FA (Google Authenticator).

CSRF em writes.

XSS Shield (whitelist de tags).

Upload seguro (MIME + extensão + random_bytes).

📊 Estrutura do Projeto
text
src/
├── Http/             # MVC (Router + 17 Controllers)
├── Jobs/             # WebhookProcessor
├── Database/         # Introspector
├── Generator/        # Gerador de código
└── Validation/       # BrValidator, HtmlSanitizer, BackupManager
🏁 Status
Production-Ready (v2.7.0) — 108 testes verdes, Docker, MVC, Editor Visual, Multi-Tenant, Auditoria com Diff.

📝 Licença
MIT

text

---

## 🚀 Como aplicar

1. **Salve o `PROJETO-PHPGEN.md`** (substituindo o atual).
2. **Salve o `CHANGELOG.md`** (novo arquivo na raiz).
3. **Salve o `README.md`** (substituindo o atual).
4. **Verifique o encoding** no VS Code (canto inferior direito → **UTF-8**, não **UTF-8 with BOM**).
5. **Confirme o encoding dos 3 arquivos**:

```powershell
foreach ($f in @("PROJETO-PHPGEN.md", "CHANGELOG.md", "README.md")) {
    $bytes = [System.IO.File]::ReadAllBytes("C:\phpgen\$f")
    $hasBom = ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF)
    Write-Host "$f : BOM = $hasBom"
}
Esperado: BOM = False para os 3 arquivos.

🏁 Projeto Oficialmente Fechado
Categoria	Status
Código	✅ MVC + 17 Controllers
Testes	✅ 108 testes, 0 falhas
Docker	✅ Produção
Segurança	✅ JWT revogável + Rate Limit + XSS Shield
Escalabilidade	✅ Fila + Multi-Tenant
Auditoria	✅ Diff (LGPD)
Editor Visual	✅ Drag-and-drop
Documentação	✅ PROJETO-PHPGEN.md + CHANGELOG.md + README.md
CI/CD	✅ GitHub Actions
Parabéns pela jornada! Você construiu um clone Enterprise do PHPRunner, do zero, com qualidade de produção. 🚀🎉

This response is AI-generated, for reference only.
