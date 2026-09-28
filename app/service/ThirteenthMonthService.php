<?php
namespace App\Service;

use App\Entity\ThirteenthMonthRecord;
use App\Helper\DateTimeHelper;
use App\Repository\DailySummaryRepository;
use App\Repository\EmployeeRepository;
use App\Repository\PayrollRepository;
use App\Repository\ThirteenthMonthRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Dompdf\Dompdf;
use InvalidArgumentException;
use RuntimeException;

class ThirteenthMonthService {
    private const TIMEZONE = 'Asia/Manila';
    private const STATUSES = ['draft', 'approved', 'paid'];

    private ThirteenthMonthRepository $repository;
    private EmployeeRepository $empRepository;
    private PayrollRepository $payrollRepository;
    private DailySummaryRepository $dailySummaryRepository;

    public function __construct(
        ?ThirteenthMonthRepository $repository = null,
        ?EmployeeRepository $empRepository = null,
        ?PayrollRepository $payrollRepository = null,
        ?DailySummaryRepository $dailySummaryRepository = null
    ) {
        $this->repository             = $repository             ?? new ThirteenthMonthRepository();
        $this->empRepository          = $empRepository          ?? new EmployeeRepository();
        $this->payrollRepository      = $payrollRepository      ?? new PayrollRepository();
        $this->dailySummaryRepository = $dailySummaryRepository ?? new DailySummaryRepository();
    }

    public function getAll(): array {
        return $this->repository->findAll();
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function getByEmployee(int $employeeId): array {
        return $this->repository->findByEmployee($employeeId);
    }

    public function getByEmployeeAndYear(int $employeeId, int $year): ?array {
        $this->validateYear($year);
        return $this->repository->findByEmployeeAndYear($employeeId, $year);
    }

    public function getByYear(int $year): array {
        $this->validateYear($year);
        return $this->repository->findByYear($year);
    }

    public function getByStatus(string $status): array {
        $status = strtolower($status);
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }
        return $this->repository->findByStatus($status);
    }

    public function computeForEmployee(int $employeeId, int $year, int $userId): array {
        $this->validateYear($year);
        $this->requireEmployee($employeeId);
        $this->assertEditable($employeeId, $year);

        $summary = $this->payrollRepository->summarizeByYear($year);
        $row     = $this->buildRow($employeeId, $year, $summary[$employeeId] ?? null, $userId);

        $this->repository->bulkUpsert([$row]);
        return $row;
    }

    public function computeForAll(int $year, int $userId): int {
        $this->validateYear($year);

        $summary   = $this->payrollRepository->summarizeByYear($year);
        $employees = $this->empRepository->findAllActive();

        $employeeIds = array_unique(array_merge(
            array_map('intval', array_keys($summary)),
            array_map(fn($e) => (int) (is_array($e) ? $e['id'] : $e->getId()), $employees)
        ));

        $rows = array_map(
            fn(int $id) => $this->buildRow($id, $year, $summary[$id] ?? null, $userId),
            $employeeIds
        );

        if (!$rows) {
            return 0;
        }

        return $this->repository->transaction(fn() => $this->repository->bulkUpsert($rows));
    }

    public function computeBasicSalary(int $employeeId, int $year): string {
        return (string) ($this->summaryFor($employeeId, $year)['total_basic'] ?? '0.00');
    }

    public function computeProRated(int $employeeId, int $year): string {
        $this->validateYear($year);
        $emp = $this->requireEmployee($employeeId);

        $monthlyRate = $emp['monthly_rate'] ?? null;
        if ($monthlyRate === null || !is_numeric($monthlyRate) || (float) $monthlyRate <= 0) {
            throw new DomainException("Employee {$employeeId} has no monthly rate.");
        }

        $tz        = new DateTimeZone(self::TIMEZONE);
        $yearStart = new DateTimeImmutable(DateTimeHelper::startOfYear($year), $tz);   
        $yearEnd   = new DateTimeImmutable(DateTimeHelper::endOfYear($year),   $tz);

        $hired = !empty($emp['date_hired'])
            ? new DateTimeImmutable((string) $emp['date_hired'], $tz)
            : $yearStart;

        if ($hired > $yearEnd) {
            return '0.00';
        }

        $start      = max($hired, $yearStart);
        $daysWorked = $start->diff($yearEnd)->days + 1;
        $daysInYear = $yearStart->format('L') === '1' ? 366 : 365;

        $projected = bcdiv(bcmul((string) $monthlyRate, (string) $daysWorked, 4), (string) $daysInYear, 4);
        return $this->roundHalfUp($projected);
    }

    public function countMonthsWorked(int $employeeId, int $year): int {
        return (int) ($this->summaryFor($employeeId, $year)['months_worked'] ?? 0);
    }

    public function countDaysWorked(int $employeeId, int $year): int {
        $this->validateYear($year);
        return $this->dailySummaryRepository->countDaysWorked(
            $employeeId,
            sprintf('%04d-01-01', $year),
            sprintf('%04d-12-31', $year)
        );
    }

