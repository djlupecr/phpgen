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