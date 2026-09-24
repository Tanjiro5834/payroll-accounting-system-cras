<?php
namespace App\Service;

class AuditService{
    public function __construct() {}
    public function log(?int $employeeId, string $actionType, $details = null): void {}
    public function logPunch(int $employeeId, string $punchType, int $punchId, array $meta): void {}
    public function logLogin(int $employeeId, string $username): void {}
    public function logLogout(int $employeeId): void {}
    public function logPayrollView(int $employeeId, string $period): void {}
    public function logPayrollCompute(int $employeeId, string $period, array $result): void {}
    public function logEmployeeEdit(int $actorId, int $targetId, array $changes): void {}
    public function getAuditTrail(string $start, string $end, ?int $employeeId = null): array {}
    public function getAuditTrailByAction(string $actionType, string $start, string $end): array {}
}