<?php
namespace App\Service;

class FlaggingService{
    public function __construct() {

    }
    
    public function runDailyFlags(string $date): array {}
    public function flagOutFromDifferentIp(string $date): int {}
    public function flagMissingPunch(string $date): int {}
    public function flagShortLunch(string $date): int {}
    public function flagLongLunch(string $date): int {}
    public function flagEarlyOut(string $date): int {}
    public function flagHabitualLate(int $employeeId, string $month): int {}
    public function flagSameDeviceMultipleEmployees(string $date): int {}
    public function getAllFlags(?string $date = null): array {}
    public function getFlagsByEmployee(int $employeeId, string $start, string $end): array {}
    public function markFlagReviewed(int $punchId, int $reviewerId): bool {}
    public function dismissFlag(int $punchId, int $reviewerId, string $reason): bool {}
    public function summarizeFlags(string $date): array {}
}