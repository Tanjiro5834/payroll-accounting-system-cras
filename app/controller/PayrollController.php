<?php
namespace App\Controller;

class PayrollController
{
    public function index(): void {}
    public function compute(): void {}
    public function show(int $id): void {}
    public function history(int $employeeId): void {}
    public function export(): void {}
    public function markAsPaid(int $id): void {}
    public function thirteenthMonth(): void {}
    public function thirteenthMonthExport(): void {}
}