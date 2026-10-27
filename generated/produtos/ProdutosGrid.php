<?php
namespace PhpGen\Generated;
use PDO;
class ProdutosGrid
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function render(): string
    {
        $search = $_GET['search'] ?? '';
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 10;
        $offset = ($page - 1) * $limit;

        $where = "WHERE `deleted_at` IS NULL";
        $params = [];
        if ($search !== '') {
            $where .= " AND (`id` LIKE :search OR `nome` LIKE :search OR `preco` LIKE :search OR `estoque` LIKE :search OR `ativo`` LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $advancedFilter = json_decode($_GET['filter'] ?? '[]', true);
        if (!empty($advancedFilter)) {
            foreach ($advancedFilter as $rule) {
                if (!empty($rule['value'])) {
                    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $rule['field']);
                    $op = in_array($rule['op'], ['=', '>', '<', 'LIKE']) ? $rule['op'] : '=';
                    $where .= " AND `$col` $op ?";
                    $params[] = $op === 'LIKE' ? "%{$rule['value']}%" : $rule['value'];
                }
            }
        }

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `produtos` $where");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        $totalPages = (int)ceil($total / $limit);

        $stmt = $this->pdo->prepare("SELECT * FROM `produtos` $where LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        ?>
        <div class="mb-4 flex flex-wrap gap-2">
            <form method="get" action="?" class="flex gap-2 w-full">
                <input type="hidden" name="action" value="view_grid">
                <input type="hidden" name="table" value="produtos">
                <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="crud.search" class="input input-bordered w-full rounded-lg">
                <button type="submit" class="btn btn-primary rounded-lg">Buscar</button>
                <a href="?action=export&table=produtos" class="btn btn-success rounded-lg">CSV</a>
                <a href="?action=export_excel&table=produtos" class="btn btn-success rounded-lg">Excel</a>
                <a href="?action=export_pdf&table=produtos" class="btn btn-error rounded-lg">PDF</a>
                <a href="?action=faker&table=produtos" class="btn btn-secondary rounded-lg">🎲 crud.generate_fake</a>
                <button type="button" class="btn btn-outline rounded-lg" onclick="openFilterModal()">⚙️ Filtros</button>
            </form>
        </div>

        <div class="flex justify-between items-center mb-4 bg-base-100 p-2 rounded shadow-sm">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="select-all" class="checkbox checkbox-primary checkbox-sm" onclick="toggleAll(this)">
                <label for="select-all" class="text-sm font-semibold cursor-pointer">Selecionar Todos</label>
            </div>
            <button type="button" class="btn btn-sm btn-error" onclick="deleteSelected()">🗑️ Excluir Selecionados</button>
        </div>

        <div class="overflow-x-auto bg-base-100 rounded-lg shadow-lg p-4">
            <table class="table border w-full" id="grid-table">
                <thead><tr class="bg-base-200">
                    <th class="w-10"></th>
                    <?php             echo '<th>Id</th>' . PHP_EOL;
            echo '<th>Nome</th>' . PHP_EOL;
            echo '<th>Preco</th>' . PHP_EOL;
            echo '<th>Estoque</th>' . PHP_EOL;
            echo '<th>Ativo</th>' . PHP_EOL;
 ?>
                    <th class="text-right">Ações</th>
                </tr></thead>
                <tbody>
                    <?php if(empty($rows)): ?>
                        <tr><td colspan="100%" class="text-center py-8 text-base-content/50">crud.no_records</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr class="hover">
                            <td class="text-center"><input type="checkbox" class="checkbox checkbox-primary checkbox-sm row-checkbox" value="<?= $row['id'] ?>"></td>
                            <?php                 echo '<td>';
                echo '<span class="inline-edit" data-id="'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'" data-field="id" data-value="'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'</span>';
                echo '</td>' . PHP_EOL;
                echo '<td>';
                echo '<span class="inline-edit" data-id="'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'" data-field="nome" data-value="'.htmlspecialchars((string)$row['nome'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars((string)$row['nome'], ENT_QUOTES, 'UTF-8').'</span>';
                echo '</td>' . PHP_EOL;
                echo '<td>';
                echo '<span class="inline-edit" data-id="'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'" data-field="preco" data-value="'.htmlspecialchars((string)$row['preco'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars((string)$row['preco'], ENT_QUOTES, 'UTF-8').'</span>';
                echo '</td>' . PHP_EOL;
                echo '<td>';
                echo '<span class="inline-edit" data-id="'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'" data-field="estoque" data-value="'.htmlspecialchars((string)$row['estoque'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars((string)$row['estoque'], ENT_QUOTES, 'UTF-8').'</span>';
                echo '</td>' . PHP_EOL;
                echo '<td>';
                echo '<span class="inline-edit" data-id="'.htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8').'" data-field="ativo" data-value="'.htmlspecialchars((string)$row['ativo'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars((string)$row['ativo'], ENT_QUOTES, 'UTF-8').'</span>';
                echo '</td>' . PHP_EOL;
 ?>
                            <td class="text-right whitespace-nowrap">
                                <a href="?action=view_record&table=produtos&id=<?= urlencode($row['id']) ?>" class="btn btn-sm btn-ghost rounded-lg">crud.view</a>
                                <!-- [FIX] Botões gerados sem PHP inline problemático, controlados pelo JS -->
                                <a href="?action=edit&table=produtos&id=<?= urlencode($row['id']) ?>" class="btn btn-sm btn-info rounded-lg text-white btn-edit">crud.edit</a>
                                <a href="?action=delete&table=produtos&id=<?= urlencode($row['id']) ?>" onclick="return confirm('Excluir?')" class="btn btn-sm btn-error rounded-lg text-white btn-delete">crud.delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="flex justify-center gap-2 mt-4">
            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?action=view_grid&table=produtos&page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $i == $page ? 'btn-active' : '' ?> rounded-lg"><?= $i ?></a>
            <?php endfor; ?>
        </div>

        <dialog id="filter_modal" class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-lg">Filtro Avançado</h3>
                <div id="filter-rules" class="space-y-2 my-4"></div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addFilterRule()">+ Adicionar Regra</button>
                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('filter_modal').close()">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="applyFilter()">Aplicar</button>
                </div>
            </div>
        </dialog>

        <script>
            function toggleAll(source) {
                document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = source.checked);
            }
            function deleteSelected() {
                const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
                if (selected.length === 0) { alert('Selecione pelo menos um registro.'); return; }
                if (!confirm(`Excluir ${selected.length} registro(s)?`)) return;
                fetch('?action=delete_multiple', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || ''},
                    body: JSON.stringify({table: 'produtos', ids: selected, _csrf: window.CSRF_TOKEN || ''})
                }).then(() => window.location.reload());
            }

            let filterSchema = [];
            function openFilterModal() {
                fetch(`?action=advanced_filter&table=produtos`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            filterSchema = data.schema;
                            document.getElementById('filter-rules').innerHTML = '';
                            addFilterRule();
                            document.getElementById('filter_modal').showModal();
                        }
                    });
            }
            function addFilterRule() {
                const div = document.createElement('div');
                div.className = 'flex gap-2 items-center';
                let options = filterSchema.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
                div.innerHTML = `
                    <select class="select select-bordered select-sm field">${options}</select>
                    <select class="select select-bordered select-sm op">
                        <option value="=">=</option>
                        <option value="LIKE">Contém</option>
                        <option value=">">&gt;</option>
                        <option value="<">&lt;</option>
                    </select>
                    <input type="text" class="input input-bordered input-sm value" placeholder="Valor...">
                    <button type="button" class="btn btn-sm btn-error" onclick="this.parentElement.remove()">✕</button>
                `;
                document.getElementById('filter-rules').appendChild(div);
            }
            function applyFilter() {
                const rules = [];
                document.querySelectorAll('#filter-rules .flex').forEach(div => {
                    const field = div.querySelector('.field').value;
                    const op = div.querySelector('.op').value;
                    const val = div.querySelector('.value').value;
                    if (val) rules.push({ field, op, value: val });
                });
                const url = new URL(window.location.href);
                url.searchParams.set('filter', JSON.stringify(rules));
                window.location.href = url.toString();
            }

            // [FIX] Esconde os botões de editar/excluir se o usuário não tiver permissão (via JS, sem quebrar o PHP)
            if (!window.CAN_UPDATE) { document.querySelectorAll('.btn-edit').forEach(el => el.style.display = 'none'); }
            if (!window.CAN_DELETE) { document.querySelectorAll('.btn-delete').forEach(el => el.style.display = 'none'); }

            if (window.CAN_UPDATE) {
                document.querySelectorAll('.inline-edit').forEach(function(el) {
                    el.addEventListener('click', function() {
                        if (this.classList.contains('editing')) return;
                        this.classList.add('editing');
                        var originalValue = this.getAttribute('data-value');
                        var id    = this.getAttribute('data-id');
                        var field = this.getAttribute('data-field');
                        var table = 'produtos';

                        var input = document.createElement('input');
                        input.type = 'text';
                        input.value = originalValue;
                        input.className = 'input input-bordered input-sm w-full';

                        this.innerHTML = '';
                        this.appendChild(input);
                        input.focus();

                        input.addEventListener('blur', function() { saveInline(id, field, table, this.value, el, originalValue); });
                        input.addEventListener('keypress', function(e) { if (e.key === 'Enter') this.blur(); });
                    });
                });
            }
            function saveInline(id, field, table, newValue, el, originalValue) {
                if (newValue === originalValue) { el.classList.remove('editing'); el.textContent = originalValue; return; }
                var formData = new FormData();
                formData.append('action', 'save_inline');
                formData.append('table', table);
                formData.append('id', id);
                formData.append('field', field);
                formData.append('value', newValue);
                formData.append('_csrf', window.CSRF_TOKEN || '');
                fetch('?', { method: 'POST', body: formData, credentials: 'same-origin' }).then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.text();
                }).then(() => { el.classList.remove('editing'); el.textContent = newValue; el.setAttribute('data-value', newValue); })
                .catch(() => { alert('Erro ao salvar.'); el.classList.remove('editing'); el.textContent = originalValue; });
            }
        </script>
        <?php
        return ob_get_clean();
    }
}