    public function save(array $data): ThirteenthMonthRecord {
        $payload = $this->normalize($data);

        if ($this->repository->findByEmployeeAndYear($payload['employee_id'], $payload['year'])) {
            throw new DomainException(
                "Record already exists for employee {$payload['employee_id']}, year {$payload['year']}."
            );
        }

        $record = ThirteenthMonthRecord::fromArray($payload);
        $this->repository->create($record);
        return $record;
    }

    public function upsert(array $data): array {
        $payload = $this->normalize($data);
        $this->assertEditable($payload['employee_id'], $payload['year']);

        $this->repository->bulkUpsert([$payload]);
        return $payload;
    }

    public function approve(int $id, int $approvedBy): bool {
        $record = $this->requireRecord($id);
        if ($record['status'] !== 'draft') {
            throw new DomainException("Only draft records can be approved (current: {$record['status']}).");
        }

        if (!$this->repository->approve($id, $approvedBy)) {
            throw new DomainException('Record was modified by another user. Reload and try again.');
        }
        return true;
    }

    public function markAsPaid(int $id, ?string $paidAt = null): bool {
        $paidAt ??= DateTimeHelper::now(); 
        $this->assertDateTime($paidAt, 'paid_at');

        $record = $this->requireRecord($id);
        if ($record['status'] !== 'approved') {
            throw new DomainException("Only approved records can be marked as paid (current: {$record['status']}).");
        }

        if (!$this->repository->markAsPaid($id, $paidAt)) {
            throw new DomainException('Record was modified by another user. Reload and try again.');
        }
        return true;
    }

    public function delete(int $id): bool {
        $record = $this->requireRecord($id);
        if ($record['status'] !== 'draft') {
            throw new DomainException("Only draft records can be deleted (current: {$record['status']}).");
        }
        return $this->repository->deleteDraft($id);
    }

    public function generateReport(int $year): array {
        $this->validateYear($year);
        $rows = $this->repository->findByYear($year);

        $totals = [
            'employees'    => count($rows),
            'total_basic'  => '0.00',
            'total_payout' => '0.00',
            'paid'         => '0.00',
            'unpaid'       => '0.00',
        ];

        foreach ($rows as $r) {
            $pay = (string) $r['thirteenth_month_pay'];
            $totals['total_basic']  = bcadd($totals['total_basic'],  (string) $r['total_basic_salary'], 2);
            $totals['total_payout'] = bcadd($totals['total_payout'], $pay, 2);
            $bucket = $r['status'] === 'paid' ? 'paid' : 'unpaid';
            $totals[$bucket] = bcadd($totals[$bucket], $pay, 2);
        }

        return [
            'year'         => $year,
            'generated_at' => DateTimeHelper::now(),  
            'rows'         => $rows,
            'totals'       => $totals,
        ];
    }

    public function exportToCsv(array $report): string {
        $fh = fopen('php://temp', 'r+');

        fputcsv($fh, ['Employee ID', 'Name', 'Role', 'Months Worked', 'Regular Hours',
                      'Basic Salary', '13th Month Pay', 'Status', 'Paid At']);

        foreach ($report['rows'] as $r) {
            fputcsv($fh, [
                $r['employee_id'],
                $this->csvSafe($r['full_name'] ?? ''),
                $this->csvSafe($r['role'] ?? ''),
                $r['months_worked'],
                $r['total_regular_hours'],
                $r['total_basic_salary'],
                $r['thirteenth_month_pay'],
                $r['status'],
                $r['paid_at'] ?? '',
            ]);
        }

        $t = $report['totals'];
        fputcsv($fh, []);
        fputcsv($fh, ['TOTAL',  '', '', '', '', $t['total_basic'], $t['total_payout'], '', '']);
        fputcsv($fh, ['PAID',   '', '', '', '', '',                 $t['paid'],         '', '']);
        fputcsv($fh, ['UNPAID', '', '', '', '', '',                 $t['unpaid'],       '', '']);

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return "\xEF\xBB\xBF" . $csv;
    }

    public function exportToPdf(array $report): string {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('PDF export needs dompdf: run `composer require dompdf/dompdf`.');
        }

        $e   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $num = fn($v) => number_format((float) $v, 2);

        $body = '';
        foreach ($report['rows'] as $r) {
            $body .= '<tr>'
                . '<td>' . $e($r['full_name'] ?? '') . '</td>'
                . '<td>' . $e($r['role'] ?? '') . '</td>'
                . '<td class="n">' . (int) $r['months_worked'] . '</td>'
                . '<td class="n">' . $num($r['total_basic_salary']) . '</td>'
                . '<td class="n">' . $num($r['thirteenth_month_pay']) . '</td>'
                . '<td>' . $e(ucfirst((string) $r['status'])) . '</td>'
                . '</tr>';
        }

