<?php
namespace PhpGen\Http;

class Router
{
    private array $routes = [];
    private array $publicRoutes = [
        'health', 'login', 'logout', 'logout_api',
        'openapi', 'api_docs', '2fa_verify', 'api',
    ];

    public function add(string $action, string $controller, string $method): void
    {
        $this->routes[$action] = [$controller, $method];
    }

    public function dispatch(string $action): void
    {
        if (!isset($this->routes[$action])) {
            http_response_code(404);
            die("Ação desconhecida: $action");
        }

        // Sessão obrigatória (exceto rotas públicas)
        if (!in_array($action, $this->publicRoutes, true)) {
            if (!isset($_SESSION['user'])) {
                header('Location: ?action=login');
                exit;
            }

            // Recarrega o usuário do banco (evita dados desatualizados)
            $db = new \PhpGen\Database\Introspector();
            $stmt = $db->pdo()->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user']['id']]);
            $fresh = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$fresh) {
                session_destroy();
                header('Location: ?action=login');
                exit;
            }

            $_SESSION['user'] = $fresh;

            // Injeta o usuário no Twig
            global $twig;
            if (isset($twig)) {
                $twig->addGlobal('session_user', $fresh);
                $twig->addGlobal('is_admin', in_array('admin', $db->getUserRoles((int)$fresh['id']), true));
            }
        }

        [$controllerClass, $method] = $this->routes[$action];
        $controller = new $controllerClass();
        $controller->$method();
    }
}