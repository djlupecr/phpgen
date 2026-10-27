<?php
namespace PhpGen\Generator;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class Generator
{
    private Introspector $db;
    private string $outputDir;

    public function __construct(Introspector $db)
    {
        $this->db = $db;
        $this->outputDir = Config::$outputDir;
        if (!is_dir($this->outputDir)) { mkdir($this->outputDir, 0775, true); }
    }

    public function generate(string $table, array $options = []): array
    {
        $this->db->assertValidTable($table);
        $this->ensureSoftDeleteColumn($table);

        $pages          = $options['pages']           ?? ['list', 'edit', 'add', 'delete'];
        $theme          = $options['theme']           ?? 'light';
        $radius         = $options['border_radius']   ?? 'rounded-lg';
        $borderWidth    = $options['border_width']    ?? 'border';
        $formLayout     = (int)($options['form_layout'] ?? 1);
        $labels         = $options['labels']          ?? [];
        $showInGrid     = $options['show_in_grid']    ?? [];
        $showInForm     = $options['show_in_form']    ?? [];
        $requiredField  = $options['required_field']  ?? [];
        $fieldTypes     = $options['field_types']     ?? [];
        $fieldMasks     = $options['field_masks']     ?? [];
        $webhookUrl     = $options['webhook_url']     ?? '';
        $trans          = $options['translations']    ?? [];
        $t              = fn($key) => $trans[$key] ?? $key;

        $allowedFieldTypes = ['auto','text','email','url','color','file','password','richtext','textarea','checkbox','number','date','datetime-local','time'];
        foreach ($fieldTypes as $k => $v) {
            if (!in_array($v, $allowedFieldTypes, true)) { $fieldTypes[$k] = 'auto'; }
        }

        $schema = $this->db->tableSchema($table);

        $fieldOrder = $options['field_order'] ?? [];
        if (!empty($fieldOrder)) {
            $byName = [];
            foreach ($schema as $c) $byName[$c['name']] = $c;
            $ordered = [];
            foreach ($fieldOrder as $name) {
                if (isset($byName[$name])) { $ordered[] = $byName[$name]; unset($byName[$name]); }
            }
            foreach ($byName as $c) $ordered[] = $c;
            $schema = $ordered;
        }

        $pk     = $this->db->primaryKey($schema) ?? 'id';
        $fks    = $this->db->foreignKeys($table);
        $dir    = $this->outputDir . '/' . $table;
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }

