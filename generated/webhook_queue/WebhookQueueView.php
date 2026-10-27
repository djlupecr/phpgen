<?php
namespace PhpGen\Generated;
use PDO;
class WebhookQueueView {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function render($id): string {
        $stmt = $this->pdo->prepare("SELECT * FROM `webhook_queue` WHERE `id` = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) return "<p>Registro não encontrado.</p>";
        ob_start(); ?>
        <div class="bg-base-100 p-6 rounded-lg shadow-lg">
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Id</label><p class="text-lg"><?= htmlspecialchars((string)$data['id'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Url</label><p class="text-lg"><?= htmlspecialchars((string)$data['url'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Payload</label><p class="text-lg"><?= htmlspecialchars((string)$data['payload'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Signature</label><p class="text-lg"><?= htmlspecialchars((string)$data['signature'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Attempts</label><p class="text-lg"><?= htmlspecialchars((string)$data['attempts'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Max_attempts</label><p class="text-lg"><?= htmlspecialchars((string)$data['max_attempts'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Status</label><p class="text-lg"><?= htmlspecialchars((string)$data['status'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Last_attempt</label><p class="text-lg"><?= htmlspecialchars((string)$data['last_attempt'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Next_attempt</label><p class="text-lg"><?= htmlspecialchars((string)$data['next_attempt'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Last_response</label><p class="text-lg"><?= htmlspecialchars((string)$data['last_response'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Last_status_code</label><p class="text-lg"><?= htmlspecialchars((string)$data['last_status_code'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Created_at</label><p class="text-lg"><?= htmlspecialchars((string)$data['created_at'], ENT_QUOTES, 'UTF-8') ?></p></div>


        </div>
        <?php return ob_get_clean();
    }
}