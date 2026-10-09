<?php
// One-off import: creates an employee record + a login for each CSV row.
//
//   C:\xampp\php\php.exe tools\import-employees.php C:\path\to\employees.csv            (dry run)
//   C:\xampp\php\php.exe tools\import-employees.php C:\path\to\employees.csv --commit   (writes)
//
// CSV header (one row per person):
//   full_name,username,password,job_title,pay_frequency,hourly_rate,monthly_rate,date_hired
//
// Passwords are hashed here with password_hash() (bcrypt), the format login uses.
// Keep the CSV OUT of the repo and delete it after importing: it holds plaintext passwords.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$envFile = dirname(__DIR__) . '/app/config/env.php';
if (is_file($envFile)) {
    foreach ((array) require $envFile as $key => $value) {
        putenv("{$key}={$value}");
    }
} else {
    putenv('APP_ENV=local'); // XAMPP
}
date_default_timezone_set('Asia/Manila');

spl_autoload_register(function (string $class): void {
    $parts = explode('\\', $class);
    $name  = array_pop($parts);
    $dir   = strtolower($parts[1] ?? 'config');
    $file  = dirname(__DIR__) . "/app/$dir/$name.php";
    if (is_file($file)) require_once $file;
});

use App\Service\EmployeeService;
use App\Service\UserService;

$path   = $argv[1] ?? '';
$commit = in_array('--commit', $argv, true);

if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php tools/import-employees.php <file.csv> [--commit]\n");
    exit(1);
}

$fh = fopen($path, 'r');
$header = array_map(fn($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
$required = ['full_name', 'username', 'password', 'job_title', 'pay_frequency', 'hourly_rate', 'monthly_rate', 'date_hired'];
if ($missing = array_diff($required, $header)) {
    fwrite(STDERR, 'Missing CSV columns: ' . implode(', ', $missing) . "\n");
    exit(1);
}

$rows = [];
$line = 1;
while (($cells = fgetcsv($fh)) !== false) {
    $line++;
    if (count(array_filter($cells, fn($c) => trim((string) $c) !== '')) === 0) continue;
    $rows[$line] = array_map('trim', array_combine($header, array_pad($cells, count($header), '')));
}
fclose($fh);

$employees = new EmployeeService();
$users     = new UserService();
$db        = \Database::getInstance()->getConnection();

echo ($commit ? "IMPORTING" : "DRY RUN (nothing is saved; add --commit to write)") . " — " . count($rows) . " rows\n\n";

$ok = 0;
$failed = 0;
foreach ($rows as $line => $r) {
    $label = str_pad($r['full_name'] ?: "(line $line)", 18);
    $db->beginTransaction();
    try {
        $employeeId = $employees->create([
            'full_name'     => $r['full_name'],
            'role'          => $r['job_title'],
            'pay_frequency' => strtolower($r['pay_frequency']),
            'hourly_rate'   => $r['hourly_rate'],
            'monthly_rate'  => $r['monthly_rate'],
            'date_hired'    => $r['date_hired'],
        ]);
        $user = $users->create([
            'username'    => $r['username'],
            'password'    => $r['password'],
            'role'        => 'employee',
            'employee_id' => $employeeId,
        ]);

        $commit ? $db->commit() : $db->rollBack();
        echo "  OK    $label employee #$employeeId, login '{$r['username']}'\n";
        $ok++;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "  FAIL  $label line $line: {$e->getMessage()}\n"; if (getenv("IMPORT_DEBUG")) echo $e->getTraceAsString(), "\n";
        $failed++;
    }
}

echo "\n$ok ok, $failed failed" . ($commit ? '' : ' (dry run)') . "\n";
exit($failed ? 1 : 0);
