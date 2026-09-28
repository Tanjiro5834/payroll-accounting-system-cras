<?php
namespace App\Controller;

use App\Helper\Response;
use App\Service\DashboardService;

class DashboardController extends BaseController {
    private DashboardService $service;

    public function __construct(?DashboardService $service = null) {
        $this->service = $service ?? new DashboardService();
    }

    // GET ?page=dashboard&action=kpiSummary[&date=Y-m-d]
    public function kpiSummary(): void {
        $this->guard(
            fn() => Response::json($this->service->getKpiSummary($this->queryDate())),
            'Failed to load KPI summary.'
        );
    }

    // GET ?page=dashboard&action=todayActivity[&date=Y-m-d][&limit=20]
    public function todayActivity(): void {
        $this->guard(
            fn() => Response::json($this->service->getTodayActivity($this->queryDate(), $this->queryInt('limit', 20))),
            'Failed to load activity.'
        );
    }

    // GET ?page=dashboard&action=flaggedPunches[&date=Y-m-d]
    public function flaggedPunches(): void {
        $this->guard(
            fn() => Response::json($this->service->getFlaggedPunches($this->queryDate())),
            'Failed to load flagged punches.'
        );
    }

    // GET ?page=dashboard&action=payrollPending
    public function payrollPending(): void {
        $this->guard(
            fn() => Response::json($this->service->getPayrollPending()),
            'Failed to load pending payroll.'
        );
    }

    // GET ?page=dashboard&action=recentActivity[&limit=20]
    public function recentActivity(): void {
        $this->guard(
            fn() => Response::json($this->service->getRecentActivity($this->queryInt('limit', 20))),
            'Failed to load recent activity.'
        );
    }

    private function queryDate(): ?string {
        $date = $this->queryTrim('date');
        return $date === '' ? null : $date;
    }
}
