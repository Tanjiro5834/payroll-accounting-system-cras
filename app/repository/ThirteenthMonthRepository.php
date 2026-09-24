<?php
namespace App\Repository;

use App\Entity\ThirteenthMonthRecord;

class ThirteenthMonthRepository extends BaseRepository{
    public function create(array $data) {}
    public function update($id, array $data) {}
    public function delete($id) {}

    public function findById($id) {}
    public function findByEmployee($employeeId) {}
    public function findByEmployeeAndYear($employeeId, $year) {}
    public function findByYear($year) {}
    public function findByStatus($status) {}
    public function findAll() {}

    public function upsert(array $data) {}
    public function markAsPaid($id, $paidAt) {}
    public function approve($id, $approvedBy) {}

    public function sumByYear($year) {}
    public function countByYear($year) {}
}