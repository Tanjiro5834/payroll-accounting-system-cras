<?php
declare(strict_types=1);

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$name  = $user['display_name'] ?? $user['username'] ?? 'User';
$role  = $user['role'] ?? '';
$roleLabel = ['owner' => 'Owner', 'admin' => 'Administrator', 'employee' => 'Employee'][$role] ?? ucfirst($role);

$words    = preg_split('/\s+/', trim($name)) ?: [];
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));

// Pages that should highlight another nav item.
$activeAlias = ['employee-form' => 'employees'];
$active = $activeAlias[$page] ?? $page;

// [route or null (no page yet), label, svg path(s)]
$sections = [
    'Main' => [
        ['dashboard',           'Dashboard',     ['M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z']],
        ['punch-employee-list', 'Clock In / Out', ['M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z']],
        ['my-time-logs',        'My Time Logs',  ['M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z']],
    ],
    'Administration' => [
        ['employees',        'Employees',         ['M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z']],
        ['payroll',          'Payroll',           ['M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z']],
        ['duty-calendar',    'Sundays & Holidays', ['M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5']],
        ['thirteenth-month', '13th Month Report', ['M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z']],
        ['audit-log',        'Audit Trail',       ['M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z']],
        ['location-log',     'Location Logs',     ['M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', 'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z']],
        ['flagged-punches',  'Flagged Punches',   ['M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5']],
    ],
];

// Employees get a self-service menu; admins/owners keep the full menu, plus "My Dashboard" when linked to an employee record.
$myDashboard = ['my-dashboard', 'My Dashboard', ['M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z']];
if ($role === 'employee') {
    $sections = [
        'Main' => [
            ['my-dashboard', 'Dashboard', ['M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z']],
            $sections['Main'][1],
            $sections['Main'][2],
        ],
    ];
} elseif (!empty($user['employee_id'])) {
    array_splice($sections['Main'], 1, 0, [$myDashboard]);
} else {
    // Not linked to an employee record: nothing of their own to show.
    $sections['Main'] = array_values(array_filter($sections['Main'], fn($item) => $item[0] !== 'my-time-logs'));
}

$base    = 'group flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm min-h-[44px]';
$onCls   = "$base bg-coolant text-white shadow-sm";
$offCls  = "$base text-slate-100 hover:bg-white/10 hover:text-white transition-colors";
$soonCls = "$base text-slate-500 cursor-not-allowed";
$icon = static function (array $paths, string $cls) use ($e): string {
    $d = implode('', array_map(fn($p) => '<path stroke-linecap="round" stroke-linejoin="round" d="' . $e($p) . '" />', $paths));
    return '<svg class="w-5 h-5 flex-shrink-0 ' . $cls . '" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">' . $d . '</svg>';
};
?>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-ink text-slate-100 transform -translate-x-full lg:translate-x-0 flex flex-col" aria-label="Main navigation">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/5">
        <img src="assets/images/coronacion-logo.png" alt="Coronacion Logo" class="w-12 h-12 object-contain">
        <div class="min-w-0">
            <p class="text-sm font-bold tracking-tight text-white leading-tight">Coronacion</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider leading-tight">Timekeeping System</p>
        </div>
    </div>

    <div class="px-5 py-4 border-b border-white/5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-coolant flex items-center justify-center flex-shrink-0">
                <span class="text-white font-bold text-sm"><?= $e($initials) ?></span>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white truncate"><?= $e($name) ?></p>
                <p class="text-xs text-slate-400 truncate"><?= $e($roleLabel) ?></p>
            </div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        <?php foreach ($sections as $heading => $items): ?>
            <p class="px-3 mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400"><?= $e($heading) ?></p>
            <ul class="space-y-1 mb-5">
                <?php foreach ($items as [$route, $label, $paths]): ?>
                    <li>
                    <?php if ($route === null): ?>
                        <span class="<?= $soonCls ?>" aria-disabled="true" title="Coming soon">
                            <?= $icon($paths, 'text-slate-600') ?><?= $e($label) ?>
                        </span>
                    <?php elseif ($route === $active): ?>
                        <a href="index.php?page=<?= $e($route) ?>" class="<?= $onCls ?>" aria-current="page">
                            <?= $icon($paths, 'text-white') ?><?= $e($label) ?>
                        </a>
                    <?php else: ?>
                        <a href="index.php?page=<?= $e($route) ?>" class="<?= $offCls ?>">
                            <?= $icon($paths, 'text-slate-400 group-hover:text-frost transition-colors') ?><?= $e($label) ?>
                        </a>
                    <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <div class="border-t border-white/5 p-3">
        <a href="index.php?page=login&amp;action=logout" class="<?= $offCls ?>">
            <?= $icon(['M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75'], 'text-slate-400 group-hover:text-frost transition-colors') ?>Sign out
        </a>
    </div>
</aside>