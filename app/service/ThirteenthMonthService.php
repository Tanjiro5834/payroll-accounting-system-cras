<?php
namespace App\Service;

class ThirteenthMonthService{
    public function __construct() {}
    public function computeForYear(int $employeeId, int $year): array {}
    public function computeForAllEmployees(int $year): array {}
    public function computeBasicSalary(int $employeeId, int $year): float {}
    public function computeProRatedBasicSalary(int $employeeId, string $dateHired, int $year): float {}
    public function countMonthsWorked(int $employeeId, int $year): int {}
    public function countDaysWorked(int $employeeId, int $year): int {}
    public function getHourlyBasic(int $employeeId, int $year): float {}
    public function getMonthlyBasic(int $employeeId, int $year): float {}
    public function generateReport(int $year): array {}
    public function exportToCsv(array $report): string {}
    public function exportToPdf(array $report): string {}
}