<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\ThirteenthMonthService;
use RuntimeException;

class ThirteenthMonthController extends BaseController {
    private ThirteenthMonthService $service;

    public function __construct(?ThirteenthMonthService $service = null) {
        $this->service = $service ?? new ThirteenthMonthService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();
        Response::json($this->service->getAll());
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id     = (int) $id;
        $record = $id > 0 ? $this->service->getById($id) : null;
        if ($record === null) {
            Response::error('13th month record not found.', 404);
        }

        Response::json($record);
    }

    public function byEmployee($employeeId): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) $employeeId;
        if ($employeeId < 1) {
            Response::error('Invalid employee ID.', 422);
        }

        Response::json($this->service->getByEmployee($employeeId));
    }

    public function byYear($year): void {
        AuthMiddleware::requireLogin();

        $this->guard(fn() => Response::json($this->service->getByYear((int) $year)));
    }

    public function compute(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $employeeId = $this->jsonInt($_POST, 'employee_id');
        $year       = $this->jsonInt($_POST, 'year', (int) date('Y'));
        $actorId    = $this->requireSessionUser();

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }

        $this->guard(function () use ($employeeId, $year, $actorId) {
            $row = $this->service->computeForEmployee($employeeId, $year, $actorId);
            Response::json(['ok' => true, 'data' => $row]);
        }, 'Failed to compute 13th month.');
    }

    public function computeAll(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $year    = $this->jsonInt($_POST, 'year', (int) date('Y'));
        $actorId = $this->requireSessionUser();

        $this->guard(function () use ($year, $actorId) {
            $count = $this->service->computeForAll($year, $actorId);
            Response::json(['ok' => true, 'computed' => $count]);
        }, 'Failed to compute for all employees.');
    }

    public function recompute($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id      = (int) $id;
        $actorId = $this->requireSessionUser();

        if ($id < 1) {
            Response::error('Invalid record ID.', 422);
        }

        $this->guard(function () use ($id, $actorId) {
            $record = $this->service->getById($id);
            if ($record === null) {
                throw new \DomainException('13th month record not found.');
            }

            $row = $this->service->computeForEmployee(
                (int) $record['employee_id'],
                (int) $record['year'],
                $actorId
            );

            Response::json(['ok' => true, 'data' => $row]);
        }, 'Failed to recompute 13th month.');
    }

    public function approve($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id      = (int) $id;
        $actorId = $this->requireSessionUser();

        if ($id < 1) {
            Response::error('Invalid record ID.', 422);
        }

        $this->guard(function () use ($id, $actorId) {
            $this->service->approve($id, $actorId);
            Response::json(['ok' => true]);
        }, 'Failed to approve 13th month.');
    }

    public function markAsPaid($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id     = (int) $id;
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));

        if ($id < 1) {
            Response::error('Invalid record ID.', 422);
        }

        $this->guard(function () use ($id, $paidAt) {
            $this->service->markAsPaid($id, $paidAt !== '' ? $paidAt : null);
            Response::json(['ok' => true]);
        }, 'Failed to mark as paid.');
    }

    public function delete($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid record ID.', 422);
        }

        $this->guard(function () use ($id) {
            $this->service->delete($id);
            Response::json(['ok' => true]);
        }, 'Failed to delete 13th month record.');
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $year   = $this->queryInt('year', (int) date('Y'));
        $format = strtolower($this->queryTrim('format', 'csv'));

        try {
            $report = $this->service->generateReport($year);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        if ($format === 'pdf') {
            try {
                $bytes = $this->service->exportToPdf($report);
            } catch (RuntimeException $e) {
                Response::error($e->getMessage(), 501);
                return;
            }

            header('Content-Type: application/pdf');
            header("Content-Disposition: attachment; filename=\"13th-month-{$year}.pdf\"");
            header('Content-Length: ' . strlen($bytes));
            echo $bytes;
            exit;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"13th-month-{$year}.csv\"");
        echo $this->service->exportToCsv($report);
        exit;
    }

    public function generateReport(): void {
        AuthMiddleware::requireLogin();

        $year = $this->queryInt('year', (int) date('Y'));

        $this->guard(fn() => Response::json(
            $this->service->generateReport($year)
        ), 'Failed to generate report.');
    }
}