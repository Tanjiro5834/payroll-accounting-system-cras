<?php
namespace App\Controller;

use App\Helper\Response;
use App\Service\DashboardService;
use InvalidArgumentException;

class DashboardController {
    private DashboardService $service;

    public function __construct(?DashboardService $service = null) {
        $this->service = $service ?? new DashboardService();
    }

    public function index(): void {
        Response::json(['page' => 'dashboard']);
    }

    public function kpiSummary(): void {
        try {
            $date = $this->queryDate();
            Response::json($this->service->getKpiSummary($date));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load KPI summary.', 500);
        }
    }

    public function todayActivity(): void {
        try {
            $date  = $this->queryDate();
            $limit = (int) ($_GET['limit'] ?? 20);

            Response::json($this->service->getTodayActivity($date, $limit));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load activity.', 500);
        }
    }

    public function recentActivity(): void {
        try {
            $limit = (int) ($_GET['limit'] ?? 20);
            Response::json($this->service->getRecentActivity($limit));
        } catch (\Throwable $e) {
            Response::error('Failed to load recent activity.', 500);
        }
    }

    private function queryDate(): ?string {
        $date = $_GET['date'] ?? null;
        if ($date === null || $date === '') {
            return null;
        }
        return (string) $date;
    }
}