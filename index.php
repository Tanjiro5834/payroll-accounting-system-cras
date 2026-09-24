<?php
declare(strict_types=1);

session_start();
define('BASE_PATH', __DIR__);

// ---------- Autoloader ----------
spl_autoload_register(function (string $class): void {
    $class = basename(str_replace('\\', '/', $class));
    foreach (['core', 'config', 'controllers', 'services', 'repositories', 'models'] as $dir) {
        $file = BASE_PATH . "/$dir/$class.php";
        if (is_file($file)) { require_once $file; return; }
    }
});

// ---------- Helpers ----------
function json(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $to): never {
    header("Location: $to");
    exit;
}

function render(string $view): never {
    $file = BASE_PATH . "/views/$view.html";
    if (!is_file($file)) { http_response_code(404); exit('View not found'); }
    readfile($file);
    exit;
}

// ---------- Request ----------
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');      // /payroll-accounting-system-cras
$path   = '/' . trim(substr($uri, strlen($base)), '/');
$isApi  = str_starts_with($path, '/api/');

// ---------- Routes: [METHOD, pattern, handler, requiresAuth] ----------
$routes = [
    // Pages
    ['GET', '/',              'view:auth/login',                     false],
    ['GET', '/login',         'view:auth/login',                     false],
    ['GET', '/dashboard',     'view:dashboard/dashboard',            true],
    ['GET', '/employees',     'view:employees/employees',            true],
    ['GET', '/payroll',       'view:payroll/payroll',                true],
    ['GET', '/punch',         'view:punch/punch',                    true],
    ['GET', '/reports',       'view:reports/reports',                true],
    ['GET', '/thirteenth-month', 'view:thirteenth-month/thirteenth-month', true],

    // API
    ['POST',   '/api/login',          [AuthController::class, 'login'],       false],
    ['POST',   '/api/logout',         [AuthController::class, 'logout'],      true],
    ['GET',    '/api/employees',      [EmployeeController::class, 'index'],   true],
    ['GET',    '/api/employees/{id}', [EmployeeController::class, 'show'],    true],
    ['POST',   '/api/employees',      [EmployeeController::class, 'store'],   true],
    ['PUT',    '/api/employees/{id}', [EmployeeController::class, 'update'],  true],
    ['DELETE', '/api/employees/{id}', [EmployeeController::class, 'destroy'], true],
    ['POST',   '/api/punch',          [TimePunchController::class, 'punch'],  true],
];

// ---------- Dispatch ----------
$allowed = [];

try {
    foreach ($routes as [$verb, $pattern, $handler, $auth]) {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (!preg_match($regex, $path, $m)) continue;

        if ($verb !== $method) { $allowed[] = $verb; continue; }

        if ($auth && empty($_SESSION['user_id'])) {
            $isApi ? json(['error' => 'Unauthorized'], 401) : redirect("$base/login");
        }

        if (is_string($handler)) render(substr($handler, 5));   // "view:..."

        $params = array_values(array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
        [$class, $action] = $handler;
        (new $class())->$action(...$params);
        exit;
    }

    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        json(['error' => 'Method Not Allowed'], 405);
    }

    $isApi ? json(['error' => 'Not Found'], 404) : (http_response_code(404) and exit('404 Not Found'));

} catch (Throwable $e) {
    error_log($e, 3, BASE_PATH . '/storage/logs/error.log');
    $isApi ? json(['error' => 'Server error'], 500) : (http_response_code(500) and exit('Server error'));
}