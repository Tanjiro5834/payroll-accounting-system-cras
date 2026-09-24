<?php
namespace App\Service;

class PayrollDeductionService{
    public function __construct() {}

    public function getByPayrollPeriod($payrollPeriodId) {}
    public function getById($id) {}

    public function create(array $data) {}
    public function update($id, array $data) {}
    public function delete($id) {}

    public function bulkInsert(array $rows) {}
    public function deleteByPayrollPeriod($payrollPeriodId) {}

    public function sumByPayrollPeriod($payrollPeriodId) {}
    public function applyDeductionsToPayroll($payrollPeriodId, array $deductions) {}

    public function computeFromCatalog($employeeId, $grossPay, $periodStart, $periodEnd) {}
    public function snapshotForPayroll($payrollPeriodId, array $computedDeductions) {}
}