        $uploadDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }

        file_put_contents($dir . '/config.json', json_encode([
            'theme' => $theme, 'radius' => $radius, 'webhook' => $webhookUrl,
            'field_order' => implode(',', array_column($schema, 'name')),
            'field_types' => $fieldTypes,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $class     = $this->studly($table);
        $namespace = Config::$generatedNamespace;
        $files     = [];

        if (in_array('list', $pages)) {
            $gridFile = $dir . '/' . $class . 'Grid.php';
            file_put_contents($gridFile, $this->renderGrid($namespace, $class, $table, $schema, $pk, $radius, $borderWidth, $labels, $showInGrid, $t));
            $files[] = $gridFile;

            $exportFile = $dir . '/' . $class . 'Export.php';
            file_put_contents($exportFile, $this->renderExport($namespace, $class, $table, $schema, $labels, $showInGrid));
            $files[] = $exportFile;

            $excelFile = $dir . '/' . $class . 'ExportExcel.php';
            file_put_contents($excelFile, $this->renderExportExcel($namespace, $class, $table, $schema, $labels, $showInGrid));
            $files[] = $excelFile;

            $pdfFile = $dir . '/' . $class . 'ExportPdf.php';
            file_put_contents($pdfFile, $this->renderExportPdf($namespace, $class, $table, $schema, $labels, $showInGrid));
            $files[] = $pdfFile;

            $viewFile = $dir . '/' . $class . 'View.php';
            file_put_contents($viewFile, $this->renderView($namespace, $class, $table, $schema, $pk, $radius, $labels, $options, $t));
            $files[] = $viewFile;
        }
        if (in_array('edit', $pages) || in_array('add', $pages)) {
            $formFile = $dir . '/' . $class . 'Form.php';
            file_put_contents($formFile, $this->renderForm($namespace, $class, $table, $schema, $pk, $fks, $radius, $borderWidth, $formLayout, $labels, $showInForm, $requiredField, $fieldTypes, $fieldMasks, $webhookUrl, $t));
            $files[] = $formFile;
        }
        if (in_array('delete', $pages)) {
            $deleteFile = $dir . '/' . $class . 'Delete.php';
            file_put_contents($deleteFile, $this->renderDelete($namespace, $class, $table, $pk, $webhookUrl));
            $files[] = $deleteFile;
        }

        return $files;
    }

    private function ensureSoftDeleteColumn(string $table): void
    {
        try { $this->db->pdo()->query("SELECT `deleted_at` FROM `$table` LIMIT 1"); }
        catch (\Throwable $e) {
            try { $this->db->pdo()->exec("ALTER TABLE `$table` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL"); } catch (\Throwable $e2) {}
        }
    }

    private function studly(string $s): string { return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $s))); }

    private function inputType(string $colType, string $colName, array $fieldTypes): string
    {
        if (isset($fieldTypes[$colName]) && $fieldTypes[$colName] !== 'auto') return $fieldTypes[$colName];
        $colType = strtolower($colType); $colName = strtolower($colName);
        if (str_contains($colName, 'senha') || str_contains($colName, 'password')) return 'password';
        if (str_contains($colName, 'email')) return 'email';
        if (str_contains($colName, 'url')) return 'url';
        if (str_contains($colName, 'cor')) return 'color';
        if (str_contains($colName, 'foto') || str_contains($colName, 'imagem') || str_contains($colName, 'avatar') || str_contains($colName, 'arquivo') || str_contains($colName, 'file')) return 'file';
        return Config::$inputMap[$colType] ?? 'text';
    }

    private function getDisplayColumn(string $refTable): string
    {
        $schema = $this->db->tableSchema($refTable);
        $cols = array_column($schema, 'name');
        foreach (['nome', 'name', 'titulo', 'title', 'email', 'username'] as $t) if (in_array($t, $cols)) return $t;
        foreach ($schema as $c) if ($c['key'] !== 'PRI') return $c['name'];
        return $cols[0] ?? 'id';
    }

    private function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

    private function exportableColumns(array $schema, array $showInGrid): array
    {
        $cols = [];
        foreach ($schema as $c) {
            if (!isset($showInGrid[$c['name']]) && !empty($showInGrid)) continue;
            if (in_array($c['type'], Config::$skipInGrid)) continue;
            $it = $this->inputType($c['type'], $c['name'], []);
            if ($it === 'password') continue;
            if ($c['name'] === 'deleted_at') continue;
            if ($it === 'file') continue;
            $cols[] = $c['name'];
        }
        return $cols;
    }

    private function renderGrid(string $ns, string $class, string $table, array $schema, string $pk, string $radius, string $borderWidth, array $labels, array $showInGrid, callable $t): string
    {
        $colsCode = ''; $rowCode = ''; $searchFields = [];
        foreach ($schema as $c) {
            if (!isset($showInGrid[$c['name']]) && !empty($showInGrid)) continue;
            if (in_array($c['type'], Config::$skipInGrid)) continue;
            if ($this->inputType($c['type'], $c['name'], []) === 'password') continue;
            if ($c['name'] === 'deleted_at') continue;

            $label = $labels[$c['name']] ?? ucfirst($c['name']);
            $searchFields[] = $c['name'];
            $colEsc = $this->e($c['name']);

            $colsCode .= "            echo '<th>" . $this->e($label) . "</th>' . PHP_EOL;\n";

            $it = $this->inputType($c['type'], $c['name'], []);
            if ($it === 'file') {
                $rowCode .= "                echo '<td><a href=\"'.\$row['{$colEsc}'].'\" target=\"_blank\"><img src=\"'.\$row['{$colEsc}'].'\" class=\"w-10 h-10 object-cover rounded\"></a></td>' . PHP_EOL;\n";
            } elseif ($it === 'richtext') {
                $rowCode .= "                echo '<td><div class=\"prose max-w-none max-h-24 overflow-hidden\">'.(\$row['{$colEsc}'] ?? '').'</div></td>' . PHP_EOL;\n";
            } else {
                $rowCode .= "                echo '<td>';\n";
                $rowCode .= "                echo '<span class=\"inline-edit\" data-id=\"'.htmlspecialchars((string)\$row['{$pk}'], ENT_QUOTES, 'UTF-8').'\" data-field=\"{$colEsc}\" data-value=\"'.htmlspecialchars((string)\$row['{$colEsc}'], ENT_QUOTES, 'UTF-8').'\">'.htmlspecialchars((string)\$row['{$colEsc}'], ENT_QUOTES, 'UTF-8').'</span>';\n";
                $rowCode .= "                echo '</td>' . PHP_EOL;\n";
            }
        }

        $searchFieldsStr = !empty($searchFields) ? implode("` LIKE :search OR `", $searchFields) . "`" : '';
        $searchCondition = $searchFieldsStr !== '' ? " AND (`$searchFieldsStr` LIKE :search)" : '';

        $tableEsc = $this->e($table);
        $pkEsc    = $this->e($pk);

        $btnView   = $t('crud.view');
        $btnEdit   = $t('crud.edit');
        $btnDelete = $t('crud.delete');
        $txtSearch = $t('crud.search');
        $txtFake   = $t('crud.generate_fake');
        $txtNoRec  = $t('crud.no_records');

        // Correção: O HTML não deve ter PHP inline com window.CAN_UPDATE.
        // Usamos classes btn-edit e btn-delete para o JavaScript esconder os botões.
        
        return <<<PHP
<?php
namespace $ns;
use PDO;
class {$class}Grid
{
    private PDO \$pdo;
    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }

    public function render(): string
    {
        \$search = \$_GET['search'] ?? '';
        \$page   = max(1, (int)(\$_GET['page'] ?? 1));
        \$limit  = 10;
        \$offset = (\$page - 1) * \$limit;

        \$where = "WHERE `deleted_at` IS NULL";
        \$params = [];
        if (\$search !== '') {
            \$where .= "$searchCondition";
            \$params[':search'] = "%\$search%";
        }

        \$advancedFilter = json_decode(\$_GET['filter'] ?? '[]', true);
        if (!empty(\$advancedFilter)) {
            foreach (\$advancedFilter as \$rule) {
                if (!empty(\$rule['value'])) {
                    \$col = preg_replace('/[^a-zA-Z0-9_]/', '', \$rule['field']);
                    \$op = in_array(\$rule['op'], ['=', '>', '<', 'LIKE']) ? \$rule['op'] : '=';
                    \$where .= " AND `\$col` \$op ?";
                    \$params[] = \$op === 'LIKE' ? "%{\$rule['value']}%" : \$rule['value'];
                }
            }
        }

        \$stmtCount = \$this->pdo->prepare("SELECT COUNT(*) FROM `$table` \$where");
        \$stmtCount->execute(\$params);
        \$total = (int)\$stmtCount->fetchColumn();
        \$totalPages = (int)ceil(\$total / \$limit);

        \$stmt = \$this->pdo->prepare("SELECT * FROM `$table` \$where LIMIT \$limit OFFSET \$offset");
        \$stmt->execute(\$params);
        \$rows = \$stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        ?>
        <div class="mb-4 flex flex-wrap gap-2">
            <form method="get" action="?" class="flex gap-2 w-full">
                <input type="hidden" name="action" value="view_grid">
                <input type="hidden" name="table" value="$tableEsc">
                <input type="text" name="search" value="<?= htmlspecialchars(\$search, ENT_QUOTES, 'UTF-8') ?>" placeholder="$txtSearch" class="input input-bordered w-full $radius">
                <button type="submit" class="btn btn-primary $radius">Buscar</button>
                <a href="?action=export&table=$tableEsc" class="btn btn-success $radius">CSV</a>
                <a href="?action=export_excel&table=$tableEsc" class="btn btn-success $radius">Excel</a>
                <a href="?action=export_pdf&table=$tableEsc" class="btn btn-error $radius">PDF</a>
                <a href="?action=faker&table=$tableEsc" class="btn btn-secondary $radius">🎲 $txtFake</a>
                <button type="button" class="btn btn-outline $radius" onclick="openFilterModal()">⚙️ Filtros</button>
            </form>
        </div>

        <div class="flex justify-between items-center mb-4 bg-base-100 p-2 rounded shadow-sm">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="select-all" class="checkbox checkbox-primary checkbox-sm" onclick="toggleAll(this)">
                <label for="select-all" class="text-sm font-semibold cursor-pointer">Selecionar Todos</label>
            </div>
            <button type="button" class="btn btn-sm btn-error" onclick="deleteSelected()">🗑️ Excluir Selecionados</button>
        </div>

        <div class="overflow-x-auto bg-base-100 $radius shadow-lg p-4">
            <table class="table $borderWidth w-full" id="grid-table">
                <thead><tr class="bg-base-200">
                    <th class="w-10"></th>
                    <?php $colsCode ?>
                    <th class="text-right">Ações</th>
                </tr></thead>
                <tbody>
                    <?php if(empty(\$rows)): ?>
                        <tr><td colspan="100%" class="text-center py-8 text-base-content/50">$txtNoRec</td></tr>
                    <?php endif; ?>
                    <?php foreach (\$rows as \$row): ?>
                        <tr class="hover">
                            <td class="text-center"><input type="checkbox" class="checkbox checkbox-primary checkbox-sm row-checkbox" value="<?= \$row['$pkEsc'] ?>"></td>
                            <?php $rowCode ?>
                            <td class="text-right whitespace-nowrap">
                                <a href="?action=view_record&table=$tableEsc&id=<?= urlencode(\$row['$pkEsc']) ?>" class="btn btn-sm btn-ghost $radius">$btnView</a>
                                <a href="?action=edit&table=$tableEsc&id=<?= urlencode(\$row['$pkEsc']) ?>" class="btn btn-sm btn-info $radius text-white btn-edit">$btnEdit</a>
                                <a href="?action=delete&table=$tableEsc&id=<?= urlencode(\$row['$pkEsc']) ?>" onclick="return confirm('Excluir?')" class="btn btn-sm btn-error $radius text-white btn-delete">$btnDelete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="flex justify-center gap-2 mt-4">
            <?php for(\$i = 1; \$i <= \$totalPages; \$i++): ?>
                <a href="?action=view_grid&table=$tableEsc&page=<?= \$i ?>&search=<?= urlencode(\$search) ?>" class="btn btn-sm <?= \$i == \$page ? 'btn-active' : '' ?> $radius"><?= \$i ?></a>
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
                if (!confirm(`Excluir \${selected.length} registro(s)?`)) return;
                fetch('?action=delete_multiple', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || ''},
                    body: JSON.stringify({table: '$tableEsc', ids: selected, _csrf: window.CSRF_TOKEN || ''})
                }).then(() => window.location.reload());
            }

            let filterSchema = [];
            function openFilterModal() {
                fetch(`?action=advanced_filter&table=$tableEsc`)
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
                let options = filterSchema.map(c => `<option value="\${c.name}">\${c.name}</option>`).join('');
                div.innerHTML = `
                    <select class="select select-bordered select-sm field">\${options}</select>
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

            // Esconde os botões de editar/excluir se o usuário não tiver permissão
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
                        var table = '$tableEsc';

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
PHP;
    }

    private function renderForm(string $ns, string $class, string $table, array $schema, string $pk, array $fks, string $radius, string $borderWidth, int $formLayout, array $labels, array $showInForm, array $requiredField, array $fieldTypes, array $fieldMasks, string $webhookUrl, callable $t): string
    {
        $fksMap = []; foreach ($fks as $fk) { $fksMap[$fk['column']] = $fk; }
        $fieldsCode = ''; $gridCols = $formLayout === 2 ? 'grid grid-cols-1 md:grid-cols-2 gap-4' : 'space-y-4';
        $fieldsCode .= "        <div class=\"$gridCols\">\n";

        $maskJsCode = '';
        $maskFunctionsEmitted = false;

        $hasRichText = false;
        foreach ($schema as $c) {
            if (($fieldTypes[$c['name']] ?? '') === 'richtext') { $hasRichText = true; break; }
        }

        $totalFields = 0;
        foreach ($schema as $c) {
            if (!isset($showInForm[$c['name']]) && !empty($showInForm)) continue;
            if (in_array($c['name'], Config::$skipInCreate) && in_array($c['name'], Config::$skipInEdit)) continue;
            if ($c['name'] === 'deleted_at') continue;
            $totalFields++;
        }
        $useSteps = $totalFields > 8;
        $stepSize = max(1, (int)ceil($totalFields / 3));
        $currentStep = 1;
        $fieldCount = 0;

        foreach ($schema as $c) {
            if (!isset($showInForm[$c['name']]) && !empty($showInForm)) continue;
            if (in_array($c['name'], Config::$skipInCreate) && in_array($c['name'], Config::$skipInEdit)) continue;
            if ($c['name'] === 'deleted_at') continue;

            $fieldCount++;
            if ($useSteps && ($fieldCount - 1) % $stepSize === 0) {
                if ($fieldCount > 1) $fieldsCode .= "        </div>\n";
                $fieldsCode .= "        <div class=\"step-content\" data-step=\"$currentStep\" style=\"" . ($currentStep > 1 ? 'display:none;' : '') . "\">\n";
                $fieldsCode .= "        <div class=\"$gridCols\">\n";
                $currentStep++;
            }

            $required    = isset($requiredField[$c['name']]) ? 'required' : '';
            $inputType   = $this->inputType($c['type'], $c['name'], $fieldTypes);
            $maskType    = $fieldMasks[$c['name']] ?? '';
            $name        = $c['name'];
            $label       = $labels[$c['name']] ?? ucfirst($name);
            $inputClass  = "input input-bordered $radius $borderWidth w-full";
            $fieldsCode .= "            <div class=\"form-control w-full\"><label class=\"label\"><span class=\"label-text font-semibold\">" . $this->e($label) . "</span></label>\n";

            if (isset($fksMap[$name])) {
                $refTable   = $fksMap[$name]['ref_table'];
                $refCol     = $fksMap[$name]['ref_column'];
                $displayCol = $this->getDisplayColumn($refTable);
                $fieldsCode .= "                <select name=\"$name\" class=\"select select-bordered $radius $borderWidth w-full\" $required>\n                    <option value=\"\">-- Selecione --</option>\n                    <?php \$stmt = \$this->pdo->query(\"SELECT `$refCol`, `$displayCol` FROM `$refTable` WHERE `deleted_at` IS NULL ORDER BY `$displayCol`\"); ?>\n                    <?php foreach (\$stmt->fetchAll(PDO::FETCH_ASSOC) as \$opt): ?>\n                        <?php \$sel = (isset(\$data['$name']) && \$data['$name'] == \$opt['$refCol']) ? 'selected' : ''; ?>\n                        <option value=\"<?= htmlspecialchars((string)\$opt['$refCol'], ENT_QUOTES, 'UTF-8') ?>\" <?= \$sel ?>><?= htmlspecialchars((string)\$opt['$displayCol'], ENT_QUOTES, 'UTF-8') ?></option>\n                    <?php endforeach; ?>\n                </select>\n";
            } elseif ($inputType === 'password') {
                $fieldsCode .= "                <div class=\"relative\"><input type=\"password\" name=\"$name\" id=\"pwd_$name\" value=\"\" class=\"$inputClass\"><button type=\"button\" onclick=\"togglePwd('$name')\" class=\"absolute right-3 top-3\">👁️</button></div><script>function togglePwd(id){var x=document.getElementById('pwd_'+id); x.type=x.type==='password'?'text':'password';}</script>\n";
            } elseif ($inputType === 'file') {
                $fieldsCode .= "                <input type=\"file\" name=\"$name\" class=\"file-input file-input-bordered $radius w-full\" accept=\"image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx\">\n";
            } elseif ($inputType === 'richtext') {
                $fieldsCode .= "                <textarea name=\"$name\" class=\"textarea textarea-bordered $radius $borderWidth w-full h-32 use-tinymce\" $required><?= htmlspecialchars((string)(\$data['$name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>\n";
            } elseif ($inputType === 'textarea') {
                $fieldsCode .= "                <textarea name=\"$name\" class=\"textarea textarea-bordered $radius $borderWidth w-full h-32\" $required><?= htmlspecialchars((string)(\$data['$name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>\n";
            } elseif ($inputType === 'checkbox') {
                $fieldsCode .= "                <div class=\"form-control\"><label class=\"label cursor-pointer justify-start gap-3\"><input type=\"checkbox\" name=\"$name\" value=\"1\" class=\"checkbox checkbox-primary $radius\" <?= !empty(\$data['$name']) ? 'checked' : '' ?>></label></div>\n";
            } else {
                $maskAttr = '';
                if ($maskType) {
                    $maskAttr = "oninput=\"aplicarMascara(this, '" . $this->e($maskType) . "')\"";
                    if (!$maskFunctionsEmitted) {
                        $maskJsCode = <<<JS
            function aplicarMascara(input, tipo) {
                let v = input.value.replace(/\\D/g, '');
                if (tipo === 'cpf') { v = v.replace(/(\\d{3})(\\d)/, '\$1.\$2'); v = v.replace(/(\\d{3})(\\d)/, '\$1.\$2'); v = v.replace(/(\\d{3})(\\d{1,2})\$/, '\$1-\$2'); }
                else if (tipo === 'cnpj') { v = v.replace(/^(\\d{2})(\\d)/, '\$1.\$2'); v = v.replace(/^(\\d{2})\\.(\\d{3})(\\d)/, '\$1.\$2.\$3'); v = v.replace(/^(\\d{2})\\.(\\d{3})\\.(\\d{3})(\\d)/, '\$1.\$2.\$3/\$4'); v = v.replace(/^(\\d{2})\\.(\\d{3})\\.(\\d{3})\\/(\\d{4})(\\d)/, '\$1.\$2.\$3/\$4-\$5'); }
                else if (tipo === 'telefone') { v = v.replace(/^(\\d{2})(\\d)/, '(\$1) \$2'); v = v.replace(/(\\d{4})(\\d)/, '\$1-\$2'); }
                else if (tipo === 'cep') { v = v.replace(/^(\\d{5})(\\d)/, '\$1-\$2'); }
                else if (tipo === 'data') { v = v.replace(/^(\\d{2})(\\d)/, '\$1/\$2'); v = v.replace(/^(\\d{2})\\/(\\d{2})(\\d)/, '\$1/\$2/\$3'); }
                else if (tipo === 'hora') { v = v.replace(/^(\\d{2})(\\d)/, '\$1:\$2'); }
                input.value = v;
            }
JS;
                        $maskFunctionsEmitted = true;
                    }
                }
                $fieldsCode .= "                <input type=\"$inputType\" name=\"$name\" value=\"<?= htmlspecialchars((string)(\$data['$name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>\" class=\"$inputClass\" $required $maskAttr>\n";
            }
            $fieldsCode .= "            </div>\n";
        }
        if ($useSteps) {
            $fieldsCode .= "        </div>\n";
            $fieldsCode .= "        <div class=\"flex justify-between mt-6\">\n";
            $fieldsCode .= "            <button type=\"button\" class=\"btn btn-ghost\" onclick=\"prevStep()\">← Anterior</button>\n";
            $fieldsCode .= "            <span class=\"self-center font-bold\" id=\"step-indicator\">Etapa 1 de " . ($currentStep - 1) . "</span>\n";
            $fieldsCode .= "            <button type=\"button\" class=\"btn btn-primary\" onclick=\"nextStep()\">Próximo →</button>\n";
            $fieldsCode .= "        </div>\n";
            $fieldsCode .= "        <script>\n";
            $fieldsCode .= "            let currentStep = 1;\n";
            $fieldsCode .= "            const totalSteps = " . ($currentStep - 1) . ";\n";
            $fieldsCode .= "            function showStep(n) { document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none'); document.querySelector('.step-content[data-step=\"'+n+'\"]').style.display = 'block'; document.getElementById('step-indicator').textContent = 'Etapa '+n+' de '+totalSteps; }\n";
            $fieldsCode .= "            function nextStep() { if (currentStep < totalSteps) { currentStep++; showStep(currentStep); } }\n";
            $fieldsCode .= "            function prevStep() { if (currentStep > 1) { currentStep--; showStep(currentStep); } }\n";
            $fieldsCode .= "        </script>\n";
        } else {
            $fieldsCode .= "        </div>\n";
        }

        $tinyMceScript = '';
        if ($hasRichText) {
            $tinyMceScript = '<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script><script>tinymce.init({selector: ".use-tinymce", height: 300, menubar: false, plugins: "lists link image table code help wordcount", toolbar: "undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code"});</script>';
        }

        $skipInEditExpr   = '[' . implode(',', array_map(fn($c) => var_export($c, true), Config::$skipInEdit)) . ']';
        $skipInCreateExpr = '[' . implode(',', array_map(fn($c) => var_export($c, true), Config::$skipInCreate)) . ']';
        $fieldMasksExpr   = '[' . implode(',', array_map(fn($k, $v) => var_export($k, true) . ' => ' . var_export($v, true), array_keys($fieldMasks), array_values($fieldMasks))) . ']';
        $fieldTypesExpr   = '[' . implode(',', array_map(fn($k, $v) => var_export($k, true) . ' => ' . var_export($v, true), array_keys($fieldTypes), array_values($fieldTypes))) . ']';
        $webhookUrlEsc    = addslashes($webhookUrl);
        $btnSave          = $t('crud.save');

        return <<<PHP
<?php
namespace $ns;
use PDO;
class {$class}Form {
    private PDO \$pdo;
    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }

    public function render(array \$data = []): string {
        ob_start(); ?>
        $tinyMceScript
        <form method="post" enctype="multipart/form-data">
$fieldsCode
            <div class="flex justify-end gap-2 pt-6 border-t mt-6"><button type="submit" class="btn btn-success $radius text-white">$btnSave</button></div>
        </form>
        <script>
$maskJsCode
        </script>
        <?php return ob_get_clean();
    }

    public function handle(\$_post, \$_files, int \$userId = 0): void {
        \$id = \$_post['$pk'] ?? null;
        \$sets = []; \$params = [];

        \$allowedExt  = ['jpg','jpeg','png','gif','webp','pdf','doc','docx'];
        \$allowedMime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf', 'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        foreach (\$_files as \$key => \$file) {
            if ((\$file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            if (\$file['size'] > 5 * 1024 * 1024) continue;
            \$ext = strtolower(pathinfo(\$file['name'], PATHINFO_EXTENSION));
            if (!in_array(\$ext, \$allowedExt, true)) continue;
            \$mime = mime_content_type(\$file['tmp_name']) ?: '';
            if (!in_array(\$mime, \$allowedMime, true)) continue;
            \$nome = bin2hex(random_bytes(16)) . '.' . \$ext;
            \$caminho = 'uploads/' . \$nome;
            if (move_uploaded_file(\$file['tmp_name'], __DIR__ . '/../../public/' . \$caminho)) { \$_post[\$key] = \$caminho; }
        }

        \$fieldMasks = $fieldMasksExpr;
        \$errosValidacao = [];
        foreach (\$fieldMasks as \$col => \$tipo) {
            if (!isset(\$_post[\$col])) continue;
            \$valor = trim((string)\$_post[\$col]);
            if (\$valor === '') continue;
            if (!\PhpGen\Validation\BrValidator::validateByType(\$tipo, \$valor)) { \$errosValidacao[] = \$col . ' (' . \$tipo . ')'; }
        }
        if (!empty(\$errosValidacao)) { throw new \InvalidArgumentException('Validação falhou: ' . implode(', ', \$errosValidacao)); }

        \$introspector = new \PhpGen\Database\Introspector(\$this->pdo);
        \$dupMsg = \$introspector->isDuplicate('$table', \$_post, '$pk', \$id ? (int)\$id : null);
        if (\$dupMsg !== null) { throw new \InvalidArgumentException(\$dupMsg); }

        \$skipInCreate = $skipInCreateExpr;
        \$skipInEdit   = $skipInEditExpr;
        \$fieldTypes   = $fieldTypesExpr;

        foreach (\$_post as \$k => \$v) {
            if (\$k === '$pk') continue;
            if (\$k === '_csrf') continue;
            if (in_array(\$k, \$skipInCreate, true)) continue;
            if (in_array(\$k, \$skipInEdit, true)) continue;
            if (in_array(\$k, ['senha','password'], true) && empty(\$v)) continue;
            if (in_array(\$k, ['senha','password'], true) && !empty(\$v)) \$v = password_hash(\$v, PASSWORD_DEFAULT);
            if (isset(\$fieldTypes[\$k]) && \$fieldTypes[\$k] === 'richtext' && !empty(\$v)) { \$v = \PhpGen\Validation\HtmlSanitizer::sanitize((string)\$v); }
            \$sets[] = "`\$k` = ?";
            \$params[] = \$v;
        }

        if (empty(\$sets)) { return; }

        if (\$id) {
            \$params[] = \$id;
            \$sql = "UPDATE `$table` SET " . implode(', ', \$sets) . " WHERE `$pk` = ?";
            \$this->pdo->prepare(\$sql)->execute(\$params);
            \$this->log(\$userId, 'UPDATE', '$table', (int)\$id);
        } else {
            \$sql = "INSERT INTO `$table` SET " . implode(', ', \$sets);
            \$this->pdo->prepare(\$sql)->execute(\$params);
            \$this->log(\$userId, 'CREATE', '$table', (int)\$this->pdo->lastInsertId());
        }
    }

    private function log(int \$userId, string \$action, string \$table, int \$recordId): void {
        try {
            \$stmt = \$this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
            \$stmt->execute([\$userId, \$action, \$table, \$recordId, \$_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Throwable \$e) {}
        \$webhookUrl = '$webhookUrlEsc';
        if (!empty(\$webhookUrl)) {
            \$payload = json_encode(['action' => \$action, 'table' => '$table', 'id' => \$recordId]);
            \$ch = curl_init(\$webhookUrl); curl_setopt_array(\$ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => \$payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            curl_exec(\$ch); curl_close(\$ch);
        }
    }
}
PHP;
    }

    private function renderDelete(string $ns, string $class, string $table, string $pk, string $webhookUrl): string
    {
        $webhookUrlEsc = addslashes($webhookUrl);
        return <<<PHP
<?php
namespace $ns;
use PDO;
class {$class}Delete {
    private PDO \$pdo;
    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }
    public function handle(\$id, int \$userId = 0): void {
        \$stmt = \$this->pdo->prepare("UPDATE `$table` SET `deleted_at` = NOW() WHERE `$pk` = ?");
        \$stmt->execute([\$id]);
        try {
            \$stmtLog = \$this->pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, 'DELETE', ?, ?, ?)");
            \$stmtLog->execute([\$userId, '$table', \$id, \$_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Throwable \$e) {}
        \$webhookUrl = '$webhookUrlEsc';
        if (!empty(\$webhookUrl)) {
            \$payload = json_encode(['action' => 'DELETE', 'table' => '$table', 'id' => \$id]);
            \$ch = curl_init(\$webhookUrl); curl_setopt_array(\$ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => \$payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            curl_exec(\$ch); curl_close(\$ch);
        }
    }
}
PHP;
    }

    private function renderView(string $ns, string $class, string $table, array $schema, string $pk, string $radius, array $labels, array $options, callable $t): string
    {
        $fieldsCode = '';
        $fieldTypes = $options['field_types'] ?? [];
        foreach ($schema as $c) {
            if ($c['name'] === 'deleted_at') continue;
            $label = $labels[$c['name']] ?? ucfirst($c['name']);
            $labelEsc = $this->e($label);
            $colEsc = $this->e($c['name']);
            $inputType = $this->inputType($c['type'], $c['name'], $fieldTypes);

            if ($inputType === 'file') {
                $fieldsCode .= "            <div class=\"mb-4\"><label class=\"text-sm font-bold text-gray-500\">$labelEsc</label><p class=\"text-lg\"><a href=\"<?= htmlspecialchars((string)\$data['{$colEsc}'], ENT_QUOTES, 'UTF-8') ?>\" target=\"_blank\"><img src=\"<?= htmlspecialchars((string)\$data['{$colEsc}'], ENT_QUOTES, 'UTF-8') ?>\" class=\"w-32 h-32 object-cover rounded\"></a></p></div>\n";
            } elseif ($inputType === 'password') {
                $fieldsCode .= "            <div class=\"mb-4\"><label class=\"text-sm font-bold text-gray-500\">$labelEsc</label><p class=\"text-lg\">********</p></div>\n";
            } elseif ($inputType === 'richtext') {
                $fieldsCode .= "            <div class=\"mb-4\"><label class=\"text-sm font-bold text-gray-500\">$labelEsc</label><div class=\"prose max-w-none text-lg\"><?= \$data['{$colEsc}'] ?? '' ?></div></div>\n";
            } else {
                $fieldsCode .= "            <div class=\"mb-4\"><label class=\"text-sm font-bold text-gray-500\">$labelEsc</label><p class=\"text-lg\"><?= htmlspecialchars((string)\$data['{$colEsc}'], ENT_QUOTES, 'UTF-8') ?></p></div>\n";
            }
        }

        $masterDetailHtml = '';
        $relacionamentos  = $options['relacionamentos'] ?? [];
        foreach ($relacionamentos as $rel) {
            $mdTable = $rel['filha'] ?? '';
            $mdFk    = $rel['fk']    ?? '';
            if (!$mdTable || !$mdFk) continue;
            try { $this->db->assertValidTable($mdTable); $this->db->assertValidColumn($mdTable, $mdFk); } catch (\Throwable $e) { continue; }

            $mdSchema = $this->db->tableSchema($mdTable);
            $mdPk     = $this->db->primaryKey($mdSchema) ?? 'id';
            $mdShowCols = [];
            foreach ($mdSchema as $mc) {
                if ($mc['name'] === 'deleted_at') continue;
                if ($this->inputType($mc['type'], $mc['name'], []) === 'password') continue;
                $mdShowCols[] = $mc['name'];
            }
            $mdHeaders = ''; foreach ($mdShowCols as $col) $mdHeaders .= "                                <th>" . $this->e(ucfirst($col)) . "</th>\n";
            $mdCells = ''; foreach ($mdShowCols as $col) $mdCells .= "                                    <td><?= htmlspecialchars((string)\$filho['$col'], ENT_QUOTES, 'UTF-8') ?></td>\n";
            $mdTableEsc = $this->e($mdTable); $mdFkEsc = $this->e($mdFk); $mdPkEsc = $this->e($mdPk); $btnEdit = $t('crud.edit');

            $masterDetailHtml .= <<<PHP

                <div class="mt-8 border-t pt-6">
                    <h3 class="text-xl font-bold text-primary mb-4">Registros Relacionados: $mdTableEsc</h3>
                    <div class="overflow-x-auto bg-base-100 $radius shadow">
                        <table class="table table-zebra w-full">
                            <thead><tr class="bg-base-200">
$mdHeaders
                                <th>Ações</th>
                            </tr></thead>
                            <tbody>
                                <?php \$stmtMd = \$this->pdo->prepare("SELECT * FROM `$mdTableEsc` WHERE `$mdFkEsc` = ? AND `deleted_at` IS NULL"); \$stmtMd->execute([\$id]); \$filhos = \$stmtMd->fetchAll(PDO::FETCH_ASSOC); if (empty(\$filhos)): ?>
                                    <tr><td colspan="<?= count([$mdShowCols]) + 1 ?>">Nenhum registro relacionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach (\$filhos as \$filho): ?>
                                    <tr>
$mdCells
                                        <td><a href="?action=edit&table=$mdTableEsc&id=<?= urlencode(\$filho['$mdPkEsc']) ?>" class="btn btn-sm btn-info text-white">$btnEdit</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
PHP;
        }

        $pkEsc = $this->e($pk);
        return <<<PHP
<?php
namespace $ns;
use PDO;
class {$class}View {
    private PDO \$pdo;
    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }
    public function render(\$id): string {
        \$stmt = \$this->pdo->prepare("SELECT * FROM `$table` WHERE `$pkEsc` = ?");
        \$stmt->execute([\$id]);
        \$data = \$stmt->fetch(PDO::FETCH_ASSOC);
        if (!\$data) return "<p>Registro não encontrado.</p>";
        ob_start(); ?>
        <div class="bg-base-100 p-6 $radius shadow-lg">
$fieldsCode
$masterDetailHtml
        </div>
        <?php return ob_get_clean();
    }
}
PHP;
    }

    private function renderExport(string $ns, string $class, string $table, array $schema, array $labels, array $showInGrid): string
    {
        $cols = $this->exportableColumns($schema, $showInGrid);
        if (empty($cols)) return "<?php\nnamespace $ns;\nuse PDO;\nclass {$class}Export { public function download(): void { http_response_code(204); exit; } }";

        $colsBacktick = implode("`, `", $cols);
        $colsPlain = implode(', ', $cols);

        return <<<PHP
<?php
namespace $ns;
use PDO;
class {$class}Export {
    private PDO \$pdo; public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }
    public function download(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="$table.csv"');
        \$output = fopen('php://output', 'w');
        fwrite(\$output, "\xEF\xBB\xBF");
        fputcsv(\$output, [$colsPlain], ';');
        \$stmt = \$this->pdo->query("SELECT `$colsBacktick` FROM `$table` WHERE `deleted_at` IS NULL");
        while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) fputcsv(\$output, \$row, ';');
        fclose(\$output); exit;
    }
}
PHP;
    }

    private function renderExportExcel(string $ns, string $class, string $table, array $schema, array $labels, array $showInGrid): string
    {
        $cols = $this->exportableColumns($schema, $showInGrid);
        if (empty($cols)) return "<?php\nnamespace $ns;\nclass {$class}ExportExcel { public function download(): void { http_response_code(204); exit; } }";

        $colsBacktick = implode("`, `", $cols);
        $colsArrayExpr = var_export($cols, true);

        return <<<PHP
<?php
namespace $ns;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PDO;

class {$class}ExportExcel {
    private PDO \$pdo;
    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }

    public function download(): void {
        if (!class_exists(Spreadsheet::class)) {
            http_response_code(500);
            die('PhpSpreadsheet não instalado. Rode: composer require phpoffice/phpspreadsheet');
        }

        \$cols = $colsArrayExpr;

        \$spreadsheet = new Spreadsheet();
        \$sheet = \$spreadsheet->getActiveSheet();

        \$colLetter = 'A';
        foreach (\$cols as \$col) {
            \$sheet->setCellValue(\$colLetter . '1', ucfirst(\$col));
            \$sheet->getStyle(\$colLetter . '1')->getFont()->setBold(true);
            \$sheet->getStyle(\$colLetter . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
            \$sheet->getColumnDimension(\$colLetter)->setAutoSize(true);
            \$colLetter++;
        }

        \$stmt = \$this->pdo->query("SELECT `$colsBacktick` FROM `$table` WHERE `deleted_at` IS NULL");
        \$rowNum = 2;
        while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
            \$colLetter = 'A';
            foreach (\$cols as \$col) {
                \$sheet->setCellValue(\$colLetter . \$rowNum, \$row[\$col] ?? '');
                \$sheet->getStyle(\$colLetter . \$rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                \$colLetter++;
            }
            \$rowNum++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="$table.xlsx"');
        \$writer = new Xlsx(\$spreadsheet);
        \$writer->save('php://output');
        exit;
    }
}
PHP;
    }

    private function renderExportPdf(string $ns, string $class, string $table, array $schema, array $labels, array $showInGrid): string
    {
        $cols = $this->exportableColumns($schema, $showInGrid);
        if (empty($cols)) return "<?php\nnamespace $ns;\nclass {$class}ExportPdf { public function download(): void { http_response_code(204); exit; } }";

        $colsBacktick = implode("`, `", $cols);
        $colsArrayExpr = var_export($cols, true);
        $headersHtml = '';
        foreach ($cols as $col) {
            $headersHtml .= '<th>' . $this->e(ucfirst($col)) . '</th>';
        }
        $tableLabel = $this->e(ucfirst($table));

        return <<<PHP
<?php
namespace $ns;
use Dompdf\Dompdf;
use PDO;

class {$class}ExportPdf {
    private PDO \$pdo;
    public function __construct(PDO \$pdo) { \$this->pdo = \$pdo; }

    public function download(): void {
        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            die('Dompdf não instalado. Rode: composer require dompdf/dompdf');
        }

        \$cols = $colsArrayExpr;

        \$html = '<html><head><meta charset="utf-8"><style>';
        \$html .= 'body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; }';
        \$html .= 'table { width: 100%; border-collapse: collapse; }';
        \$html .= 'th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }';
        \$html .= 'th { background-color: #f0f0f0; font-weight: bold; }';
        \$html .= 'h1 { text-align: center; color: #333; }';
        \$html .= '.footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; }';
        \$html .= '</style></head><body>';
        \$html .= '<h1>Relatório: $tableLabel</h1>';
        \$html .= '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';
        \$html .= '<table><thead><tr>$headersHtml</tr></thead><tbody>';

        \$stmt = \$this->pdo->query("SELECT `$colsBacktick` FROM `$table` WHERE `deleted_at` IS NULL");
        while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
            \$html .= '<tr>';
            foreach (\$cols as \$col) {
                \$html .= '<td>' . htmlspecialchars((string)(\$row[\$col] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            \$html .= '</tr>';
        }

        \$html .= '</tbody></table>';
        \$html .= '<div class="footer">phpgen - Sistema de Gestão</div>';
        \$html .= '</body></html>';

        \$dompdf = new Dompdf();
        \$dompdf->loadHtml(\$html);
        \$dompdf->setPaper('A4', 'landscape');
        \$dompdf->render();
        \$dompdf->stream("$table.pdf", ["Attachment" => true]);
        exit;
    }
}
PHP;
    }
}