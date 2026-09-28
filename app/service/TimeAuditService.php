<?php
namespace App\Service;

use App\Helper\DateTimeHelper;
use App\Helper\IpHelper;
use App\Repository\AuditTrailRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class TimeAuditService {
    private const TIMEZONE = 'Asia/Manila';

    private AuditTrailRepository $repository;

    public function __construct(?AuditTrailRepository $repository = null) {
        $this->repository = $repository ?? new AuditTrailRepository();
    }

    public function getAuditTrail(string $start, string $end, ?int $employeeId = null): array {
        $this->assertDateRange($start, $end);
        return $this->repository->findByDateRange($start, $end, $employeeId);
    }

    public function getAuditTrailByAction(string $actionType, string $start, string $end): array {
        $this->assertDateRange($start, $end);
        return $this->repository->findByActionType($actionType, $start, $end);
    }

    public function getAuditTrailByEmployee(int $employeeId, int $limit = 100): array {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        return $this->repository->findByEmployee($employeeId, $limit);
    }

    public function logLogin(int $employeeId, string $username): void {
        $this->log($employeeId, 'LOGIN', ['username' => $username]);
    }

    public function logLogout(int $employeeId): void {
        $this->log($employeeId, 'LOGOUT');
    }

    public function logPunch(int $employeeId, string $punchType, int $punchId, array $meta): void {
        $this->log($employeeId, 'PUNCH', [
            'punch_type' => $punchType,
            'punch_id'   => $punchId,
            'meta'       => $meta,
        ]);
    }

    public function logPunchReverse(int $employeeId, int $punchId, string $reason): void {
        $this->log($employeeId, 'PUNCH_REVERSE', [
            'punch_id' => $punchId,
            'reason'   => $reason,
        ]);
    }

    public function logPayrollView(int $employeeId, string $period): void {
        $this->log($employeeId, 'PAYROLL_VIEW', ['period' => $period]);
    }

    public function logPayrollCompute(int $employeeId, string $period, array $result): void {
        $this->log($employeeId, 'PAYROLL_COMPUTE', [
            'period' => $period,
            'result' => $result,
        ]);
    }

    public function logEmployeeEdit(int $actorId, int $targetId, array $changes): void {
        $this->log($actorId, 'EMPLOYEE_EDIT', [
            'target_employee_id' => $targetId,
            'changes'            => $changes,
        ]);
    }

    public function logSettingChange(int $actorId, string $key, mixed $oldValue, mixed $newValue): void {
        $this->log($actorId, 'SETTING_CHANGE', [
            'key'       => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    public function summarizeByEmployee(int $employeeId, string $start, string $end): array {
        $this->assertDateRange($start, $end);

        $rows = $this->repository->findByDateRange($start, $end, $employeeId);

        return $this->countByAction($rows);
    }

    public function summarizeByAction(string $start, string $end): array {
        $this->assertDateRange($start, $end);

        $rows = $this->repository->findByDateRange($start, $end);

        return $this->countByAction($rows);
    }

    public function purgeOlderThan(int $days): int {
        if ($days < 1) {
            throw new InvalidArgumentException('Days must be a positive integer.');
        }

        $cutoff = DateTimeHelper::minutesAgo($days * 24 * 60);

        return $this->repository->purgeOlderThan($cutoff);
    }

    private function log(?int $employeeId, string $actionType, mixed $details = null): void {
        $this->repository->log($employeeId, $actionType, $details, IpHelper::getClientIp());
    }

    private function countByAction(array $rows): array {
        $counts = [];
        foreach ($rows as $r) {
            $type = (string) $r['action_type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        return $counts;
    }

    private function assertDateRange(string $start, string $end): void {
        $tz = new DateTimeZone(self::TIMEZONE);

        $s = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $tz);
        if (!$s || $s->format('Y-m-d') !== $start) {
            throw new InvalidArgumentException("Invalid start date: {$start}");
        }

        $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $tz);
        if (!$e || $e->format('Y-m-d') !== $end) {
            throw new InvalidArgumentException("Invalid end date: {$end}");
        }

        if ($s > $e) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function getDailySummaryForEmployee(int $employeeId, string $date): ?array {
        return $this->dailySummary->findByEmployeeAndDate($employeeId, $date);
    }

    public function getDailySummaryForDate(string $date): array {
        return $this->dailySummary->findByDate($date);
    }
}