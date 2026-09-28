<?php
namespace App\Controller;

use App\Helper\IpHelper;
use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\FlaggingService;
use App\Service\TimePunchService;

class PunchController extends BaseController {
    private TimePunchService $service;
    private FlaggingService $flagging;

    public function __construct(
        ?TimePunchService $service = null,
        ?FlaggingService $flagging = null
    ) {
        $this->service  = $service  ?? new TimePunchService();
        $this->flagging = $flagging ?? new FlaggingService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $date = $this->queryTrim('date');

        $this->guard(fn() => Response::json(
            $this->flagging->getAllFlags($date !== '' ? $date : null)
        ), 'Failed to load punches.');
    }

    public function show(int $employeeId): void {
        AuthMiddleware::requireLogin();

        if ($employeeId < 1) {
            Response::error('Invalid employee ID.', 422);
        }

        $this->guard(fn() => Response::json(
            $this->service->getTodayStatus($employeeId)
        ), 'Failed to load status.');
    }

    public function store(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $employeeId = $this->jsonInt($_POST, 'employee_id', $this->sessionEmployeeId());
        $punchType  = $this->jsonField($_POST, 'punch_type');

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }
        if ($punchType === '') {
            Response::error('punch_type is required.', 422);
        }

        $this->guard(function () use ($employeeId, $punchType) {
            $id = $this->service->record($employeeId, $punchType, $this->locationPayload());
            Response::json(['ok' => true, 'punch_id' => $id], 201);
        }, 'Failed to record punch.');
    }

    public function todayStatus(int $employeeId): void {
        AuthMiddleware::requireLogin();
        $this->guard(fn() => Response::json(
            $this->service->getTodayStatus($employeeId)
        ), 'Failed to load status.');
    }

    public function history(int $employeeId): void {
        AuthMiddleware::requireLogin();

        $start = $this->queryTrim('start');
        $end   = $this->queryTrim('end');

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        $this->guard(fn() => Response::json(
            $this->service->getPunchHistory($employeeId, $start, $end)
        ), 'Failed to load history.');
    }

    public function reverse(int $punchId): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $actorId = $this->requireSessionUser();

        $this->guardWithStatus(function () use ($punchId, $actorId) {
            $this->service->reversePunch($punchId, $actorId);
            Response::json(['ok' => true]);
        }, 404, 'Failed to reverse punch.');
    }

    private function locationPayload(): array {
        return [
            'ip_address'         => IpHelper::getClientIp(),
            'gps_lat'            => $this->floatOrNull($_POST['gps_lat']      ?? null),
            'gps_lng'            => $this->floatOrNull($_POST['gps_lng']      ?? null),
            'gps_accuracy'       => isset($_POST['gps_accuracy']) && $_POST['gps_accuracy'] !== ''
                ? (int) $_POST['gps_accuracy']
                : null,
            'device_fingerprint' => isset($_POST['device_fingerprint']) && $_POST['device_fingerprint'] !== ''
                ? trim((string) $_POST['device_fingerprint'])
                : null,
        ];
    }

    private function floatOrNull(mixed $value): ?float {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }
}