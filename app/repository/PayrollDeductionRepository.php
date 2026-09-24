<?php
namespace App\Repository;

class PayrollDeductionRepository extends BaseRepository{
    public function create(array $data) {}
    public function update($id, array $data) {}
    public function delete($id) {}

    public function findById($id) {}
    public function findByPayrollPeriod($payrollPeriodId) {}
    public function findByDeduction($deductionId) {}
    public function findAll() {}

    public function sumByPayrollPeriod($payrollPeriodId) {}
    public function deleteByPayrollPeriod($payrollPeriodId) {}
    public function bulkInsert(array $rows) {}
}