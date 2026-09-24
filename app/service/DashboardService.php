<?php
namespace App\Service;

class DashboardService{
    private DashboardRepository $repository;

    public function __construct() {
        $this->repository = new DashboardRepository();
    }
    
    public function getKpiSummary(string $date): array {
        
    }

    public function getTodayActivity(string $date): array {

    }

    public function getFlaggedPunches(string $date): array {

    }

    public function getEmployeesPunchedIn(string $date): array {

    }

    public function getEmployeesNotPunchedIn(string $date): array {

    }

    public function getPayrollPending(): array {

    }

    public function getRecentActivity(int $limit = 20): array {

    }
}