<?php
namespace App\Controller;

class PunchController
{
    public function index(): void {}
    public function show(int $employeeId): void {}
    public function store(): void {}
    public function todayStatus(int $employeeId): void {}
    public function history(int $employeeId): void {}
    public function reverse(int $punchId): void {}
}