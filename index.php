<?php
declare(strict_types=1);

ini_set('display_errors', '1');   // dev only — set to '0' in production
error_reporting(E_ALL);
session_start();

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\EmployeeController;
use App\Controller\PayrollController;
use App\Controller\PunchController;
use App\Controller\ReportController;
use App\Controller\ThirteenthMonthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;

// ─── AUTOLOAD: App\Controller\Foo → app/controller/Foo.php ───
spl_autoload_register(function (string $class): void {
    $parts = explode('\\', $class);
    $name  = array_pop($parts);
    $dir   = strtolower($parts[1] ?? 'config');   // no namespace (Database) → app/config
    $file  = __DIR__ . "/app/$dir/$name.php";
    if (is_file($file)) require_once $file;
});

// ─── ROUTES: page => [view, controller, role, allowed actions] ───
$routes = [
    'login'            => ['auth/login',                        AuthController::class,            null,       ['login', 'logout']],
    'dashboard'        => ['dashboard/dashboard',               DashboardController::class,       'admin',    ['kpiSummary', 'todayActivity', 'recentActivity']],
    'employees'        => ['employees/employees',               EmployeeController::class,        'admin',    ['index', 'search', 'show', 'store', 'update', 'deactivate', 'reactivate', 'uploadPhoto']],
    'employee-form'    => ['employees/employee-form',           EmployeeController::class,        'admin',    ['show', 'store', 'update']],
    'payroll'          => ['payroll/payroll',                   PayrollController::class,         'admin',    ['index', 'compute', 'show', 'history', 'export', 'markAsPaid']],
    'thirteenth-month' => ['thirteenth-month/thirteenth-month', ThirteenthMonthController::class, 'admin',    ['index', 'computeAll', 'show', 'approve', 'markAsPaid', 'export']],
    'audit-log'        => ['reports/audit-log',                 ReportController::class,          'admin',    ['auditLog', 'auditLogExport']],
    'location-log'     => ['reports/location-log',              ReportController::class,          'admin',    ['locationLog', 'locationLogExport']],
    'punch-list'       => ['punch/punch-employee-list',         PunchController::class,           'employee', ['index']],
    'punch'            => ['punch/punch',                       PunchController::class,           'employee', ['store', 'todayStatus', 'history']],
];

// ─── ROUTING ───
$page   = $_GET['page']   ?? 'login';
$action = $_GET['action'] ?? null;
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!isset($routes[$page])) {
    http_response_code(404);
    exit('404 Not Found');
}

[$view, $controller, $role, $actions] = $routes[$page];

if ($role !== null) {
    (new AuthMiddleware())->handle();
    (new RoleMiddleware())->{'require' . ucfirst($role)}();   // requireAdmin() / requireEmployee()
}

// No action → serve the page
if ($action === null) {
    readfile(__DIR__ . "/views/$view.html");
    exit;
}

// Action → call controller: ?page=employees&action=show&id=5 → EmployeeController::show(5)
if (!in_array($action, $actions, true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Unknown action']));
}

if($page !== 'login') (new CsrfMiddleware())->handle();   // no-op on GET

$id === null
    ? (new $controller())->$action()
    : (new $controller())->$action($id);