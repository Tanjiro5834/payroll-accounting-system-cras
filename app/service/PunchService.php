<?php
namespace App\Service;

use App\Entity\TimePunch;

class PunchService{
    public function __construct() {}
    public function record(int $employeeId, string $punchType, array $locationData): array {}
    public function validatePunch(int $employeeId, string $punchType, string $date): array {}
    public function getTodayStatus(int $employeeId): array {}
    public function getPunchHistory(int $employeeId, string $start, string $end): array {}
    public function getLatestPunch(int $employeeId): ?TimePunch {}
    public function canPunch(int $employeeId, string $punchType): bool {}
    public function calculateNextPunchType(int $employeeId, string $date): ?string {}
    public function reversePunch(int $punchId, int $requestedBy): bool {}
    public function buildPunchFromRequest(int $employeeId, string $punchType, array $locationData): TimePunch {}
}