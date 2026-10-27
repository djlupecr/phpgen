<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Http\Router;
use PhpGen\Http\Controllers\{
    AuthController,
    TwoFactorController,
    DashboardController,
    AuditController,
    BackupController,
    AiController,
    NotificationController,
    TokenController,
    RoleController,
    WebhookController,
    CrudController,
    ApiController,
    ExportController,
    HealthController,
    WizardController,
    DesignerController,
    TenantController,
    LandingController
};

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// ============================================================
// TWIG (GLOBAL — os controllers usam `global $twig`)
// ============================================================
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, ['cache' => false]);

// ============================================================
// HELPERS GLOBAIS
// ============================================================
function jsonSafe($data): string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrfCheck(): void {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        die("CSRF token inválido.");
    }
}

// ============================================================
// FILTRO DE TRADUÇÃO (i18n)
// ============================================================
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'pt_BR';
if (!in_array($lang, ['pt_BR', 'en', 'es'])) { $lang = 'pt_BR'; }
$_SESSION['lang'] = $lang;
setcookie('lang', $lang, time() + (86400 * 30), '/');

$translationFile = __DIR__ . '/../locales/' . $lang . '.php';
$translations = file_exists($translationFile) ? require $translationFile : [];
$GLOBALS['translations'] = $translations;

$twig->addFilter(new \Twig\TwigFilter('trans', function ($key) use ($translations) {
    $parts = explode('.', $key);
    $val = $translations;
    foreach ($parts as $p) {
        if (isset($val[$p])) { $val = $val[$p]; } else { return $key; }
    }
    return is_string($val) ? $val : $key;
}));

$twig->addGlobal('current_lang', $lang);
$twig->addGlobal('available_langs', ['pt_BR' => '🇧🇷 PT', 'en' => '🇺🇸 EN', 'es' => '🇪🇸 ES']);
$twig->addGlobal('csrf_token', csrfToken());

// ============================================================
// FALLBACK AUTHORIZATION (para o header JWT)
// ============================================================
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (!empty($_GET['api_token'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['api_token'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
}

// ============================================================
// ROTEADOR
// ============================================================
$router = new Router();

// Rotas públicas
$router->add('landing',         LandingController::class,    'index');
$router->add('health',          HealthController::class,     'index');
$router->add('login',           AuthController::class,       'login');
$router->add('logout',          AuthController::class,       'logout');
$router->add('logout_api',      AuthController::class,       'logoutApi');
$router->add('openapi',         ApiController::class,        'openapi');
$router->add('api_docs',        ApiController::class,        'docs');
$router->add('2fa_verify',      TwoFactorController::class,  'verify');
$router->add('api',             ApiController::class,        'handle');

// Rotas com sessão obrigatória
$router->add('dashboard',         DashboardController::class,     'index');
$router->add('2fa_setup',         TwoFactorController::class,     'setup');
$router->add('audit',             AuditController::class,         'index');
$router->add('backup',            BackupController::class,        'index');
$router->add('backup_download',   BackupController::class,        'download');
$router->add('backup_restore',    BackupController::class,        'restore');
$router->add('ai',                AiController::class,            'index');
$router->add('ai_assist',         AiController::class,            'assist');
$router->add('notifications_list',NotificationController::class,  'list');
$router->add('notification_read', NotificationController::class,  'read');
$router->add('tokens',            TokenController::class,         'index');
$router->add('token_create',      TokenController::class,         'create');
$router->add('token_revoke',      TokenController::class,         'revoke');
$router->add('roles',             RoleController::class,          'index');
$router->add('role_assign',       RoleController::class,          'assign');
$router->add('role_remove',       RoleController::class,          'remove');
$router->add('webhook_logs',      WebhookController::class,       'index');
$router->add('process_webhooks',  WebhookController::class,       'process');
$router->add('view_grid',         CrudController::class,          'viewGrid');
$router->add('view_record',       CrudController::class,          'viewRecord');
$router->add('add',               CrudController::class,          'add');
$router->add('edit',              CrudController::class,          'edit');
$router->add('delete',            CrudController::class,          'delete');
$router->add('delete_multiple',   CrudController::class,          'deleteMultiple');
$router->add('save_inline',       CrudController::class,          'saveInline');
$router->add('advanced_filter',   CrudController::class,          'advancedFilter');
$router->add('faker',             CrudController::class,          'faker');
$router->add('create_table_form', WizardController::class,        'createTableForm');
$router->add('create_table',      WizardController::class,        'createTable');
$router->add('welcome',           WizardController::class,        'welcome');
$router->add('connect',           WizardController::class,        'connect');
$router->add('tables',            WizardController::class,        'tables');
$router->add('configure',         WizardController::class,        'configure');
$router->add('appearance',        WizardController::class,        'appearance');
$router->add('relationships',     WizardController::class,        'relationships');
$router->add('generate',          WizardController::class,        'generate');
$router->add('export',            ExportController::class,        'csv');
$router->add('export_excel',      ExportController::class,        'excel');
$router->add('export_pdf',        ExportController::class,        'pdf');
$router->add('tenants',           TenantController::class,        'index');
$router->add('tenant_switch',     TenantController::class,        'switch');
$router->add('designer',          DesignerController::class,      'index');
$router->add('designer_save',     DesignerController::class,      'save');

// Despacha a rota (padrão: landing page pública)
$router->dispatch($_GET['action'] ?? 'landing');