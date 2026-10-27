<?php
namespace PhpGen\Generated;
use PDO;
class UsuariosForm {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function render(array $data = []): string {
        ob_start(); ?>
        
        <form method="post" enctype="multipart/form-data">
        <div class="space-y-4">
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Nome</span></label>
                <input type="text" name="nome" value="<?= htmlspecialchars((string)($data['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Email</span></label>
                <input type="email" name="email" value="<?= htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Senha</span></label>
                <div class="relative"><input type="password" name="senha" id="pwd_senha" value="" class="input input-bordered rounded-lg border w-full"><button type="button" onclick="togglePwd('senha')" class="absolute right-3 top-3">👁️</button></div><script>function togglePwd(id){var x=document.getElementById('pwd_'+id); x.type=x.type==='password'?'text':'password';}</script>
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Ativo</span></label>
                <input type="number" name="ativo" value="<?= htmlspecialchars((string)($data['ativo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Criado_em</span></label>
                <input type="datetime-local" name="criado_em" value="<?= htmlspecialchars((string)($data['criado_em'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
        </div>

            <div class="flex justify-end gap-2 pt-6 border-t mt-6"><button type="submit" class="btn btn-success rounded-lg text-white">crud.save</button></div>
        </form>
        <script>

        </script>
        <?php return ob_get_clean();
    }

    public function handle($_post, $_files, int $userId = 0): void {
        $id = $_post['id'] ?? null;
        $sets = []; $params = [];

        $allowedExt  = ['jpg','jpeg','png','gif','webp','pdf','doc','docx'];
        $allowedMime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf', 'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        foreach ($_files as $key => $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            if ($file['size'] > 5 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) continue;
            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (!in_array($mime, $allowedMime, true)) continue;
            $nome = bin2hex(random_bytes(16)) . '.' . $ext;
            $caminho = 'uploads/' . $nome;
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../../public/' . $caminho)) { $_post[$key] = $caminho; }
        }

        $fieldMasks = [];
        $errosValidacao = [];
        foreach ($fieldMasks as $col => $tipo) {
            if (!isset($_post[$col])) continue;
            $valor = trim((string)$_post[$col]);
            if ($valor === '') continue;
            if (!\PhpGen\Validation\BrValidator::validateByType($tipo, $valor)) { $errosValidacao[] = $col . ' (' . $tipo . ')'; }
        }
        if (!empty($errosValidacao)) { throw new \InvalidArgumentException('Validação falhou: ' . implode(', ', $errosValidacao)); }

        $introspector = new \PhpGen\Database\Introspector($this->pdo);
        $dupMsg = $introspector->isDuplicate('usuarios', $_post, 'id', $id ? (int)$id : null);
        if ($dupMsg !== null) { throw new \InvalidArgumentException($dupMsg); }

        $skipInCreate = ['id','created_at','updated_at'];
        $skipInEdit   = ['id','created_at'];
        $fieldTypes   = [];

        foreach ($_post as $k => $v) {
            if ($k === 'id') continue;
            if ($k === '_csrf') continue;
            if (in_array($k, $skipInCreate, true)) continue;
            if (in_array($k, $skipInEdit, true)) continue;
            if (in_array($k, ['senha','password'], true) && empty($v)) continue;
            if (in_array($k, ['senha','password'], true) && !empty($v)) $v = password_hash($v, PASSWORD_DEFAULT);
            if (isset($fieldTypes[$k]) && $fieldTypes[$k] === 'richtext' && !empty($v)) { $v = \PhpGen\Validation\HtmlSanitizer::sanitize((string)$v); }
            $sets[] = "`$k` = ?";
            $params[] = $v;
        }

        if (empty($sets)) { return; }

        if ($id) {
            $params[] = $id;
            $sql = "UPDATE `usuarios` SET " . implode(', ', $sets) . " WHERE `id` = ?";
            $this->pdo->prepare($sql)->execute($params);
            $this->log($userId, 'UPDATE', 'usuarios', (int)$id);
        } else {
            $sql = "INSERT INTO `usuarios` SET " . implode(', ', $sets);
            $this->pdo->prepare($sql)->execute($params);
            $this->log($userId, 'CREATE', 'usuarios', (int)$this->pdo->lastInsertId());
        }
    }

    private function log(int $userId, string $action, string $table, int $recordId): void {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $table, $recordId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Throwable $e) {}
        $webhookUrl = '';
        if (!empty($webhookUrl)) {
            $payload = json_encode(['action' => $action, 'table' => 'usuarios', 'id' => $recordId]);
            $ch = curl_init($webhookUrl); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            curl_exec($ch); curl_close($ch);
        }
    }
}