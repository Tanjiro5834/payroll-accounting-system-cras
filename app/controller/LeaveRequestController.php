<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\LeaveRequestService;

class LeaveRequestController extends BaseController {
    private LeaveRequestService $service;

    public function __construct(?LeaveRequestService $service = null) {
        $this->service = $service ?? new LeaveRequestService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $status = $this->queryTrim('status');

        $this->guard(function () use ($status) {
            Response::json($status !== ''
                ? $this->service->getByStatus($status)
                : $this->service->getAll()
            );
        }, 'Failed to load leave requests.');
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id      = (int) $id;
        $request = $id > 0 ? $this->service->getById($id) : null;
        if ($request === null) {
            Response::error('Leave request not found.', 404);
        }

        Response::json($request);
    }

    public function create(): void {
        AuthMiddleware::requireLogin();
        Response::json(['page' => 'leave-create']);
    }

    public function store(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $actorId = $this->requireSessionUser();

        $this->guard(function () use ($actorId) {
            $id = $this->service->create($this->input(), $actorId);
            Response::json(['ok' => true, 'id' => $id], 201);
        }, 'Failed to create leave request.');
    }

    public function edit($id): void {
        AuthMiddleware::requireLogin();

        $id      = (int) $id;
        $request = $id > 0 ? $this->service->getById($id) : null;
        if ($request === null) {
            Response::error('Leave request not found.', 404);
        }

        Response::json(['page' => 'leave-edit', 'data' => $request]);
    }

    public function update($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid leave request ID.', 422);
        }

        $this->guard(function () use ($id) {
            $ok = $this->service->update($id, $this->input());
            Response::json(['ok' => $ok]);
        }, 'Failed to update leave request.');
    }

    public function delete($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid leave request ID.', 422);
        }

        $this->guard(function () use ($id) {
            $this->service->delete($id);
            Response::json(['ok' => true]);
        }, 'Failed to delete leave request.');
    }

    public function approve($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id       = (int) $id;
        $approver = $this->requireSessionUser();

        $this->guard(function () use ($id, $approver) {
            $this->service->approve($id, $approver);
            Response::json(['ok' => true]);
        }, 'Failed to approve leave request.');
    }

    public function reject($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id       = (int) $id;
        $approver = $this->requireSessionUser();
        $reason   = trim((string) ($_POST['reason'] ?? ''));

        $this->guard(function () use ($id, $approver, $reason) {
            $this->service->reject($id, $approver, $reason !== '' ? $reason : null);
            Response::json(['ok' => true]);
        }, 'Failed to reject leave request.');
    }

    public function cancel($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid leave request ID.', 422);
        }

        $this->guard(function () use ($id) {
            $this->service->cancel($id);
            Response::json(['ok' => true]);
        }, 'Failed to cancel leave request.');
    }

    public function myRequests(): void {
        AuthMiddleware::requireLogin();

        $employeeId = $this->sessionEmployeeId() ?: $this->queryInt('employee_id');
        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }

        Response::json($this->service->getByEmployee($employeeId));
    }

    public function pending(): void {
        AuthMiddleware::requireLogin();
        Response::json($this->service->getByStatus('pending'));
    }
}