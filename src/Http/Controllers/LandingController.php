<?php
namespace PhpGen\Http\Controllers;

class LandingController
{
    public function index(): void
    {
        global $twig;

        // Se o usuário já está logado, redireciona para o dashboard
        if (isset($_SESSION['user'])) {
            header('Location: ?action=dashboard');
            exit;
        }

        // Se o sistema não está instalado, redireciona para o instalador
        $configFile = __DIR__ . '/../../../src/Config.php';
        if (!file_exists($configFile) || filesize($configFile) < 500) {
            header('Location: install.php');
            exit;
        }

        echo $twig->render('landing.twig', [
            'app_name' => \PhpGen\Config::$appName ?? 'PHPGEN',
            'year' => date('Y'),
        ]);
    }
}