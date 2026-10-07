<?php
namespace App\Service;

use App\Helper\DateTimeHelper;
use App\Repository\TimePunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class FlaggingService {
    private const TIMEZONE = 'Asia/Manila';

    // A lunch break more than 10 minutes off the scheduled length gets flagged (60-min lunch: < 50 or > 70).
    private const LUNCH_TOLERANCE_MINUTES = 10;
    // First run, or after a long gap: don't scan further back than this.
    private const MAX_CATCH_UP_DAYS = 31;
    public const SETTING_CHECKED_THROUGH = 'flags_checked_through';
    private const HABITUAL_LATE_LIMIT = 3;
    private const MAX_BULK_REVIEW = 500;
    private const REVIEW_STATUSES = ['open', 'reviewed', 'all'];

    private TimePunchRepository $repository;
    private SystemSettingService $settings;

    public function __construct(?TimePunchRepository $repository = null, ?SystemSettingService $settings = null) {
        $this->repository = $repository ?? new TimePunchRepository();
        $this->settings   = $settings   ?? new SystemSettingService();
    }

    /**
     * Runs the daily flag rules for every finished day not checked yet (up to yesterday), then remembers
     * how far it got. No cron needed: called whenever the owner opens Flagged Punches or the dashboard.
     * Today is never checked, because people are still punching (a missing PM_OUT at 10 AM isn't missing yet).
     * @return array{checked: string[], flagged: int}
     */
    public function runPendingDays(): array {
        $tz        = new DateTimeZone(self::TIMEZONE);
        $yesterday = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
        $floor     = (new DateTimeImmutable('today', $tz))->modify('-' . self::MAX_CATCH_UP_DAYS . ' days')->format('Y-m-d');

        $last = (string) $this->settings->getValue(self::SETTING_CHECKED_THROUGH, '');
        $from = $last !== '' ? (new DateTimeImmutable($last, $tz))->modify('+1 day')->format('Y-m-d') : $floor;
        $from = max($from, $floor);

        $checked = [];
        $flagged = 0;
        for ($d = $from; $d <= $yesterday; $d = (new DateTimeImmutable($d, $tz))->modify('+1 day')->format('Y-m-d')) {
            $flagged  += array_sum(array_slice($this->runDailyFlags($d), 1));
            $checked[] = $d;
        }

        if ($checked) {
            $this->settings->setValue(self::SETTING_CHECKED_THROUGH, $yesterday);
        }
        return ['checked' => $checked, 'flagged' => $flagged];
    }

    public function runDailyFlags(?string $date = null): array {
        $date = $this->resolveDate($date);

        return [
            'date'                       => $date,
            'missing_punch'              => $this->flagMissingPunch($date),
            'early_out'                  => $this->flagEarlyOut($date),
            'short_lunch'                => $this->flagShortLunch($date),
            'long_lunch'                 => $this->flagLongLunch($date),
            'out_from_different_ip'      => $this->flagOutFromDifferentIp($date),
            'same_device_multiple_users' => $this->flagSameDeviceMultipleEmployees($date),
        ];
    }

    public function flagOutFromDifferentIp(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagOutFromDifferentIp($date);
    }

    public function flagMissingPunch(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagMissingPunch($date);
    }

    public function flagShortLunch(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagShortLunch($date, $this->lunchMinutes() - self::LUNCH_TOLERANCE_MINUTES);
    }

    public function flagLongLunch(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagLongLunch($date, $this->lunchMinutes() + self::LUNCH_TOLERANCE_MINUTES);
    }

    public function flagEarlyOut(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagEarlyOut($date, $this->settings->getWorkEnd() . ':00');
    }

    public function flagHabitualLate(int $employeeId, int $year, int $month): int {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }
        if ($year < 2000 || $year > (int) date('Y') + 1) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }

        $flagged = $this->repository->flagHabitualLate($employeeId, $year, $month);

        if ($flagged >= self::HABITUAL_LATE_LIMIT) {
            return $flagged;
        }

        return 0;
    }

    public function flagSameDeviceMultipleEmployees(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagSameDeviceMultipleEmployees($date);
    }

    public function getAllFlags(?string $date = null): array {
        return $this->repository->findFlagged($this->resolveOptionalDate($date));
    }

    public function getFlagsByEmployee(int $employeeId, string $start, string $end): array {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        $this->assertDate($start, 'start');
        $this->assertDate($end, 'end');
        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }

        return $this->repository->findFlaggedByEmployee($employeeId, $start, $end);
    }

    public function markFlagReviewed(int $punchId, int $reviewerId): bool {
        $this->assertPositiveId($punchId, 'punchId');
        $this->assertPositiveId($reviewerId, 'reviewerId');

        if (!$this->repository->markReviewed($punchId, $reviewerId)) {
            throw new DomainException("Punch #{$punchId} is not flagged or was already reviewed.");
        }

        return true;
    }

    public function markFlagsReviewedBulk(array $ids, int $reviewerId): int {
        $this->assertPositiveId($reviewerId, 'reviewerId');

        $clean = [];
        foreach ($ids as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new InvalidArgumentException('ids must be positive integers.');
            }
            $clean[$id] = $id;
        }
        if (!$clean) {
            throw new InvalidArgumentException('Select at least one punch.');
        }
        if (count($clean) > self::MAX_BULK_REVIEW) {
            throw new InvalidArgumentException('Review at most ' . self::MAX_BULK_REVIEW . ' punches at once.');
        }

        return $this->repository->markReviewedBulk(array_values($clean), $reviewerId);
    }

    public function search(string $status, string $from, string $to, int $employeeId): array {
        $status = $status !== '' ? $status : 'open';
        if (!in_array($status, self::REVIEW_STATUSES, true)) {
            throw new InvalidArgumentException('status must be one of: ' . implode(', ', self::REVIEW_STATUSES) . '.');
        }

        $today = new DateTimeImmutable('today', new DateTimeZone(self::TIMEZONE));
        $to    = $to   !== '' ? $to   : $today->format('Y-m-d');
        $from  = $from !== '' ? $from : $today->modify('-13 days')->format('Y-m-d');

        $this->assertDate($from, 'date_from');
        $this->assertDate($to, 'date_to');

        if ($from > $to) {
            throw new InvalidArgumentException('date_from must be on or before date_to.');
        }

        if ($employeeId < 0) {
            throw new InvalidArgumentException('employee_id must not be negative.');
        }

        return array_map(fn(array $r) => [
            'id'               => (int) $r['id'],
            'employee_id'      => (int) $r['employee_id'],
            'full_name'        => $r['full_name'],
            'work_date'        => $r['work_date'],
            'punch_type'       => $r['punch_type'],
            'punch_time'       => $r['punch_time'],
            'ip_address'       => $r['ip_address'],
            'gps_lat'          => $r['gps_lat'],
            'gps_lng'          => $r['gps_lng'],
            'gps_accuracy'     => $r['gps_accuracy'],
            'flag_reason'      => $r['flag_reason'],
            'reviewed_by'      => $r['reviewed_by'] !== null ? (int) $r['reviewed_by'] : null,
            'reviewed_by_name' => $r['reviewed_by_name'],
            'reviewed_at'      => $r['reviewed_at'],
        ], $this->repository->searchFlagged($status, $from, $to, $employeeId));
    }

    public function dismissFlag(int $punchId, int $reviewerId, string $reason): bool {
        $this->assertPositiveId($punchId, 'punchId');
        $this->assertPositiveId($reviewerId, 'reviewerId');

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Dismiss reason is required.');
        }

        if (!$this->repository->reviewById($punchId, $reviewerId)) {
            throw new DomainException("Punch #{$punchId} not found.");
        }

        return true;
    }

    public function summarizeFlags(string $date): array {
        $this->assertDate($date, 'date');

        $rows = $this->repository->findFlagged($date);

        $byReason = [];
        $byType   = [];
        foreach ($rows as $r) {
            $reason = (string) ($r['flag_reason'] ?? 'unknown');
            $type   = (string) ($r['punch_type'] ?? 'unknown');

            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
            $byType[$type]     = ($byType[$type] ?? 0) + 1;
        }

        return [
            'date'      => $date,
            'total'     => count($rows),
            'by_reason' => $byReason,
            'by_type'   => $byType,
        ];
    }

    private function lunchMinutes(): int {
        $start = DateTimeImmutable::createFromFormat('!H:i', $this->settings->getLunchStart());
        $end   = DateTimeImmutable::createFromFormat('!H:i', $this->settings->getLunchEnd());
        return intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
    }

    private function resolveDate(?string $date): string {
        if ($date === null || $date === '') {
            return DateTimeHelper::today();
        }

        $this->assertDate($date, 'date');
        return $date;
    }

    private function resolveOptionalDate(?string $date): ?string {
        if ($date === null || $date === '') {
            return null;
        }

        $this->assertDate($date, 'date');
        return $date;
    }

    private function assertDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function assertPositiveId(int $id, string $field): void {
        if ($id < 1) {
            throw new InvalidArgumentException("{$field} must be a positive integer.");
        }
    }
}