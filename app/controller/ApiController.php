<?php
namespace App\Controller;

use App\Helper\IpHelper;
use App\Helper\Response;
use App\Service\DashboardService;
use App\Service\EmployeeService;
use App\Service\FlaggingService;
use App\Service\LocationService;
use App\Service\TimePunchService;
use DomainException;
use InvalidArgumentException;

class ApiController {
    private TimePunchService $punch;
    private DashboardService $dashboard;
    private EmployeeService $employees;
    private LocationService $location;
    private FlaggingService $flags;

    public function __construct(
        ?TimePunchService $punch = null,
        ?DashboardService $dashboard = null,
        ?EmployeeService $employees = null,
        ?LocationService $location = null,
        ?FlaggingService $flags = null
    ) {
        $this->punch     = $punch     ?? new TimePunchService();
        $this->dashboard = $dashboard ?? new DashboardService();
        $this->employees = $employees ?? new EmployeeService();
        $this->location  = $location  ?? new LocationService();
        $this->flags     = $flags     ?? new FlaggingService();
    }

    public function punch(): void {
        $this->requireMethod('POST');

        $payload = $this->jsonInput();

        $employeeId = (int) ($payload['employee_id'] ?? $_SESSION['employee_id'] ?? 0);
        $punchType  = trim((string) ($payload['punch_type'] ?? ''));

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }
        if ($punchType === '') {
            Response::error('punch_type is required.', 422);
        }

        try {
            $location = $this->location->captureFromRequest($payload);
            $punchId  = $this->punch->record($employeeId, $punchType, $location);

            Response::json([
                'ok'       => true,
                'punch_id' => $punchId,
                'status'   => $this->punch->getTodayStatus($employeeId),
            ], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to record punch.', 500);
        }
    }

    public function todayStatus(): void {
        $employeeId = (int) ($_GET['employee_id'] ?? $_SESSION['employee_id'] ?? 0);

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }

        try {
            Response::json($this->punch->getTodayStatus($employeeId));
        } catch (\Throwable $e) {
            Response::error('Failed to load status.', 500);
        }
    }

    public function employeeList(): void {
        $query = trim((string) ($_GET['q'] ?? ''));

        try {
            $list = $query === ''
                ? $this->employees->getAllActive()
                : $this->employees->search($query);

            Response::json(array_map(fn($e) => [
                'id'   => (int) $e->getId(),
                'name' => $e->getFullName(),
                'role' => $e->getRole(),
            ], $list));
        } catch (\Throwable $e) {
            Response::error('Failed to load employees.', 500);
        }
    }

    public function locationPing(): void {
        $this->requireMethod('POST');

        $payload    = $this->jsonInput();
        $employeeId = (int) ($payload['employee_id'] ?? $_SESSION['employee_id'] ?? 0);

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }

        try {
            $ping = $this->location->recordPing(
                $employeeId,
                $payload['gps_lat'] ?? null,
                $payload['gps_lng'] ?? null
            );

            $ping['ip_address']         = IpHelper::getClientIp();
            $ping['device_fingerprint'] = $payload['device_fingerprint'] ?? null;

            Response::json(['ok' => true, 'ping' => $ping], 202);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to record ping.', 500);
        }
    }

    public function health(): void {
        Response::json([
            'ok'        => true,
            'service'   => 'payroll-accounting-system',
            'time'      => (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))
                ->format('Y-m-d H:i:s'),
            'php'       => PHP_VERSION,
        ]);
    }

    private function requireMethod(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            Response::error('Method not allowed', 405);
        }
    }

    private function jsonInput(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        return array_merge($_GET, $_POST);
    }
}