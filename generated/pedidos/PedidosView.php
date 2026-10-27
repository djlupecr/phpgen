<?php
namespace PhpGen\Generated;
use PDO;
class PedidosView {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function render($id): string {
        $stmt = $this->pdo->prepare("SELECT * FROM `pedidos` WHERE `id` = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) return "<p>Registro não encontrado.</p>";
        ob_start(); ?>
        <div class="bg-base-100 p-6 rounded-lg shadow-lg">
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Id</label><p class="text-lg"><?= htmlspecialchars((string)$data['id'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Usuario_id</label><p class="text-lg"><?= htmlspecialchars((string)$data['usuario_id'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Produto_id</label><p class="text-lg"><?= htmlspecialchars((string)$data['produto_id'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Quantidade</label><p class="text-lg"><?= htmlspecialchars((string)$data['quantidade'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Total</label><p class="text-lg"><?= htmlspecialchars((string)$data['total'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Criado_em</label><p class="text-lg"><?= htmlspecialchars((string)$data['criado_em'], ENT_QUOTES, 'UTF-8') ?></p></div>


        </div>
        <?php return ob_get_clean();
    }
}