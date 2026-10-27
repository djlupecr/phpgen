<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class CrudController
{
    private function getTable(): array
    {
        $table = $_GET['table'] ?? 'usuarios';
        $class = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $table)));
        return [$table, $class];
    }

    public function viewGrid(): void
    {
        [$table, $class] = $this->getTable();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        if (!$db->can($userId, $table, 'SELECT')) {
            http_response_code(403);
            die("Você não tem permissão para visualizar '$table'.");
        }
        $ns = Config::$generatedNamespace;
        $f = __DIR__ . "/../../../generated/$table/{$class}Grid.php";
        if (!file_exists($f)) {
            echo "Grid não foi gerado.";
            return;
        }
        require_once $f;
        $cls = $ns . '\\' . $class . 'Grid';
        $grid = new $cls($db->pdo());
        $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
        $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];
        $theme = $cfg['theme'] ?? 'light';
        $canInsert = $db->can($userId, $table, 'INSERT');
        $canUpdate = $db->can($userId, $table, 'UPDATE');
        $canDelete = $db->can($userId, $table, 'DELETE');
        echo "<html><head><meta charset='utf-8'><title>$table</title><link href='https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css' rel='stylesheet' /><script src='https://cdn.tailwindcss.com'></script></head>";
        echo "<body data-theme='$theme' class='bg-base-200 min-h-screen p-8'><div class='max-w-6xl mx-auto'><div class='flex justify-between mb-6'><h1 class='text-3xl font-bold text-base-content'>$table</h1><div><a href='?action=tables' class='btn btn-ghost'>Voltar</a>";
        if ($canInsert) { echo " <a href='?action=add&table=$table' class='btn btn-primary'>+ Adicionar</a>"; }
        echo "</div></div><script>window.CSRF_TOKEN = '" . csrfToken() . "';</script><script>window.CAN_UPDATE = " . ($canUpdate ? 'true' : 'false') . ";</script><script>window.CAN_DELETE = " . ($canDelete ? 'true' : 'false') . ";</script>";
        echo $grid->render();
        echo "</div></body></html>";
    }

    public function viewRecord(): void
    {
        [$table, $class] = $this->getTable();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        if (!$db->can($userId, $table, 'SELECT')) {
            http_response_code(403);
            die("Acesso negado.");
        }
        $ns = Config::$generatedNamespace;
        $f = __DIR__ . "/../../../generated/$table/{$class}View.php";
        if (!file_exists($f)) { echo "View não gerada."; return; }
        require_once $f;
        $cls = $ns . '\\' . $class . 'View';
        $view = new $cls($db->pdo());
        echo "<html><head><link href='https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css' rel='stylesheet' /><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-base-200 p-8'><div class='max-w-xl mx-auto'><a href='?action=view_grid&table=$table' class='btn btn-ghost mb-4'>Voltar</a>";
        echo $view->render($_GET['id'] ?? 0);
        echo "</div></body></html>";
    }

    public function add(): void { $this->form('add'); }
    public function edit(): void { $this->form('edit'); }

    private function form(string $mode): void
    {
        [$table, $class] = $this->getTable();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $required = ($mode === 'add') ? 'INSERT' : 'UPDATE';
        if (!$db->can($userId, $table, $required)) {
            http_response_code(403);
            die("Acesso negado.");
        }
        $ns = Config::$generatedNamespace;
        $f = __DIR__ . "/../../../generated/$table/{$class}Form.php";
        if (!file_exists($f)) { echo "Form não gerado."; return; }
        require_once $f;
        $cls = $ns . '\\' . $class . 'Form';
        $form = new $cls($db->pdo());
        $formError = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['_csrf'])) csrfCheck();
            try {
                $form->handle($_POST, $_FILES, $userId);
                header("Location: ?action=view_grid&table=$table");
                exit;
            } catch (\InvalidArgumentException $e) {
                $formError = $e->getMessage();
            } catch (\Throwable $e) {
                $formError = 'Erro: ' . $e->getMessage();
            }
        }

        $data = [];
        if ($mode === 'edit' && isset($_GET['id'])) {
            $schema = $db->tableSchema($table);
            $pk = $db->primaryKey($schema) ?? 'id';
            $stmt = $db->pdo()->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        } elseif ($formError) {
            $data = $_POST;
        }

        $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
        $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];
        $theme = $cfg['theme'] ?? 'light';

        echo "<html><head><meta charset='utf-8'><title>$table</title><link href='https://cdn.jsdelivr.net/npm/daisyui@4.7.2/dist/full.min.css' rel='stylesheet' /><script src='https://cdn.tailwindcss.com'></script></head><body data-theme='$theme' class='bg-base-200 min-h-screen p-8'>";
        echo "<div class='max-w-xl mx-auto'><div class='flex items-center justify-between mb-6'><h1 class='text-2xl font-bold text-base-content'>" . ($mode === 'add' ? 'Adicionar' : 'Editar') . " em <span class='text-primary'>$table</span></h1><a href='?action=view_grid&table=$table' class='btn btn-ghost'>Voltar</a></div><div class='card bg-base-100 shadow-xl p-6'>";
        $html = $form->render($data);
        $html = str_replace('</form>', '<input type="hidden" name="_csrf" value="' . csrfToken() . '"></form>', $html);
        if ($mode === 'edit') {
            $html = str_replace('</form>', '<input type="hidden" name="id" value="' . htmlspecialchars($_GET['id'], ENT_QUOTES) . '"></form>', $html);
        }
        if (!empty($formError)) {
            $html = '<div class="alert alert-error mb-4"><span>' . htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') . '</span></div>' . $html;
        }
        echo $html;
        echo "</div></div></body></html>";
    }

    public function delete(): void
    {
        [$table, $class] = $this->getTable();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        if (!$db->can($userId, $table, 'DELETE')) {
            http_response_code(403);
            die("Acesso negado.");
        }
        $ns = Config::$generatedNamespace;
        $f = __DIR__ . "/../../../generated/$table/{$class}Delete.php";
        if (!file_exists($f)) { echo "Delete não gerado."; return; }
        require_once $f;
        $cls = $ns . '\\' . $class . 'Delete';
        $del = new $cls($db->pdo());
        $del->handle($_GET['id'], $userId);
        header("Location: ?action=view_grid&table=$table");
        exit;
    }

    public function deleteMultiple(): void
    {
        header('Content-Type: application/json');
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $input = json_decode(file_get_contents('php://input'), true);
        $table = $input['table'] ?? '';
        $ids = $input['ids'] ?? [];
        $db = new Introspector();
        try {
            $db->assertValidTable($table);
            if (!$db->can($userId, $table, 'DELETE')) throw new \Exception('Permissão negada');
            if (empty($ids) || !is_array($ids)) throw new \Exception('Nenhum ID informado');
            $schema = $db->tableSchema($table);
            $pk = $db->primaryKey($schema) ?? 'id';
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $db->pdo()->beginTransaction();
            $db->pdo()->prepare("UPDATE `$table` SET `deleted_at` = NOW() WHERE `$pk` IN ($ph)")->execute($ids);
            foreach ($ids as $id) {
                $db->log($userId, 'DELETE_MULTIPLE', $table, (int)$id);
            }
            $db->pdo()->commit();
            echo json_encode(['status' => 'success', 'count' => count($ids)]);
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveInline(): void
    {
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $table = $_POST['table'] ?? '';
        $id = $_POST['id'] ?? 0;
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        $db = new Introspector();
        try {
            $db->assertValidTable($table);
            $db->assertValidColumn($table, $field);
            if (!$db->can($userId, $table, 'UPDATE')) {
                http_response_code(403);
                echo "Permissão negada";
                exit;
            }
            $schema = $db->tableSchema($table);
            $pk = $db->primaryKey($schema) ?? 'id';
            if ($field === 'deleted_at' || $field === $pk || $field === 'created_at') {
                http_response_code(400);
                echo "Campo não editável inline.";
                exit;
            }
            $fieldTypes = [];
            $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
            if (file_exists($cfgFile)) {
                $cfg = json_decode(file_get_contents($cfgFile), true);
                $fieldTypes = $cfg['field_types'] ?? [];
            }
            if (($fieldTypes[$field] ?? '') === 'richtext' && $value !== '') {
                $value = \PhpGen\Validation\HtmlSanitizer::sanitize((string)$value);
            }
            $db->pdo()->prepare("UPDATE `$table` SET `$field` = ? WHERE `$pk` = ?")->execute([$value, $id]);
            echo "OK";
        } catch (\Throwable $e) {
            http_response_code(400);
            echo "Erro: " . $e->getMessage();
        }
    }

    public function advancedFilter(): void
    {
        header('Content-Type: application/json');
        $table = $_GET['table'] ?? '';
        $db = new Introspector();
        try {
            $db->assertValidTable($table);
            echo json_encode(['status' => 'success', 'schema' => $db->tableSchema($table)]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function faker(): void
    {
        $table = $_GET['table'] ?? '';
        $db = new Introspector();
        try { $db->assertValidTable($table); } catch (\Throwable $e) { die("Tabela inválida."); }
        $schema = $db->tableSchema($table);
        for ($i = 0; $i < 20; $i++) {
            $sets = []; $params = [];
            foreach ($schema as $c) {
                if ($c['key'] === 'PRI' || in_array($c['name'], Config::$skipInCreate, true) || $c['name'] === 'deleted_at') continue;
                $name = strtolower($c['name']);
                $val = '';
                if (str_contains($name, 'nome') || str_contains($name, 'name')) $val = "Usuário Teste " . rand(1, 999);
                elseif (str_contains($name, 'email')) $val = "teste" . rand(1, 999) . "@exemplo.com";
                elseif (str_contains($name, 'cpf')) $val = rand(100, 999) . "." . rand(100, 999) . "." . rand(100, 999) . "-" . rand(10, 99);
                elseif (str_contains($name, 'telefone') || str_contains($name, 'celular')) $val = "(" . rand(11, 99) . ") 9" . rand(1000, 9999) . "-" . rand(1000, 9999);
                elseif (str_contains($name, 'ativo')) $val = rand(0, 1);
                elseif (str_contains($name, 'preco') || str_contains($name, 'valor')) $val = rand(10, 500) + 0.90;
                elseif (str_contains($name, 'estoque')) $val = rand(1, 100);
                elseif ($c['type'] === 'int' || $c['type'] === 'tinyint') $val = rand(1, 100);
                elseif ($c['type'] === 'date') $val = date('Y-m-d', strtotime('-' . rand(1, 365) . ' days'));
                elseif ($c['type'] === 'datetime') $val = date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'));
                else $val = "Texto de teste " . rand(1, 100);
                $sets[] = "`{$c['name']}` = ?";
                $params[] = $val;
            }
            try { $db->pdo()->prepare("INSERT INTO `$table` SET " . implode(', ', $sets))->execute($params); } catch (\Throwable $e) {}
        }
        header("Location: ?action=view_grid&table=$table");
    }
}