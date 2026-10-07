<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\MyTimeLogService;

// The logged-in employee's own time log. The employee id always comes from the session, never the request.
class MyTimeLogController extends BaseController {
    private MyTimeLogService $service;

    public function __construct(?MyTimeLogService $service = null) {
        $this->service = $service ?? new MyTimeLogService();
    }

    // GET ?page=my-time-logs&action=month&month=2026-10
    public function month(): void {
        AuthMiddleware::requireLogin();
        $employeeId = $this->sessionEmployeeId();
        if ($employeeId < 1) {
            Response::error('Your account is not linked to an employee record.', 403);
        }

        $month = $this->queryTrim('month', date('Y-m'));
        $this->guard(fn() => Response::json($this->service->month($employeeId, $month)), 'Failed to load your time log.');
    }
}
