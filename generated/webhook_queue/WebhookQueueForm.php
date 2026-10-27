<?php
namespace PhpGen\Generated;
use PDO;
class WebhookQueueForm {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function render(array $data = []): string {
        ob_start(); ?>
        
        <form method="post" enctype="multipart/form-data">
        <div class="space-y-4">
        <div class="step-content" data-step="1" style="">
        <div class="space-y-4">
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Url</span></label>
                <input type="url" name="url" value="<?= htmlspecialchars((string)($data['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Payload</span></label>
                <textarea name="payload" class="textarea textarea-bordered rounded-lg border w-full h-32" ><?= htmlspecialchars((string)($data['payload'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Signature</span></label>
                <input type="text" name="signature" value="<?= htmlspecialchars((string)($data['signature'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Attempts</span></label>
                <input type="number" name="attempts" value="<?= htmlspecialchars((string)($data['attempts'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
        </div>
        <div class="step-content" data-step="2" style="display:none;">
        <div class="space-y-4">
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Max_attempts</span></label>
                <input type="number" name="max_attempts" value="<?= htmlspecialchars((string)($data['max_attempts'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Status</span></label>
                <input type="select" name="status" value="<?= htmlspecialchars((string)($data['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Last_attempt</span></label>
                <input type="datetime-local" name="last_attempt" value="<?= htmlspecialchars((string)($data['last_attempt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Next_attempt</span></label>
                <input type="datetime-local" name="next_attempt" value="<?= htmlspecialchars((string)($data['next_attempt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
        </div>
        <div class="step-content" data-step="3" style="display:none;">
        <div class="space-y-4">
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Last_response</span></label>
                <textarea name="last_response" class="textarea textarea-bordered rounded-lg border w-full h-32" ><?= htmlspecialchars((string)($data['last_response'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-control w-full"><label class="label"><span class="label-text font-semibold">Last_status_code</span></label>
                <input type="number" name="last_status_code" value="<?= htmlspecialchars((string)($data['last_status_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="input input-bordered rounded-lg border w-full"  >
            </div>
        </div>
        <div class="flex justify-between mt-6">
            <button type="button" class="btn btn-ghost" onclick="prevStep()">← Anterior</button>
            <span class="self-center font-bold" id="step-indicator">Etapa 1 de 3</span>
            <button type="button" class="btn btn-primary" onclick="nextStep()">Próximo →</button>
        </div>
        <script>
            let currentStep = 1;
            const totalSteps = 3;
            function showStep(n) { document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none'); document.querySelector('.step-content[data-step="'+n+'"]').style.display = 'block'; document.getElementById('step-indicator').textContent = 'Etapa '+n+' de '+totalSteps; }
            function nextStep() { if (currentStep < totalSteps) { currentStep++; showStep(currentStep); } }
            function prevStep() { if (currentStep > 1) { currentStep--; showStep(currentStep); } }
        </script>

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

        $fieldMasks = ['id' => '','url' => '','payload' => '','signature' => '','attempts' => '','max_attempts' => '','status' => '','last_attempt' => '','next_attempt' => '','last_response' => '','last_status_code' => '','created_at' => ''];
        $errosValidacao = [];
        foreach ($fieldMasks as $col => $tipo) {
            if (!isset($_post[$col])) continue;
            $valor = trim((string)$_post[$col]);
            if ($valor === '') continue;
            if (!\PhpGen\Validation\BrValidator::validateByType($tipo, $valor)) { $errosValidacao[] = $col . ' (' . $tipo . ')'; }
        }
        if (!empty($errosValidacao)) { throw new \InvalidArgumentException('Validação falhou: ' . implode(', ', $errosValidacao)); }

        $introspector = new \PhpGen\Database\Introspector($this->pdo);
        $dupMsg = $introspector->isDuplicate('webhook_queue', $_post, 'id', $id ? (int)$id : null);
        if ($dupMsg !== null) { throw new \InvalidArgumentException($dupMsg); }

        $skipInCreate = ['id','created_at','updated_at'];
        $skipInEdit   = ['id','created_at'];
        $fieldTypes   = ['id' => 'auto','url' => 'auto','payload' => 'auto','signature' => 'auto','attempts' => 'auto','max_attempts' => 'auto','status' => 'auto','last_attempt' => 'auto','next_attempt' => 'auto','last_response' => 'auto','last_status_code' => 'auto','created_at' => 'auto'];

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
            $sql = "UPDATE `webhook_queue` SET " . implode(', ', $sets) . " WHERE `id` = ?";
            $this->pdo->prepare($sql)->execute($params);
            $this->log($userId, 'UPDATE', 'webhook_queue', (int)$id);
        } else {
            $sql = "INSERT INTO `webhook_queue` SET " . implode(', ', $sets);
            $this->pdo->prepare($sql)->execute($params);
            $this->log($userId, 'CREATE', 'webhook_queue', (int)$this->pdo->lastInsertId());
        }
    }

    private function log(int $userId, string $action, string $table, int $recordId): void {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $table, $recordId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Throwable $e) {}
        $webhookUrl = '';
        if (!empty($webhookUrl)) {
            $payload = json_encode(['action' => $action, 'table' => 'webhook_queue', 'id' => $recordId]);
            $ch = curl_init($webhookUrl); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            curl_exec($ch); curl_close($ch);
        }
    }
}