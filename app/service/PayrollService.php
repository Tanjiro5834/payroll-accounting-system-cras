<?php
namespace App\Service;

class PayrollService{
    public function __construct() {}
    public function computeForPeriod(int $employeeId, string $start, string $end): array {}
    public function computeForAllEmployees(string $start, string $end): array {}
    public function computeGrossPay(array $summary, array $employee): float {}
    public function computeOvertimePay(float $hours, float $hourlyRate): float {}
    public function computeNightDiffPay(float $hours, float $hourlyRate): float {}
    public function computeRestDayPay(float $hours, float $hourlyRate): float {}
    public function computeDeductions(int $employeeId, float $grossPay, string $start, string $end): array {}
    public function computeNetPay(float $grossPay, array $deductions): float {}
    public function getHourlyRate(array $employee): float {}
    public function savePayrollPeriod(array $data): int {}
    public function getPayrollHistory(int $employeeId): array {}
    public function exportToCsv(array $payrollData): string {}
    public function markAsPaid(int $payrollId, string $paidAt): bool {}
}