        $t    = $report['totals'];
        $html = '<html><head><meta charset="UTF-8"><style>
                    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
                    h1 { font-size: 16px; margin: 0 0 4px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                    th, td { border: 1px solid #ccc; padding: 4px 6px; }
                    th { background: #f0f0f0; text-align: left; }
                    .n { text-align: right; }
                    tfoot td { font-weight: bold; }
                 </style></head><body>'
            . '<h1>13th Month Pay Report — ' . (int) $report['year'] . '</h1>'
            . '<div>Generated: ' . $e($report['generated_at']) . ' · Employees: ' . (int) $t['employees'] . '</div>'
            . '<table><thead><tr><th>Name</th><th>Role</th><th class="n">Months</th>'
            . '<th class="n">Basic Salary (PHP)</th><th class="n">13th Month (PHP)</th><th>Status</th></tr></thead>'
            . '<tbody>' . $body . '</tbody>'
            . '<tfoot>'
            . '<tr><td colspan="3">Total</td><td class="n">'  . $num($t['total_basic'])  . '</td><td class="n">' . $num($t['total_payout']) . '</td><td></td></tr>'
            . '<tr><td colspan="4">Paid</td><td class="n">'   . $num($t['paid'])         . '</td><td></td></tr>'
            . '<tr><td colspan="4">Unpaid</td><td class="n">' . $num($t['unpaid'])       . '</td><td></td></tr>'
            . '</tfoot></table></body></html>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function getTotalPayout(int $year): string {
        $this->validateYear($year);
        return $this->repository->sumByYear($year);
    }

    public function getUnpaidRecords(int $year): array {
        $this->validateYear($year);
        return $this->repository->findUnpaidByYear($year);
    }

    private function buildRow(int $employeeId, int $year, ?array $s, int $userId): array {
        $basic = (string) ($s['total_basic'] ?? '0.00');

        return [
            'employee_id'          => $employeeId,
            'year'                 => $year,
            'months_worked'        => (int) ($s['months_worked'] ?? 0),
            'total_regular_hours'  => (string) ($s['total_hours'] ?? '0.00'),
            'total_basic_salary'   => $basic,
            'thirteenth_month_pay' => $this->roundHalfUp(bcdiv($basic, '12', 4)),
            'status'               => 'draft',
            'computed_by'          => $userId,
            'computed_at'          => DateTimeHelper::now(),   
            'paid_at'              => null,
        ];
    }

    private function summaryFor(int $employeeId, int $year): ?array {
        $this->validateYear($year);
        return $this->payrollRepository->summarizeByYear($year)[$employeeId] ?? null;
    }

    private function normalize(array $data): array {
        foreach (['employee_id', 'year'] as $key) {
            if (!isset($data[$key])) {
                throw new InvalidArgumentException("Missing field: {$key}");
            }
        }

        $employeeId = (int) $data['employee_id'];
        $year       = (int) $data['year'];
        $this->validateYear($year);
        $this->requireEmployee($employeeId);

        $months = filter_var($data['months_worked'] ?? 0, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 12]]);
        if ($months === false) {
            throw new InvalidArgumentException('months_worked must be an integer from 0 to 12.');
        }

        $basic = $this->money($data['total_basic_salary'] ?? '0', 'total_basic_salary');

        return [
            'employee_id'          => $employeeId,
            'year'                 => $year,
            'months_worked'        => $months,
            'total_regular_hours'  => $this->money($data['total_regular_hours'] ?? '0', 'total_regular_hours'),
            'total_basic_salary'   => $basic,
            'thirteenth_month_pay' => $this->roundHalfUp(bcdiv($basic, '12', 4)),
            'status'               => 'draft',
            'computed_by'          => isset($data['computed_by']) ? (int) $data['computed_by'] : null,
            'computed_at'          => DateTimeHelper::now(),
            'paid_at'              => null,
        ];
    }

    private function validateYear(int $year): void {
        if ($year < 2000 || $year > (int) date('Y') + 1) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }
    }

    private function requireEmployee(int $employeeId): array {
        $emp = $this->empRepository->findById($employeeId);
        if (!$emp) {
            throw new DomainException("Employee not found: {$employeeId}");
        }
        return is_array($emp) ? $emp : $emp->toArray();
    }

    private function requireRecord(int $id): array {
        $record = $this->repository->findById($id);
        if (!$record) {
            throw new DomainException("13th month record not found: {$id}");
        }
        return $record;
    }

    private function assertEditable(int $employeeId, int $year): void {
        $existing = $this->repository->findByEmployeeAndYear($employeeId, $year);
        if ($existing && $existing['status'] !== 'draft') {
            throw new DomainException(
                "Record for employee {$employeeId}, year {$year} is {$existing['status']} and locked."
            );
        }
    }

    private function money(mixed $value, string $field): string {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative number.");
        }
        return bcadd((string) $value, '0', 2);
    }

    private function roundHalfUp(string $value): string {
        return bccomp($value, '0', 4) >= 0
            ? bcadd($value, '0.005', 2)
            : bcsub($value, '0.005', 2);
    }

    private function assertDateTime(string $value, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException("{$field} must be a valid Y-m-d H:i:s.");
        }
    }

    private function csvSafe(?string $value): string {
        $value = (string) $value;
        return ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) ? "'" . $value : $value;
    }
}