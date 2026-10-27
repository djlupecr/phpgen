<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Config;
use PhpGen\Database\Introspector;

class DesignerController
{
    public function index(): void
    {
        global $twig;
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $table = $_GET['table'] ?? '';

        if (empty($table)) {
            http_response_code(400);
            die("Tabela não informada. Use ?action=designer&table=usuarios");
        }

        try {
            $db->assertValidTable($table);
        } catch (\Throwable $e) {
            http_response_code(404);
            die("Tabela inválida: " . htmlspecialchars($e->getMessage()));
        }

        if (!$db->can($userId, $table, 'SELECT')) {
            http_response_code(403);
            die("Acesso negado.");
        }

        $schema = $db->tableSchema($table);
        $allFields = array_column($schema, 'name');

        // Lê a configuração atual (se existir)
        $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
        $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];

        $formFields = [];
        if (!empty($cfg['field_order'])) {
            $formFields = array_map('trim', explode(',', $cfg['field_order']));
        } else {
            $formFields = $allFields;
        }

        // Campos disponíveis = todos que não estão no form
        $availableFields = array_values(array_diff($allFields, $formFields));

        // Labels customizadas
        $labels = $cfg['labels'] ?? [];

        echo $twig->render('designer.twig', [
            'table' => $table,
            'available_fields' => $availableFields,
            'form_fields' => $formFields,
            'labels' => $labels,
            'csrf' => csrfToken(),
        ]);
    }

    public function save(): void
    {
        header('Content-Type: application/json');
        csrfCheck();
        $userId = (int)$_SESSION['user']['id'];
        $db = new Introspector();
        $table = $_GET['table'] ?? '';

        try {
            $db->assertValidTable($table);
        } catch (\Throwable $e) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tabela inválida']);
            return;
        }

        if (!$db->can($userId, $table, 'UPDATE')) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permissão negada']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $formFields = $input['form_fields'] ?? [];

        if (!is_array($formFields)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'form_fields deve ser um array']);
            return;
        }

        // Salva no config.json
        $cfgFile = __DIR__ . "/../../../generated/$table/config.json";
        $cfg = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) : [];
        $cfg['field_order'] = implode(',', $formFields);
        $cfg['updated_at'] = date('c');
        file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['status' => 'success', 'message' => 'Layout salvo!', 'field_order' => $cfg['field_order']]);
    }
}