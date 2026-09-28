<?php
namespace App\Controller;

use App\Entity\Employee;
use App\Helper\IpHelper;
use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\EmployeeService;
use App\Service\TimePunchService;
use DomainException;
use InvalidArgumentException;

class PunchController extends BaseController {
    private const STAFF_ROLES = ['admin', 'owner'];

    private TimePunchService $service;
    private EmployeeService $employees;

    public function __construct(
        ?TimePunchService $service = null,
        ?EmployeeService $employees = null
    ) {
        $this->service   = $service   ?? new TimePunchService();
        $this->employees = $employees ?? new EmployeeService();
    }

    // GET ?page=punch-employee-list&action=index
    // Admin/owner get every active employee (shared kiosk); an employee gets only themselves.
    public function index(): void {
        $this->guard(function () {
            $list = $this->isStaff()
                ? $this->employees->getAllActive()
                : array_filter([$this->employees->getById($this->sessionEmployeeId())]);

            Response::json([
                'can_pick_any' => $this->isStaff(),
                'employees'    => array_map(fn(Employee $e) => $this->card($e), array_values($list)),
            ]);
        }, 'Failed to load employees.');
    }

    // GET ?page=punch&action=todayStatus[&id=5]   (no id → the logged-in employee)
    public function todayStatus(int $employeeId = 0): void {
        $this->guard(function () use ($employeeId) {
            $id       = $this->resolveEmployee($employeeId);
            $employee = $this->employees->getById($id) ?? throw new DomainException('Employee not found.');

            Response::json(['employee' => $this->card($employee)] + $this->service->getTodayStatus($id));
        }, 'Failed to load punch status.');
    }

    // POST ?page=punch&action=store
    // body: { employee_id, punch_type, gps_lat, gps_lng, gps_accuracy, device_fingerprint }
    public function store(): void {
        $this->requireMethod('POST');
        $input = $this->input();

        $this->guard(function () use ($input) {
            $id        = $this->resolveEmployee($this->jsonInt($input, 'employee_id'));
            $punchType = strtoupper($this->jsonField($input, 'punch_type'));

            $this->service->record($id, $punchType, $this->locationPayload($input));

            // Send the fresh status back so the page re-renders from the server's truth.
            Response::json($this->service->getTodayStatus($id), 201);
        }, 'Failed to record punch.');
    }

    // GET ?page=punch&action=history[&id=5]&start=Y-m-d&end=Y-m-d
    public function history(int $employeeId = 0): void {
        $this->guard(function () use ($employeeId) {
            $id = $this->resolveEmployee($employeeId);
            Response::json($this->service->getPunchHistory($id, $this->queryTrim('start'), $this->queryTrim('end')));
        }, 'Failed to load history.');
    }

    // Employees may only act on their own record; staff may pick anyone (kiosk mode).
    private function resolveEmployee(int $requested): int {
        if ($this->isStaff()) {
            if ($requested < 1) {
                throw new InvalidArgumentException('employee_id is required.');
            }
            return $requested;
        }

        $own = $this->sessionEmployeeId();
        if ($own < 1) {
            Response::error('Your account is not linked to an employee.', 403);
        }
        if ($requested > 0 && $requested !== $own) {
            Response::error('You can only punch for yourself.', 403);
        }
        return $own;
    }

    private function isStaff(): bool {
        return in_array(AuthMiddleware::user()['role'] ?? '', self::STAFF_ROLES, true);
    }

    private function card(Employee $e): array {
        return [
            'id'    => (int) $e->getId(),
            'name'  => $e->getFullName(),
            'role'  => $e->getRole(),
            'photo' => $e->getProfilePhotoUrl(),
        ];
    }

    // IP comes from the server, never from the request body.
    private function locationPayload(array $input): array {
        return [
            'ip_address'         => IpHelper::getClientIp(),
            'gps_lat'            => $this->floatOrNull($input['gps_lat'] ?? null),
            'gps_lng'            => $this->floatOrNull($input['gps_lng'] ?? null),
            'gps_accuracy'       => is_numeric($input['gps_accuracy'] ?? null) ? (int) $input['gps_accuracy'] : null,
            'device_fingerprint' => $this->jsonField($input, 'device_fingerprint') ?: null,
        ];
    }

    private function floatOrNull(mixed $value): ?float {
        return is_numeric($value) ? (float) $value : null;
    }
}