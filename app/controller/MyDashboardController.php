<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\AuditService;
use App\Service\EmployeeService;
use App\Service\MyDashboardService;
use App\Service\UserService;

// Self-service endpoints. The employee is ALWAYS the logged-in user's employee_id —
// no endpoint accepts an employee id from the request.
class MyDashboardController extends BaseController {
    private MyDashboardService $service;

    public function __construct(?MyDashboardService $service = null) {
        $this->service = $service ?? new MyDashboardService();
    }

    // GET ?page=my-dashboard&action=summary
    public function summary(): void {
        $employeeId = $this->me();
        $this->guard(fn() => Response::json($this->service->summary($employeeId)), 'Failed to load your dashboard.');
    }

    // GET ?page=my-dashboard&action=attendance&month=2026-10
    public function attendance(): void {
        $employeeId = $this->me();
        $month = $this->queryTrim('month', date('Y-m'));
        $this->guard(fn() => Response::json($this->service->attendance($employeeId, $month)), 'Failed to load attendance.');
    }

    // GET ?page=my-dashboard&action=trend
    public function trend(): void {
        $employeeId = $this->me();
        $this->guard(fn() => Response::json($this->service->trend($employeeId)), 'Failed to load trend.');
    }

    // GET ?page=my-dashboard&action=payslips
    public function payslips(): void {
        $employeeId = $this->me();
        $this->guard(fn() => Response::json($this->service->payslips($employeeId)), 'Failed to load payslips.');
    }

    // GET ?page=my-dashboard&action=payslip&id=38
    public function payslip(int $id): void {
        $employeeId = $this->me();
        $this->guard(fn() => Response::json($this->service->payslip($employeeId, $id)), 'Failed to load payslip.');
    }

    // GET ?page=my-dashboard&action=rateHistory
    public function rateHistory(): void {
        $employeeId = $this->me();
        $this->guard(fn() => Response::json($this->service->rateHistory($employeeId)), 'Failed to load rate history.');
    }

    // POST ?page=my-dashboard&action=uploadPhoto   multipart field: photo
    public function uploadPhoto(): void {
        $this->requireMethod('POST');
        $employeeId = $this->me();
        if (!isset($_FILES['photo'])) {
            Response::error('No file uploaded.', 422);
        }
        $this->guard(
            fn() => Response::json(['ok' => true, 'url' => (new EmployeeService())->uploadProfilePhoto($employeeId, $_FILES['photo'])]),
            'Failed to upload photo.'
        );
    }

    // POST ?page=my-dashboard&action=removePhoto
    public function removePhoto(): void {
        $this->requireMethod('POST');
        $employeeId = $this->me();
        $this->guard(function () use ($employeeId) {
            (new EmployeeService())->deleteProfilePhoto($employeeId);
            Response::json(['ok' => true]);
        }, 'Failed to remove photo.');
    }

    // POST ?page=my-dashboard&action=changePassword   body: {current_password, new_password}
    public function changePassword(): void {
        $this->requireMethod('POST');
        $userId = $this->requireSessionUser();
        $input  = $this->input();
        $current = (string) ($input['current_password'] ?? '');
        $next    = (string) ($input['new_password'] ?? '');

        if ($current === '' || $next === '') {
            Response::error('Current and new passwords are required.', 422);
        }

        $this->guardWithStatus(function () use ($userId, $current, $next) {
            (new UserService())->changePassword($userId, $current, $next);
            (new AuditService())->record('PASSWORD_CHANGE', $this->sessionEmployeeId() ?: null);
            Response::json(['ok' => true]);
        }, 422, 'Failed to change password.');
    }

    // 403, not 401: api.js treats 401 as "session expired" and bounces to login.
    private function me(): int {
        AuthMiddleware::requireLogin();
        $id = $this->sessionEmployeeId();
        if ($id < 1) {
            Response::error('Your account is not linked to an employee record.', 403);
        }
        return $id;
    }
}
