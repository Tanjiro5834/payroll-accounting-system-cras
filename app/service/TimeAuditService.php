<?php
namespace App\Service;

use App\Entity\ThirteenthMonthRecord;

class ThirteenthMonthService{
    public function __construct() {}

    public function getAll() {}
    public function getById($id) {}
    public function getByEmployee($employeeId) {}
    public function getByEmployeeAndYear($employeeId, $year) {}
    public function getByYear($year) {}
    public function getByStatus($status) {}

    public function computeForEmployee($employeeId, $year) {}
    public function computeForAll($year) {}
    public function computeBasicSalary($employeeId, $year) {}
    public function computeProRated($employeeId, $dateHired, $year) {}

    public function countMonthsWorked($employeeId, $year) {}
    public function countDaysWorked($employeeId, $year) {}

    public function save(array $data) {}
    public function upsert(array $data) {}
    public function approve($id, $approvedBy) {}
    public function markAsPaid($id, $paidAt) {}
    public function delete($id) {}

    public function generateReport($year) {}
    public function exportToCsv(array $report) {}
    public function exportToPdf(array $report) {}

    public function getTotalPayout($year) {}
    public function getUnpaidRecords($year) {}
}