<?php
declare(strict_types=1);

// Server settings live in app/config/env.php (git-ignored; copy env.example.php).
$envFile = __DIR__ . '/app/config/env.php';
if (is_file($envFile)) {
    foreach ((array) require $envFile as $key => $value) {
        putenv("{$key}={$value}");
    }
}

// Errors are logged, never shown to users, unless APP_DEBUG=1 (or running on localhost).
$host    = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$isLocal = getenv('APP_ENV') === 'local'
        || (getenv('APP_ENV') !== 'production' && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
$showErrors = $isLocal || getenv('APP_DEBUG') === '1';
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $showErrors ? '1' : '0');

// Anything not caught by a controller: log the details, show the user a plain message.
if (!$showErrors) {
    set_exception_handler(function (Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Something went wrong. Please try again.']);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>Error</title><p style="font-family:sans-serif;padding:2rem">Something went wrong. Please try again, or tell the office if it keeps happening.</p>';
        }
    });
}

$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,   // cookie only travels over https on the live site
    'httponly' => true,       // JavaScript can't read the session cookie
    'samesite' => 'Lax',
]);
session_start();

use App\Config\App;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\DutyCalendarController;
use App\Controller\EmployeeController;
use App\Controller\PayrollController;
use App\Controller\PunchController;
use App\Controller\ReportController;
use App\Controller\ThirteenthMonthController;
use App\Controller\FlaggedPunchController;
use App\Controller\MyDashboardController;
use App\Controller\MyTimeLogController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;

spl_autoload_register(function (string $class): void {
    $parts = explode('\\', $class);
    $name  = array_pop($parts);
    $dir   = strtolower($parts[1] ?? 'config');   
    $file  = __DIR__ . "/app/$dir/$name.php";
    if (is_file($file)) require_once $file;
});

date_default_timezone_set(App::TIMEZONE);

function renderPartial(string $name, array $vars): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/views/partials/$name.php";
    return (string) ob_get_clean();
}

$routes = [
    'login'            => ['auth/login',                        AuthController::class,            null,       ['login', 'logout']],
    'my-dashboard'     => ['dashboard/my-dashboard',            MyDashboardController::class,     'employee', ['summary', 'attendance', 'trend', 'payslips', 'payslip', 'rateHistory', 'uploadPhoto', 'removePhoto', 'changePassword']],
    'my-time-logs'     => ['dashboard/my-time-logs',            MyTimeLogController::class,       'employee', ['month']],
    'dashboard'        => ['dashboard/dashboard',               DashboardController::class,       'admin',    ['kpiSummary', 'todayActivity', 'flaggedPunches', 'payrollPending', 'recentActivity']],
    'employee-form'    => ['employees/employee-form',           EmployeeController::class,        'admin',    ['show', 'store', 'update']],
    'payroll'          => ['payroll/payroll',                   PayrollController::class,         'admin',    ['index', 'compute', 'approve', 'show', 'history', 'export', 'markAsPaid']],
    'thirteenth-month' => ['thirteenth-month/thirteenth-month', ThirteenthMonthController::class, 'admin',    ['generateReport', 'computeAll', 'show', 'approve', 'markAsPaid', 'export']],
    'audit-log'        => ['reports/audit-log',                 ReportController::class,          'admin',    ['auditLog', 'auditLogExport']],
    'location-log'     => ['reports/location-log',              ReportController::class,          'admin',    ['locationLog', 'locationLogExport']],
    'punch-employee-list'       => ['punch/punch-employee-list',         PunchController::class,           'employee', ['index']],
    'punch'            => ['punch/punch',                       PunchController::class,           'employee', ['store', 'todayStatus', 'history']],
    'employees'        => ['employees/employees',               EmployeeController::class,        'admin',    ['index', 'search', 'show', 'store', 'update', 'deactivate', 'reactivate', 'uploadPhoto', 'removePhoto']],
    'duty-calendar'    => ['calendar/duty-calendar',            DutyCalendarController::class,    'admin',    ['roster', 'saveDuty', 'deleteDuty', 'holidays', 'saveHoliday', 'deleteHoliday', 'seedHolidays', 'settings', 'saveSettings']],
    'flagged-punches'  => ['punch/flagged-punches',             FlaggedPunchController::class,    'admin',    ['index', 'review', 'reviewBulk']],
];

// ─── ROUTING ───
$page = $_GET['page']   ?? 'login';
$action = $_GET['action'] ?? null;
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!isset($routes[$page])) {
    http_response_code(404);
    exit('404 Not Found');
}

[$view, $controller, $role, $actions] = $routes[$page];

if ($role !== null) {
    (new AuthMiddleware())->handle();
    (new RoleMiddleware())->{'require' . ucfirst($role)}(); 
}

if ($action === null) {
    $csrf = htmlspecialchars((new CsrfMiddleware())->getToken(), ENT_QUOTES);
    $html = file_get_contents(__DIR__ . "/views/$view.html");

    $html = preg_replace_callback(
        '#(src|href)="(assets/[^"?]+\.(?:js|css))"#',
        function (array $m): string {
            $file = __DIR__ . '/' . $m[2];
            $ver  = is_file($file) ? filemtime($file) : 0;
            return $m[1] . '="' . $m[2] . '?v=' . $ver . '"';
        },
        $html
    );

    if (str_contains($html, '<!-- @sidebar -->')) {
        $html = str_replace('<!-- @sidebar -->', renderPartial('sidebar', [
            'page' => $page,
            'user' => AuthMiddleware::user(),
        ]), $html);
    }

    echo str_replace(
        '</head>',
        "<meta name=\"csrf-token\" content=\"$csrf\">\n</head>",
        $html
    );
    exit;
}

if (!in_array($action, $actions, true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Unknown action']));
}

if($page !== 'login') (new CsrfMiddleware())->handle();

$id === null ? (new $controller())->$action() : (new $controller())->$action($id);