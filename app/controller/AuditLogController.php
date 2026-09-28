<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\TimeAuditService;

class AuditLogController extends BaseController {
    private TimeAuditService $service;

    public function __construct(?TimeAuditService $service = null) {
        $this->service = $service ?? new TimeAuditService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $start      = $this->queryTrim('start');
        $end        = $this->queryTrim('end');
        $employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : null;

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        $this->guard(fn() => Response::json(
            $this->service->getAuditTrail($start, $end, $employeeId)
        ), 'Failed to load audit trail.');
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid audit record ID.', 422);
        }

        $row = $this->service->getById($id);
        if ($row === null) {
            Response::error('Audit record not found.', 404);
        }

        Response::json($row);
    }

    public function byEmployee($employeeId): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) $employeeId;
        if ($employeeId < 1) {
            Response::error('Invalid employee ID.', 422);
        }

        $limit = $this->queryInt('limit', 100);

        $this->guard(fn() => Response::json(
            $this->service->getAuditTrailByEmployee($employeeId, $limit)
        ), 'Failed to load audit trail.');
    }

    public function byAction($actionType): void {
        AuthMiddleware::requireLogin();

        $start = $this->queryTrim('start');
        $end   = $this->queryTrim('end');

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        $this->guard(fn() => Response::json(
            $this->service->getAuditTrailByAction((string) $actionType, $start, $end)
        ), 'Failed to load audit trail.');
    }

    public function byDateRange($start, $end): void {
        AuthMiddleware::requireLogin();

        $this->guard(fn() => Response::json(
            $this->service->getAuditTrail((string) $start, (string) $end)
        ), 'Failed to load audit trail.');
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $start      = $this->queryTrim('start');
        $end        = $this->queryTrim('end');
        $employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : null;

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        try {
            $rows = $this->service->getAuditTrail($start, $end, $employeeId);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        $this->streamCsv('audit-trail.csv',
            ['ID', 'Employee ID', 'Name', 'Action', 'Details', 'IP', 'Performed At'],
            $rows,
            fn(array $r) => [
                $r['id']            ?? '',
                $r['employee_id']   ?? '',
                $r['full_name']     ?? '',
                $r['action_type']   ?? '',
                is_array($r['action_details'] ?? null)
                    ? json_encode($r['action_details'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : ($r['action_details'] ?? ''),
                $r['ip_address']    ?? '',
                $r['performed_at']  ?? '',
            ]
        );
    }

    public function purge(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $days = $this->jsonInt($_POST, 'days', 90);
        if ($days < 1) {
            Response::error('days must be a positive integer.', 422);
        }

        $this->guard(function () use ($days) {
            $deleted = $this->service->purgeOlderThan($days);
            Response::json(['ok' => true, 'deleted' => $deleted]);
        }, 'Failed to purge audit trail.');
    }
}