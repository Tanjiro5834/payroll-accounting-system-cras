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

    private const SHORT_LUNCH_MINUTES = 50;
    private const LONG_LUNCH_MAX      = 70;
    private const HABITUAL_LATE_LIMIT = 3;

    private TimePunchRepository $repository;

    public function __construct(?TimePunchRepository $repository = null) {
        $this->repository = $repository ?? new TimePunchRepository();
    }

    public function runDailyFlags(?string $date = null): array {
        $date = $this->resolveDate($date);

        return [
            'date'                       => $date,
            'out_from_different_ip'      => $this->flagOutFromDifferentIp($date),
            'missing_punch'              => $this->flagMissingPunch($date),
            'short_lunch'                => $this->flagShortLunch($date),
            'long_lunch'                 => $this->flagLongLunch($date),
            'early_out'                  => $this->flagEarlyOut($date),
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
        return $this->repository->flagShortLunch($date, self::SHORT_LUNCH_MINUTES);
    }

    public function flagLongLunch(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagLongLunch($date, self::LONG_LUNCH_MAX);
    }

    public function flagEarlyOut(string $date): int {
        $this->assertDate($date, 'date');
        return $this->repository->flagEarlyOut($date);
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

        if (!$this->repository->unflagById($punchId, $reviewerId)) {
            throw new DomainException("Punch #{$punchId} is not flagged or was already reviewed.");
        }

        return true;
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