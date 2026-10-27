<?php
namespace PhpGen\Generated;
use PDO;
class ProdutosView {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function render($id): string {
        $stmt = $this->pdo->prepare("SELECT * FROM `produtos` WHERE `id` = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) return "<p>Registro não encontrado.</p>";
        ob_start(); ?>
        <div class="bg-base-100 p-6 rounded-lg shadow-lg">
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Id</label><p class="text-lg"><?= htmlspecialchars((string)$data['id'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Nome</label><p class="text-lg"><?= htmlspecialchars((string)$data['nome'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Preco</label><p class="text-lg"><?= htmlspecialchars((string)$data['preco'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Estoque</label><p class="text-lg"><?= htmlspecialchars((string)$data['estoque'], ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="mb-4"><label class="text-sm font-bold text-gray-500">Ativo</label><p class="text-lg"><?= htmlspecialchars((string)$data['ativo'], ENT_QUOTES, 'UTF-8') ?></p></div>


        </div>
        <?php return ob_get_clean();
    }
}