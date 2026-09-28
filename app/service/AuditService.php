<?php
namespace App\Service;

use App\Repository\AuditTrailRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class AuditService {
    private const TIMEZONE = 'Asia/Manila';

    private AuditTrailRepository $repository;

    public function __construct(?AuditTrailRepository $repository = null) {
        $this->repository = $repository ?? new AuditTrailRepository();
    }

    public function log(?int $employeeId, string $actionType, mixed $details = null): void {
        $this->repository->log($employeeId, $this->normalizeAction($actionType), $details, $this->clientIp());
    }

    public function logPunch(int $employeeId, string $punchType, int $punchId, array $meta): void {
        $this->log($employeeId, 'PUNCH', [
            'punch_type' => $punchType,
            'punch_id'   => $punchId,
            'meta'       => $meta,
        ]);
    }

    public function logLogin(int $employeeId, string $username): void {
        $this->log($employeeId, 'LOGIN', ['username' => $username]);
    }

    public function logLogout(int $employeeId): void {
        $this->log($employeeId, 'LOGOUT');
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

    public function getAuditTrail(string $start, string $end, ?int $employeeId = null): array {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        return $this->repository->findByDateRange($start, $end, $employeeId);
    }

    public function getAuditTrailByAction(string $actionType, string $start, string $end): array {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        return $this->repository->findByActionType($this->normalizeAction($actionType), $start, $end);
    }

    private function normalizeAction(string $actionType): string {
        $actionType = trim($actionType);
        if ($actionType === '') {
            throw new InvalidArgumentException('Action type is required.');
        }
        if (mb_strlen($actionType) > 50) {
            throw new InvalidArgumentException('Action type must be 50 characters or fewer.');
        }
        return $actionType;
    }

    private function clientIp(): ?string {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function assertValidDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function assertDateOrder(string $start, string $end): void {
        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }
}