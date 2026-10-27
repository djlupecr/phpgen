<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class AuditController
{
    public function index(): void
    {
        global $twig;
        $db = new Introspector();
        $db->ensureAuditLogsTable();

        $fUser = trim($_GET['user'] ?? '');
        $fAction = trim($_GET['action_f'] ?? '');
        $fTable = trim($_GET['table_f'] ?? '');
        $fFrom = trim($_GET['from'] ?? '');
        $fTo = trim($_GET['to'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $where = []; $params = [];
        if ($fUser) { $where[] = "a.user_id = ?"; $params[] = (int)$fUser; }
        if ($fAction) { $where[] = "a.action = ?"; $params[] = $fAction; }
        if ($fTable) { $where[] = "a.table_name = ?"; $params[] = $fTable; }
        if ($fFrom) { $where[] = "a.created_at >= ?"; $params[] = $fFrom . ' 00:00:00'; }
        if ($fTo) { $where[] = "a.created_at <= ?"; $params[] = $fTo . ' 23:59:59'; }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmtCount = $db->pdo()->prepare("SELECT COUNT(*) FROM audit_logs a $whereSql");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $db->pdo()->prepare("SELECT a.*, u.nome AS user_nome, u.email AS user_email FROM audit_logs a LEFT JOIN usuarios u ON u.id = a.user_id $whereSql ORDER BY a.id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users = $db->pdo()->query("SELECT id, nome, email FROM usuarios ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC);
        $actions = $db->pdo()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(\PDO::FETCH_COLUMN);
        $tablesWithLogs = $db->pdo()->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetchAll(\PDO::FETCH_COLUMN);

        echo $twig->render('audit.twig', [
            'logs' => $logs, 'total' => $total, 'page' => $page,
            'totalPages' => ceil($total / $limit),
            'users' => $users, 'actions' => $actions, 'tablesWithLogs' => $tablesWithLogs,
            'filters' => ['user' => $fUser, 'action' => $fAction, 'table' => $fTable, 'from' => $fFrom, 'to' => $fTo],
        ]);
    }
}